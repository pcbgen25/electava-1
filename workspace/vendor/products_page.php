<?php
$pageTitle = 'My Products';
require_once __DIR__ . '/../includes/auth.php';
requireRole('vendor');

function normalizeBulkHeader(string $value): string {
    $value = trim($value);
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function detectCsvDelimiter(string $path): string {
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return ',';
    }

    $firstLine = (string)fgets($handle);
    fclose($handle);

    $delimiters = [',', ';', "\t", '|'];
    $bestDelimiter = ',';
    $bestScore = -1;

    foreach ($delimiters as $delimiter) {
        $score = substr_count($firstLine, $delimiter);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

function buildBulkColumnMap(array $headers): array {
    $aliases = [
        'part_number' => ['part_number', 'part_no', 'part', 'mpn', 'manufacturer_part_number'],
        'name' => ['name', 'product_name', 'product', 'display_name', 'title'],
        'description' => ['description', 'desc', 'details'],
        'manufacturer' => ['manufacturer', 'manufacturer_name', 'brand'],
        'category' => ['category', 'category_name', 'product_category'],
        'price' => ['price', 'unit_price', 'price_inr', 'inr_price', 'selling_price'],
        'stock' => ['stock', 'qty', 'quantity', 'inventory'],
        'datasheet_url' => ['datasheet_url', 'datasheet', 'datasheet_link', 'document_url', 'url'],
    ];

    $map = [];
    foreach ($headers as $index => $header) {
        foreach ($aliases as $canonical => $options) {
            if (in_array($header, $options, true) && !isset($map[$canonical])) {
                $map[$canonical] = $index;
                break;
            }
        }
    }

    return $map;
}

function findOrCreateManufacturerId(PDO $pdo, string $name): ?int {
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $lookup = $pdo->prepare("SELECT id FROM manufacturers WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $lookup->execute([$name]);
    $existing = $lookup->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $insert = $pdo->prepare("INSERT INTO manufacturers (name) VALUES (?)");
    $insert->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function findOrCreateCategoryId(PDO $pdo, string $name): ?int {
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $lookup = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $lookup->execute([$name]);
    $existing = $lookup->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $insert = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, NULL)");
    $insert->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function createVendorComponent(PDO $pdo, int $vendorUserId, array $data): int {
    $partNumber = trim((string)($data['part_number'] ?? ''));
    $name = trim((string)($data['name'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $datasheetUrl = trim((string)($data['datasheet_url'] ?? ''));
    $price = (float)($data['price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $manufacturerId = $data['manufacturer_id'] ?: null;
    $categoryId = $data['category_id'] ?: null;

    if ($partNumber === '') {
        throw new RuntimeException('Part Number is required.');
    }
    if ($name === '') {
        $name = $partNumber;
    }
    if ($price < 0) {
        throw new RuntimeException('Price cannot be negative.');
    }
    if ($stock < 0) {
        throw new RuntimeException('Stock cannot be negative.');
    }
    if ($datasheetUrl !== '' && !filter_var($datasheetUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Datasheet URL must be a valid URL.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO components (
            part_number,
            name,
            description,
            manufacturer_id,
            category_id,
            vendor_id,
            price,
            stock,
            status,
            datasheet_url,
            created_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $partNumber,
        $name,
        $description,
        $manufacturerId,
        $categoryId,
        $vendorUserId,
        $price,
        $stock,
        'draft',
        $datasheetUrl,
        $vendorUserId,
    ]);

    return (int)$pdo->lastInsertId();
}

$uid = (int)$_SESSION['user_id'];
$feedback = '';
$feedbackTone = 'success';
$bulkSummary = null;
$singleFormDefaults = [
    'part_number' => '',
    'name' => '',
    'description' => '',
    'manufacturer_id' => '',
    'new_manufacturer_name' => '',
    'category_id' => '',
    'new_category_name' => '',
    'price' => '0',
    'stock' => '0',
    'datasheet_url' => '',
];
$singleForm = $singleFormDefaults;
$templateHeaders = ['part_number', 'name', 'description', 'manufacturer', 'category', 'price', 'stock', 'datasheet_url'];
$templateRows = [
    ['LM7805CT', '5V Voltage Regulator', 'TO-220 linear regulator for 5V output', 'STMicroelectronics', 'Power Management', '12.50', '500', 'https://example.com/datasheet-lm7805.pdf'],
    ['ESP32-WROOM-32E', 'Wi-Fi MCU Module', '2.4 GHz Wi-Fi and Bluetooth module', 'Espressif', 'Wireless Modules', '185.00', '120', 'https://example.com/datasheet-esp32.pdf'],
];

$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$vendor) {
        $feedback = 'Vendor profile not found. Please contact an administrator before adding products.';
        $feedbackTone = 'error';
    } elseif ($_POST['action'] === 'create_single') {
        $singleForm = [
            'part_number' => trim((string)($_POST['part_number'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'manufacturer_id' => (string)($_POST['manufacturer_id'] ?? ''),
            'new_manufacturer_name' => trim((string)($_POST['new_manufacturer_name'] ?? '')),
            'category_id' => (string)($_POST['category_id'] ?? ''),
            'new_category_name' => trim((string)($_POST['new_category_name'] ?? '')),
            'price' => trim((string)($_POST['price'] ?? '0')),
            'stock' => trim((string)($_POST['stock'] ?? '0')),
            'datasheet_url' => trim((string)($_POST['datasheet_url'] ?? '')),
        ];

        try {
            if ($singleForm['price'] !== '' && !is_numeric($singleForm['price'])) {
                throw new RuntimeException('Price must be numeric.');
            }
            if ($singleForm['stock'] !== '' && !preg_match('/^-?\d+$/', $singleForm['stock'])) {
                throw new RuntimeException('Stock must be a whole number.');
            }

            $manufacturerId = $singleForm['new_manufacturer_name'] !== ''
                ? findOrCreateManufacturerId($pdo, $singleForm['new_manufacturer_name'])
                : ($singleForm['manufacturer_id'] !== '' ? (int)$singleForm['manufacturer_id'] : null);

            $categoryId = $singleForm['new_category_name'] !== ''
                ? findOrCreateCategoryId($pdo, $singleForm['new_category_name'])
                : ($singleForm['category_id'] !== '' ? (int)$singleForm['category_id'] : null);

            $componentId = createVendorComponent($pdo, $uid, [
                'part_number' => $singleForm['part_number'],
                'name' => $singleForm['name'],
                'description' => $singleForm['description'],
                'manufacturer_id' => $manufacturerId,
                'category_id' => $categoryId,
                'price' => $singleForm['price'] === '' ? 0 : (float)$singleForm['price'],
                'stock' => $singleForm['stock'] === '' ? 0 : (int)$singleForm['stock'],
                'datasheet_url' => $singleForm['datasheet_url'],
            ]);

            logAudit($pdo, 'vendor_create_component', 'component', $componentId, 'Vendor created single product');
            $feedback = 'Product saved as draft. Review it below and submit it when ready.';
            $feedbackTone = 'success';
            $singleForm = $singleFormDefaults;
        } catch (Throwable $error) {
            $feedback = $error->getMessage();
            $feedbackTone = 'error';
        }
    } elseif ($_POST['action'] === 'bulk_upload') {
        $bulkSummary = ['created' => 0, 'skipped' => 0, 'errors' => [], 'file_name' => ''];

        try {
            $file = $_FILES['bulk_product_file'] ?? null;
            if (!$file || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose the filled sample sheet before uploading.');
            }
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('The bulk upload file could not be read.');
            }

            $bulkSummary['file_name'] = (string)$file['name'];
            $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                throw new RuntimeException('Upload the sample sheet as CSV. You can edit it in Excel and save it back as CSV.');
            }

            $delimiter = detectCsvDelimiter((string)$file['tmp_name']);
            $handle = fopen((string)$file['tmp_name'], 'rb');
            if ($handle === false) {
                throw new RuntimeException('Unable to open the uploaded file.');
            }

            $headers = fgetcsv($handle, 0, $delimiter);
            if (!$headers) {
                fclose($handle);
                throw new RuntimeException('The uploaded file is empty.');
            }

            $normalizedHeaders = array_map('normalizeBulkHeader', $headers);
            $columnMap = buildBulkColumnMap($normalizedHeaders);
            if (!isset($columnMap['part_number'])) {
                fclose($handle);
                throw new RuntimeException('The sheet must include a part_number column.');
            }

            $rowNumber = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                $rowData = [];
                foreach ($columnMap as $field => $index) {
                    $rowData[$field] = trim((string)($row[$index] ?? ''));
                }

                $isBlankRow = !array_filter($rowData, static fn($value) => $value !== '');
                if ($isBlankRow) {
                    $bulkSummary['skipped']++;
                    continue;
                }

                try {
                    $partNumber = trim((string)($rowData['part_number'] ?? ''));
                    $name = trim((string)($rowData['name'] ?? ''));
                    $priceValue = trim((string)($rowData['price'] ?? ''));
                    $stockValue = trim((string)($rowData['stock'] ?? ''));
                    $datasheetUrl = trim((string)($rowData['datasheet_url'] ?? ''));

                    if ($partNumber === '') {
                        throw new RuntimeException('Part Number is required.');
                    }
                    if ($priceValue !== '' && !is_numeric($priceValue)) {
                        throw new RuntimeException('Price must be numeric.');
                    }
                    if ($stockValue !== '' && !preg_match('/^-?\d+$/', $stockValue)) {
                        throw new RuntimeException('Stock must be a whole number.');
                    }
                    if ($datasheetUrl !== '' && !filter_var($datasheetUrl, FILTER_VALIDATE_URL)) {
                        throw new RuntimeException('Datasheet URL must be a valid URL.');
                    }

                    $manufacturerId = findOrCreateManufacturerId($pdo, (string)($rowData['manufacturer'] ?? ''));
                    $categoryId = findOrCreateCategoryId($pdo, (string)($rowData['category'] ?? ''));

                    createVendorComponent($pdo, $uid, [
                        'part_number' => $partNumber,
                        'name' => $name,
                        'description' => (string)($rowData['description'] ?? ''),
                        'manufacturer_id' => $manufacturerId,
                        'category_id' => $categoryId,
                        'price' => $priceValue === '' ? 0 : (float)$priceValue,
                        'stock' => $stockValue === '' ? 0 : (int)$stockValue,
                        'datasheet_url' => $datasheetUrl,
                    ]);

                    $bulkSummary['created']++;
                } catch (Throwable $rowError) {
                    $bulkSummary['errors'][] = 'Row ' . $rowNumber . ': ' . $rowError->getMessage();
                }
            }

            fclose($handle);

            if ($bulkSummary['created'] === 0 && $bulkSummary['skipped'] === 0 && !$bulkSummary['errors']) {
                throw new RuntimeException('The uploaded sheet did not contain any product rows.');
            }

            if ($bulkSummary['created'] > 0) {
                logAudit(
                    $pdo,
                    'vendor_bulk_upload_components',
                    'component',
                    null,
                    'Imported ' . $bulkSummary['created'] . ' products from ' . $bulkSummary['file_name']
                );
            }

            if ($bulkSummary['created'] > 0 && !$bulkSummary['errors']) {
                $feedback = 'Bulk upload completed. ' . $bulkSummary['created'] . ' products were saved as draft.';
                $feedbackTone = 'success';
            } elseif ($bulkSummary['created'] > 0) {
                $feedback = 'Bulk upload completed with some row issues. ' . $bulkSummary['created'] . ' products were saved as draft.';
                $feedbackTone = 'warning';
            } else {
                $feedback = 'No products were imported. Fix the sheet rows listed below and upload again.';
                $feedbackTone = 'error';
            }
        } catch (Throwable $error) {
            $feedback = $error->getMessage();
            $feedbackTone = 'error';
        }
    } elseif ($_POST['action'] === 'submit') {
        $compId = (int)($_POST['component_id'] ?? 0);
        $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND vendor_id = ? AND status = 'draft'")
            ->execute([$compId, $uid]);
        logAudit($pdo, 'vendor_submit_component', 'component', $compId, 'Submitted for marketplace approval');
        $feedback = 'Product submitted for marketplace approval.';
        $feedbackTone = 'success';
    } elseif ($_POST['action'] === 'update_stock') {
        $compId = (int)($_POST['component_id'] ?? 0);
        $newStock = (int)($_POST['stock'] ?? 0);
        $pdo->prepare("UPDATE components SET stock = ? WHERE id = ? AND vendor_id = ?")->execute([$newStock, $compId, $uid]);
        logAudit($pdo, 'vendor_update_stock', 'component', $compId, 'Stock updated to ' . $newStock);
        $feedback = 'Stock updated.';
        $feedbackTone = 'success';
    } elseif ($_POST['action'] === 'update_price') {
        $compId = (int)($_POST['component_id'] ?? 0);
        $newPrice = (float)($_POST['price'] ?? 0);
        $pdo->prepare("UPDATE components SET price = ? WHERE id = ? AND vendor_id = ?")->execute([$newPrice, $compId, $uid]);
        logAudit($pdo, 'vendor_update_price', 'component', $compId, 'Price updated to ' . $newPrice);
        $feedback = 'Price updated.';
        $feedbackTone = 'success';
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$entryMode = trim((string)($_GET['entry'] ?? 'single'));
if (!in_array($entryMode, ['single', 'bulk'], true)) {
    $entryMode = 'single';
}
$sql = "SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
        FROM components c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.vendor_id = ?";
$params = [$uid];
if ($statusFilter !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$totalProducts = count($products);
$activeCount = count(array_filter($products, static fn($p) => $p['status'] === 'active'));
$draftCount = count(array_filter($products, static fn($p) => $p['status'] === 'draft'));
$lowStockCount = count(array_filter($products, static fn($p) => $p['stock'] <= $p['low_stock_threshold'] && $p['status'] === 'active'));
$feedbackStyles = [
    'success' => 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300',
    'warning' => 'bg-amber-500/10 border border-amber-500/20 text-amber-300',
    'error' => 'bg-red-500/10 border border-red-500/20 text-red-300',
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$vendor): ?>
<div class="glass-card p-8 rounded-2xl text-center">
    <h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3>
    <p class="text-slate-500">Please contact an administrator to set up your vendor profile.</p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php exit; ?>
<?php endif; ?>

<?php if ($feedback !== ''): ?>
<div class="<?= $feedbackStyles[$feedbackTone] ?? $feedbackStyles['success'] ?> px-4 py-3 rounded-xl mb-4 text-sm flex items-start gap-2">
    <i class="fa-solid fa-circle-info mt-0.5"></i>
    <div><?= htmlspecialchars($feedback) ?></div>
</div>
<?php endif; ?>

<?php if ($bulkSummary): ?>
<div class="glass-card rounded-2xl p-4 border border-slate-700/50 mb-6">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <div>
            <h3 class="text-sm font-semibold text-white">Bulk Upload Result</h3>
            <p class="text-xs text-slate-500 mt-1">
                File: <span class="text-slate-300"><?= htmlspecialchars($bulkSummary['file_name'] ?: 'Uploaded sheet') ?></span>
            </p>
        </div>
        <div class="flex gap-2 text-xs">
            <span class="px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">Created: <?= (int)$bulkSummary['created'] ?></span>
            <span class="px-3 py-1.5 rounded-full bg-slate-500/10 border border-slate-500/20 text-slate-300">Skipped Blank Rows: <?= (int)$bulkSummary['skipped'] ?></span>
            <span class="px-3 py-1.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-300">Row Issues: <?= count($bulkSummary['errors']) ?></span>
        </div>
    </div>
    <?php if ($bulkSummary['errors']): ?>
    <div class="bg-slate-900/40 border border-slate-800 rounded-xl px-4 py-3">
        <div class="text-xs font-semibold text-white mb-2">Rows to Fix</div>
        <div class="space-y-2 text-xs text-slate-300">
            <?php foreach (array_slice($bulkSummary['errors'], 0, 8) as $rowError): ?>
            <div><?= htmlspecialchars($rowError) ?></div>
            <?php endforeach; ?>
            <?php if (count($bulkSummary['errors']) > 8): ?>
            <div class="text-slate-500">And <?= count($bulkSummary['errors']) - 8 ?> more row issues.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between gap-4 flex-wrap mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">My Products</h2>
        <p class="text-sm text-slate-500 mt-1">Choose one vendor product entry flow at a time, then manage your saved products below.</p>
    </div>
    <div class="flex gap-3 flex-wrap">
        <a href="/vendor/products.php?entry=single#entryPanel" class="<?= $entryMode === 'single' ? 'btn-primary text-white' : 'btn-secondary text-slate-200' ?> px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>Single Product
        </a>
        <a href="/vendor/products.php?entry=bulk#entryPanel" class="<?= $entryMode === 'bulk' ? 'btn-primary text-white' : 'btn-secondary text-slate-200' ?> px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center gap-2">
            <i class="fa-solid fa-file-arrow-up"></i>Bulk Upload
        </a>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-boxes-stacked text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalProducts ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Total Products</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $activeCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Active Listings</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-slate-500/10 flex items-center justify-center"><i class="fa-solid fa-pen-to-square text-slate-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $draftCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Drafts</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation text-red-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $lowStockCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Low Stock</div>
    </div>
</div>

<div id="entryPanel" class="mb-6">
    <?php if ($entryMode === 'single'): ?>
    <section class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-white">Add Single Product</h3>
                <p class="text-xs text-slate-500 mt-1">Single product mode is active. Only the single-product form is shown here.</p>
            </div>
            <div class="px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs">Saved as Draft</div>
        </div>

        <form method="POST" action="/vendor/products.php?entry=single#entryPanel" class="space-y-4">
            <input type="hidden" name="action" value="create_single">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Part Number *</label>
                    <input type="text" name="part_number" required value="<?= htmlspecialchars($singleForm['part_number']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. LM7805CT">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Product Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($singleForm['name']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. 5V Voltage Regulator">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Short product details for the marketplace."><?= htmlspecialchars($singleForm['description']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Manufacturer</label>
                        <select name="manufacturer_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            <option value="">Select existing manufacturer</option>
                            <?php foreach ($manufacturers as $manufacturer): ?>
                            <option value="<?= (int)$manufacturer['id'] ?>" <?= $singleForm['manufacturer_id'] === (string)$manufacturer['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($manufacturer['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Or Add New Manufacturer</label>
                        <input type="text" name="new_manufacturer_name" value="<?= htmlspecialchars($singleForm['new_manufacturer_name']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. Infineon">
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Category</label>
                        <select name="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            <option value="">Select existing category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= $singleForm['category_id'] === (string)$category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Or Add New Category</label>
                        <input type="text" name="new_category_name" value="<?= htmlspecialchars($singleForm['new_category_name']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. Power Management">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Price (INR)</label>
                    <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($singleForm['price']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Stock</label>
                    <input type="number" name="stock" min="0" value="<?= htmlspecialchars($singleForm['stock']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Datasheet URL</label>
                    <input type="url" name="datasheet_url" value="<?= htmlspecialchars($singleForm['datasheet_url']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="https://example.com/datasheet.pdf">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-800/70">
                <a href="/vendor/products.php?entry=single#entryPanel" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Clear</a>
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Draft Product
                </button>
            </div>
        </form>
    </section>
    <?php else: ?>
    <section class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-white">Bulk Upload</h3>
                <p class="text-xs text-slate-500 mt-1">Bulk upload mode is active. Only the bulk sheet upload flow is shown here.</p>
            </div>
            <a href="/vendor/product_template.php" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200 inline-flex items-center gap-2">
                <i class="fa-solid fa-file-csv"></i>Download Sample Sheet
            </a>
        </div>

        <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-4 mb-4">
            <div class="text-xs font-semibold text-white mb-3 uppercase tracking-wider">How To Upload</div>
            <div class="space-y-2 text-sm text-slate-300">
                <div>1. Download the sample sheet and open it in Excel.</div>
                <div>2. Keep the first header row unchanged and fill one product per row.</div>
                <div>3. Save the completed file from Excel as CSV format.</div>
                <div>4. Upload the CSV here. Each valid row will be saved as a draft product.</div>
            </div>
        </div>

        <div class="bg-slate-900/30 border border-slate-800 rounded-2xl p-4 mb-4">
            <div class="text-xs font-semibold text-white mb-3 uppercase tracking-wider">Template Preview</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[760px]">
                    <thead class="text-slate-400 uppercase tracking-wider">
                        <tr>
                            <?php foreach ($templateHeaders as $header): ?>
                            <th class="px-3 py-2 border-b border-slate-800"><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="text-slate-300">
                        <?php foreach ($templateRows as $sampleRow): ?>
                        <tr class="border-b border-slate-900/60">
                            <?php foreach ($sampleRow as $cell): ?>
                            <td class="px-3 py-2"><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="/vendor/products.php?entry=bulk#entryPanel" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="bulk_upload">
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Upload Filled CSV Sheet</label>
                <input type="file" name="bulk_product_file" accept=".csv,text/csv" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                <p class="text-[11px] text-slate-500 mt-2">Supported format: CSV saved from the provided Excel sample sheet.</p>
            </div>
            <div class="flex justify-end">
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">
                    <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i>Upload Bulk Products
                </button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex items-center gap-3 flex-wrap">
    <input type="hidden" name="entry" value="<?= htmlspecialchars($entryMode) ?>">
    <div class="relative flex-1 min-w-[260px]">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
    </div>
    <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
    </select>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
</form>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Product</th>
                <th class="px-5 py-4 font-semibold">Category</th>
                <th class="px-5 py-4 font-semibold">Price</th>
                <th class="px-5 py-4 font-semibold">Stock</th>
                <th class="px-5 py-4 font-semibold">Status</th>
                <th class="px-5 py-4 font-semibold text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($products as $product): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/15 to-cyan-500/15 flex items-center justify-center border border-blue-500/20 shrink-0">
                            <i class="fa-solid fa-microchip text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="text-xs text-emerald-400 font-mono"><?= htmlspecialchars($product['part_number']) ?></div>
                            <?php if ($product['manufacturer_name']): ?>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($product['manufacturer_name']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($product['category_name'] ?: '-') ?></td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="component_id" value="<?= (int)$product['id'] ?>">
                        <span class="text-slate-500 text-xs">INR</span>
                        <input type="number" name="price" value="<?= htmlspecialchars((string)$product['price']) ?>" min="0" step="0.01" class="input-field w-24 px-2 py-1 rounded text-xs text-right" onchange="this.form.submit()">
                    </form>
                </td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="component_id" value="<?= (int)$product['id'] ?>">
                        <input type="number" name="stock" value="<?= htmlspecialchars((string)$product['stock']) ?>" min="0" class="input-field w-20 px-2 py-1 rounded text-xs text-right <?= $product['stock'] <= $product['low_stock_threshold'] ? 'border-red-500/30 text-red-300' : '' ?>" onchange="this.form.submit()">
                        <?php if ($product['stock'] <= $product['low_stock_threshold']): ?>
                        <i class="fa-solid fa-triangle-exclamation text-red-400 text-xs" title="Low stock"></i>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="px-5 py-4"><?= statusBadge($product['status']) ?></td>
                <td class="px-5 py-4 text-right">
                    <?php if ($product['status'] === 'draft'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="component_id" value="<?= (int)$product['id'] ?>">
                        <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                            <i class="fa-solid fa-paper-plane mr-1"></i>Submit
                        </button>
                    </form>
                    <?php elseif ($product['status'] === 'pending_approval'): ?>
                    <span class="text-xs text-amber-400"><i class="fa-solid fa-hourglass-half mr-1"></i>Under Review</span>
                    <?php elseif ($product['status'] === 'active'): ?>
                    <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Live</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">
                    No products yet. Use the Single Product form or Bulk Upload section above to add your first items.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
