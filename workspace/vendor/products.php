<?php
require_once __DIR__ . '/products_page.php';
return;

$pageTitle = 'My Products';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor record
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator to set up your vendor profile.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle component creation
if (<?php
require_once __DIR__ . '/products_page.php';
return;

$pageTitle = 'My Products';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor record
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator to set up your vendor profile.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle component creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO components (part_number, name, description, manufacturer_id, category_id, vendor_id, price, stock, status, datasheet_url, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['part_number']),
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            $_POST['manufacturer_id'] ?: null,
            $_POST['category_id'] ?: null,
            $uid,
            (float)($_POST['price'] ?? 0),
            (int)($_POST['stock'] ?? 0),
            'draft',
            trim($_POST['datasheet_url'] ?? ''),
            $uid
        ]);
        logAudit($pdo, 'vendor_create_component', 'component', $pdo->lastInsertId(), 'Vendor created product');
        $msg = 'Product created as draft. Submit for marketplace review.';
    } elseif ($_POST['action'] === 'submit') {
        $compId = (int)$_POST['component_id'];
        $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND vendor_id = ? AND status = 'draft'")->execute([$compId, $uid]);
        logAudit($pdo, 'vendor_submit_component', 'component', $compId, 'Submitted for marketplace approval');
        $msg = 'Product submitted for marketplace approval!';
    } elseif ($_POST['action'] === 'update_stock') {
        $compId = (int)$_POST['component_id'];
        $newStock = (int)$_POST['stock'];
        $pdo->prepare("UPDATE components SET stock = ? WHERE id = ? AND vendor_id = ?")->execute([$newStock, $compId, $uid]);
        $msg = 'Stock updated.';
    } elseif ($_POST['action'] === 'update_price') {
        $compId = (int)$_POST['component_id'];
        $newPrice = (float)$_POST['price'];
        $pdo->prepare("UPDATE components SET price = ? WHERE id = ? AND vendor_id = ?")->execute([$newPrice, $compId, $uid]);
        $msg = 'Price updated.';
    }
}

// Fetch products
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT c.*, m.name as manufacturer_name, cat.name as category_name 
        FROM components c 
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.vendor_id = ?";
$params = [$uid];
if ($statusFilter) { $sql .= " AND c.status = ?"; $params[] = $statusFilter; }
if ($search) { $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $products = $stmt->fetchAll();

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$totalProducts = count($products);
$activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$draftCount = count(array_filter($products, fn($p) => $p['status'] === 'draft'));
$lowStockCount = count(array_filter($products, fn($p) => $p['stock'] <= $p['low_stock_threshold'] && $p['status'] === 'active'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">My Products</h2>
        <p class="text-sm text-slate-500 mt-1">Manage your component listings on the Electava marketplace.</p>
    </div>
    <button onclick="document.getElementById('prodModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium">
        <i class="fa-solid fa-plus mr-1.5"></i>Add Product
    </button>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
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

<!-- Search -->
<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex items-center gap-3">
    <div class="relative flex-1">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
    </div>
    <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Draft</option>
        <option value="pending_approval" <?= $statusFilter==='pending_approval'?'selected':'' ?>>Pending</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="discontinued" <?= $statusFilter==='discontinued'?'selected':'' ?>>Discontinued</option>
    </select>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
</form>

<!-- Products Table -->
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
            <?php foreach ($products as $p): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/15 to-purple-500/15 flex items-center justify-center border border-blue-500/20 shrink-0">
                            <i class="fa-solid fa-microchip text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-emerald-400 font-mono"><?= htmlspecialchars($p['part_number']) ?></div>
                            <?php if ($p['manufacturer_name']): ?><div class="text-[10px] text-slate-600"><?= htmlspecialchars($p['manufacturer_name']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <span class="text-slate-500 text-xs">₹</span>
                        <input type="number" name="price" value="<?= $p['price'] ?>" step="0.01" class="input-field w-20 px-1.5 py-1 rounded text-xs text-right" onchange="this.form.submit()">
                    </form>
                </td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <input type="number" name="stock" value="<?= $p['stock'] ?>" class="input-field w-16 px-1.5 py-1 rounded text-xs text-right <?= $p['stock'] <= $p['low_stock_threshold'] ? 'border-red-500/30 text-red-400' : '' ?>" onchange="this.form.submit()">
                        <?php if ($p['stock'] <= $p['low_stock_threshold']): ?>
                        <i class="fa-solid fa-triangle-exclamation text-red-400 text-xs" title="Low stock"></i>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="px-5 py-4"><?= statusBadge($p['status']) ?></td>
                <td class="px-5 py-4 text-right">
                    <?php if ($p['status'] === 'draft'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                            <i class="fa-solid fa-paper-plane mr-1"></i>Submit
                        </button>
                    </form>
                    <?php elseif ($p['status'] === 'pending_approval'): ?>
                    <span class="text-xs text-amber-400"><i class="fa-solid fa-hourglass-half mr-1"></i>Under Review</span>
                    <?php elseif ($p['status'] === 'active'): ?>
                    <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Live</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">No products yet. Add your first component listing above.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Product Modal -->
<div id="prodModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50 max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white"><i class="fa-solid fa-boxes-stacked text-blue-400 mr-2"></i>Add Product</h3>
            <button onclick="document.getElementById('prodModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. LM7805CT"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Name *</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. 5V Voltage Regulator"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Manufacturer</label>
                    <select name="manufacturer_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($manufacturers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Category</label>
                    <select name="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Unit Price (₹)</label><input type="number" name="price" step="0.01" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Initial Stock</label><input type="number" name="stock" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Datasheet URL</label><input type="url" name="datasheet_url" placeholder="https://..." class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('prodModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Add Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_METHOD'] === 'POST' && isset(<?php
require_once __DIR__ . '/products_page.php';
return;

$pageTitle = 'My Products';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor record
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator to set up your vendor profile.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle component creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO components (part_number, name, description, manufacturer_id, category_id, vendor_id, price, stock, status, datasheet_url, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['part_number']),
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            $_POST['manufacturer_id'] ?: null,
            $_POST['category_id'] ?: null,
            $uid,
            (float)($_POST['price'] ?? 0),
            (int)($_POST['stock'] ?? 0),
            'draft',
            trim($_POST['datasheet_url'] ?? ''),
            $uid
        ]);
        logAudit($pdo, 'vendor_create_component', 'component', $pdo->lastInsertId(), 'Vendor created product');
        $msg = 'Product created as draft. Submit for marketplace review.';
    } elseif ($_POST['action'] === 'submit') {
        $compId = (int)$_POST['component_id'];
        $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND vendor_id = ? AND status = 'draft'")->execute([$compId, $uid]);
        logAudit($pdo, 'vendor_submit_component', 'component', $compId, 'Submitted for marketplace approval');
        $msg = 'Product submitted for marketplace approval!';
    } elseif ($_POST['action'] === 'update_stock') {
        $compId = (int)$_POST['component_id'];
        $newStock = (int)$_POST['stock'];
        $pdo->prepare("UPDATE components SET stock = ? WHERE id = ? AND vendor_id = ?")->execute([$newStock, $compId, $uid]);
        $msg = 'Stock updated.';
    } elseif ($_POST['action'] === 'update_price') {
        $compId = (int)$_POST['component_id'];
        $newPrice = (float)$_POST['price'];
        $pdo->prepare("UPDATE components SET price = ? WHERE id = ? AND vendor_id = ?")->execute([$newPrice, $compId, $uid]);
        $msg = 'Price updated.';
    }
}

// Fetch products
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT c.*, m.name as manufacturer_name, cat.name as category_name 
        FROM components c 
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.vendor_id = ?";
$params = [$uid];
if ($statusFilter) { $sql .= " AND c.status = ?"; $params[] = $statusFilter; }
if ($search) { $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $products = $stmt->fetchAll();

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$totalProducts = count($products);
$activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$draftCount = count(array_filter($products, fn($p) => $p['status'] === 'draft'));
$lowStockCount = count(array_filter($products, fn($p) => $p['stock'] <= $p['low_stock_threshold'] && $p['status'] === 'active'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">My Products</h2>
        <p class="text-sm text-slate-500 mt-1">Manage your component listings on the Electava marketplace.</p>
    </div>
    <button onclick="document.getElementById('prodModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium">
        <i class="fa-solid fa-plus mr-1.5"></i>Add Product
    </button>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
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

<!-- Search -->
<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex items-center gap-3">
    <div class="relative flex-1">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
    </div>
    <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Draft</option>
        <option value="pending_approval" <?= $statusFilter==='pending_approval'?'selected':'' ?>>Pending</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="discontinued" <?= $statusFilter==='discontinued'?'selected':'' ?>>Discontinued</option>
    </select>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
</form>

<!-- Products Table -->
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
            <?php foreach ($products as $p): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/15 to-purple-500/15 flex items-center justify-center border border-blue-500/20 shrink-0">
                            <i class="fa-solid fa-microchip text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-emerald-400 font-mono"><?= htmlspecialchars($p['part_number']) ?></div>
                            <?php if ($p['manufacturer_name']): ?><div class="text-[10px] text-slate-600"><?= htmlspecialchars($p['manufacturer_name']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <span class="text-slate-500 text-xs">₹</span>
                        <input type="number" name="price" value="<?= $p['price'] ?>" step="0.01" class="input-field w-20 px-1.5 py-1 rounded text-xs text-right" onchange="this.form.submit()">
                    </form>
                </td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <input type="number" name="stock" value="<?= $p['stock'] ?>" class="input-field w-16 px-1.5 py-1 rounded text-xs text-right <?= $p['stock'] <= $p['low_stock_threshold'] ? 'border-red-500/30 text-red-400' : '' ?>" onchange="this.form.submit()">
                        <?php if ($p['stock'] <= $p['low_stock_threshold']): ?>
                        <i class="fa-solid fa-triangle-exclamation text-red-400 text-xs" title="Low stock"></i>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="px-5 py-4"><?= statusBadge($p['status']) ?></td>
                <td class="px-5 py-4 text-right">
                    <?php if ($p['status'] === 'draft'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                            <i class="fa-solid fa-paper-plane mr-1"></i>Submit
                        </button>
                    </form>
                    <?php elseif ($p['status'] === 'pending_approval'): ?>
                    <span class="text-xs text-amber-400"><i class="fa-solid fa-hourglass-half mr-1"></i>Under Review</span>
                    <?php elseif ($p['status'] === 'active'): ?>
                    <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Live</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">No products yet. Add your first component listing above.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Product Modal -->
<div id="prodModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50 max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white"><i class="fa-solid fa-boxes-stacked text-blue-400 mr-2"></i>Add Product</h3>
            <button onclick="document.getElementById('prodModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. LM7805CT"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Name *</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. 5V Voltage Regulator"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Manufacturer</label>
                    <select name="manufacturer_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($manufacturers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Category</label>
                    <select name="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Unit Price (₹)</label><input type="number" name="price" step="0.01" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Initial Stock</label><input type="number" name="stock" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Datasheet URL</label><input type="url" name="datasheet_url" placeholder="https://..." class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('prodModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Add Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO components (part_number, name, description, manufacturer_id, category_id, vendor_id, price, stock, status, datasheet_url, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['part_number']),
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            $_POST['manufacturer_id'] ?: null,
            $_POST['category_id'] ?: null,
            $uid,
            (float)($_POST['price'] ?? 0),
            (int)($_POST['stock'] ?? 0),
            'draft',
            trim($_POST['datasheet_url'] ?? ''),
            $uid
        ]);
        logAudit($pdo, 'vendor_create_component', 'component', $pdo->lastInsertId(), 'Vendor created product');
        $msg = 'Product created as draft. Submit for marketplace review.';
    } elseif ($_POST['action'] === 'submit') {
        $compId = (int)$_POST['component_id'];
        $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND vendor_id = ? AND status = 'draft'")->execute([$compId, $uid]);
        logAudit($pdo, 'vendor_submit_component', 'component', $compId, 'Submitted for marketplace approval');
        $msg = 'Product submitted for marketplace approval!';
    } elseif ($_POST['action'] === 'update_stock') {
        $compId = (int)$_POST['component_id'];
        $newStock = (int)$_POST['stock'];
        $pdo->prepare("UPDATE components SET stock = ? WHERE id = ? AND vendor_id = ?")->execute([$newStock, $compId, $uid]);
        $msg = 'Stock updated.';
    } elseif ($_POST['action'] === 'update_price') {
        $compId = (int)$_POST['component_id'];
        $newPrice = (float)$_POST['price'];
        $pdo->prepare("UPDATE components SET price = ? WHERE id = ? AND vendor_id = ?")->execute([$newPrice, $compId, $uid]);
        $msg = 'Price updated.';
    }
}

// Fetch products
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT c.*, m.name as manufacturer_name, cat.name as category_name 
        FROM components c 
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.vendor_id = ?";
$params = [$uid];
if ($statusFilter) { $sql .= " AND c.status = ?"; $params[] = $statusFilter; }
if ($search) { $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $products = $stmt->fetchAll();

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$totalProducts = count($products);
$activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$draftCount = count(array_filter($products, fn($p) => $p['status'] === 'draft'));
$lowStockCount = count(array_filter($products, fn($p) => $p['stock'] <= $p['low_stock_threshold'] && $p['status'] === 'active'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">My Products</h2>
        <p class="text-sm text-slate-500 mt-1">Manage your component listings on the Electava marketplace.</p>
    </div>
    <button onclick="document.getElementById('prodModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium">
        <i class="fa-solid fa-plus mr-1.5"></i>Add Product
    </button>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
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

<!-- Search -->
<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex items-center gap-3">
    <div class="relative flex-1">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
    </div>
    <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Draft</option>
        <option value="pending_approval" <?= $statusFilter==='pending_approval'?'selected':'' ?>>Pending</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="discontinued" <?= $statusFilter==='discontinued'?'selected':'' ?>>Discontinued</option>
    </select>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
</form>

<!-- Products Table -->
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
            <?php foreach ($products as $p): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/15 to-purple-500/15 flex items-center justify-center border border-blue-500/20 shrink-0">
                            <i class="fa-solid fa-microchip text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-emerald-400 font-mono"><?= htmlspecialchars($p['part_number']) ?></div>
                            <?php if ($p['manufacturer_name']): ?><div class="text-[10px] text-slate-600"><?= htmlspecialchars($p['manufacturer_name']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <span class="text-slate-500 text-xs">₹</span>
                        <input type="number" name="price" value="<?= $p['price'] ?>" step="0.01" class="input-field w-20 px-1.5 py-1 rounded text-xs text-right" onchange="this.form.submit()">
                    </form>
                </td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <input type="number" name="stock" value="<?= $p['stock'] ?>" class="input-field w-16 px-1.5 py-1 rounded text-xs text-right <?= $p['stock'] <= $p['low_stock_threshold'] ? 'border-red-500/30 text-red-400' : '' ?>" onchange="this.form.submit()">
                        <?php if ($p['stock'] <= $p['low_stock_threshold']): ?>
                        <i class="fa-solid fa-triangle-exclamation text-red-400 text-xs" title="Low stock"></i>
                        <?php endif; ?>
                    </form>
                </td>
                <td class="px-5 py-4"><?= statusBadge($p['status']) ?></td>
                <td class="px-5 py-4 text-right">
                    <?php if ($p['status'] === 'draft'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="component_id" value="<?= $p['id'] ?>">
                        <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                            <i class="fa-solid fa-paper-plane mr-1"></i>Submit
                        </button>
                    </form>
                    <?php elseif ($p['status'] === 'pending_approval'): ?>
                    <span class="text-xs text-amber-400"><i class="fa-solid fa-hourglass-half mr-1"></i>Under Review</span>
                    <?php elseif ($p['status'] === 'active'): ?>
                    <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Live</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">No products yet. Add your first component listing above.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Product Modal -->
<div id="prodModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50 max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white"><i class="fa-solid fa-boxes-stacked text-blue-400 mr-2"></i>Add Product</h3>
            <button onclick="document.getElementById('prodModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. LM7805CT"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Name *</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. 5V Voltage Regulator"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Manufacturer</label>
                    <select name="manufacturer_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($manufacturers as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Category</label>
                    <select name="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Unit Price (₹)</label><input type="number" name="price" step="0.01" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Initial Stock</label><input type="number" name="stock" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Datasheet URL</label><input type="url" name="datasheet_url" placeholder="https://..." class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('prodModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Add Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
