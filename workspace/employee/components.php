<?php
$pageTitle = 'Components';
require_once __DIR__ . '/../includes/auth.php';
requireRole('employee');

function ensureComponentSchema(PDO $pdo): void {
    $column = $pdo->query("SHOW COLUMNS FROM components LIKE 'electava_part_number'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE components ADD COLUMN electava_part_number VARCHAR(255) DEFAULT NULL AFTER part_number");
        $pdo->exec("UPDATE components SET electava_part_number = part_number WHERE electava_part_number IS NULL OR electava_part_number = ''");
    }
    $assetLinksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'asset_links'")->fetch();
    if (!$assetLinksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN asset_links LONGTEXT DEFAULT NULL AFTER specifications");
    }
    $quantityBreaksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'quantity_breaks'")->fetch();
    if (!$quantityBreaksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN quantity_breaks LONGTEXT DEFAULT NULL AFTER price");
    }
}

function parseSpecifications(array $keys, array $values): ?string {
    $specs = [];
    $count = max(count($keys), count($values));
    for ($i = 0; $i < $count; $i++) {
        $key = trim((string)($keys[$i] ?? ''));
        $value = trim((string)($values[$i] ?? ''));
        if ($key === '') {
            continue;
        }
        $specs[$key] = $value;
    }
    return empty($specs) ? null : json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function specificationsArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    return is_array($decoded) ? $decoded : [];
}

function assetLinksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }
    return [
        'documents' => is_array($decoded['documents'] ?? null) ? $decoded['documents'] : [],
        'images' => is_array($decoded['images'] ?? null) ? $decoded['images'] : [],
        'cad' => is_array($decoded['cad'] ?? null) ? $decoded['cad'] : [],
    ];
}

function quantityBreaksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $qty = (int)($entry['qty'] ?? $entry['quantity'] ?? 0);
        $price = $entry['price'] ?? null;

        if ($qty < 1 || !is_numeric((string)$price)) {
            continue;
        }

        $normalized[$qty] = [
            'qty' => $qty,
            'price' => round((float)$price, 4),
        ];
    }

    ksort($normalized);
    return array_values($normalized);
}

function parseQuantityBreaks(array $quantities, array $prices): array {
    $tiers = [];
    $count = max(count($quantities), count($prices));

    for ($i = 0; $i < $count; $i++) {
        $qtyRaw = trim((string)($quantities[$i] ?? ''));
        $priceRaw = trim((string)($prices[$i] ?? ''));

        if ($qtyRaw === '' && $priceRaw === '') {
            continue;
        }

        if ($qtyRaw === '' || !ctype_digit($qtyRaw) || (int)$qtyRaw < 1) {
            throw new RuntimeException('Each pricing row needs a valid quantity of 1 or more.');
        }

        if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            throw new RuntimeException('Each pricing row needs a valid price of 0 or more.');
        }

        $qty = (int)$qtyRaw;
        $tiers[$qty] = [
            'qty' => $qty,
            'price' => round((float)$priceRaw, 4),
        ];
    }

    ksort($tiers);
    return array_values($tiers);
}

function parseLinkEntries(array $labels, array $urls): array {
    $entries = [];
    $count = max(count($labels), count($urls));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $url = trim((string)($urls[$i] ?? ''));
        if ($url === '') {
            continue;
        }
        $entries[] = [
            'label' => $label !== '' ? $label : $url,
            'url' => $url,
        ];
    }
    return $entries;
}

function isLocalUploadPath(?string $path): bool {
    return is_string($path) && str_starts_with($path, '/uploads/');
}

function deleteLocalUpload(?string $relativePath): void {
    if (!isLocalUploadPath($relativePath)) {
        return;
    }
    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function storeComponentUpload(PDO $pdo, int $componentId, int $uid, array $config): ?string {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['error'])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $config['label'] . '.');
    }

    $originalName = (string)($file['name'] ?? 'file');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $config['extensions'], true)) {
        throw new RuntimeException('Invalid file type for ' . $config['label'] . '.');
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $componentId . DIRECTORY_SEPARATOR . $config['folder'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeName = trim((string)$safeName, '-');
    if ($safeName === '') {
        $safeName = $config['folder'];
    }

    $storedName = $safeName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save ' . $config['label'] . '.');
    }

    $relativePath = '/uploads/components/' . $componentId . '/' . $config['folder'] . '/' . $storedName;
    $stmt = $pdo->prepare("
        INSERT INTO files (original_name, stored_name, file_path, file_type, file_size, mime_type, related_type, related_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, 'component', ?, ?)
    ");
    $stmt->execute([
        $originalName,
        $storedName,
        $relativePath,
        $config['column'],
        (int)($file['size'] ?? 0),
        $file['type'] ?? null,
        $componentId,
        $uid,
    ]);

    return $relativePath;
}

function normalizeUploadBatch(array $fileInput): array {
    if (!isset($fileInput['name'])) {
        return [];
    }

    if (!is_array($fileInput['name'])) {
        return [$fileInput];
    }

    $files = [];
    foreach ($fileInput['name'] as $index => $name) {
        $files[] = [
            'name' => $fileInput['name'][$index] ?? '',
            'type' => $fileInput['type'][$index] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
            'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$index] ?? 0,
        ];
    }
    return $files;
}

function storeComponentUploads(PDO $pdo, int $componentId, int $uid, array $config): array {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName])) {
        return [];
    }

    $storedPaths = [];
    foreach (normalizeUploadBatch($_FILES[$fieldName]) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['__single_upload__'] = $file;
        $singleConfig = $config;
        $singleConfig['upload_field'] = '__single_upload__';
        $stored = storeComponentUpload($pdo, $componentId, $uid, $singleConfig);
        if ($stored !== null) {
            $storedPaths[] = $stored;
        }
    }
    unset($_FILES['__single_upload__']);

    return $storedPaths;
}

function uploadBucketFromType(string $fileType): string {
    if (str_contains($fileType, 'image')) {
        return 'images';
    }
    if (str_contains($fileType, 'cad') || str_contains($fileType, 'symbol') || str_contains($fileType, 'footprint') || str_contains($fileType, 'step')) {
        return 'cad';
    }
    return 'documents';
}

function loadComponentUploads(PDO $pdo, array $componentIds): array {
    if (!$componentIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, related_id, original_name, file_path, file_type, created_at
        FROM files
        WHERE related_type = 'component' AND related_id IN ($placeholders)
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute($componentIds);

    $grouped = [];
    while ($row = $stmt->fetch()) {
        $bucket = uploadBucketFromType((string)$row['file_type']);
        $grouped[(int)$row['related_id']][$bucket][] = [
            'id' => (int)$row['id'],
            'name' => $row['original_name'],
            'path' => $row['file_path'],
            'file_type' => $row['file_type'],
        ];
    }
    return $grouped;
}

function deleteComponentUploads(PDO $pdo, int $componentId, int $uid, array $fileIds): void {
    $fileIds = array_values(array_filter(array_map('intval', $fileIds)));
    if (!$fileIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
    $params = array_merge([$componentId, $uid], $fileIds);
    $stmt = $pdo->prepare("
        SELECT id, file_path
        FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    if (!$files) {
        return;
    }

    foreach ($files as $file) {
        deleteLocalUpload($file['file_path'] ?? null);
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $deleteStmt->execute($params);
}

function isAjaxRequest(): bool {
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function buildComponentsUrl(array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($value) => $value !== null && $value !== ''));
    return 'components.php' . ($query !== '' ? '?' . $query : '');
}

ensureComponentSchema($pdo);

$uid = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$assetConfig = [
    'datasheet_url' => ['column' => 'datasheet_url', 'upload_field' => 'datasheet_file', 'folder' => 'datasheets', 'label' => 'Document', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'symbol_file' => ['column' => 'symbol_file', 'upload_field' => 'symbol_upload', 'folder' => 'symbols', 'label' => 'CAD Symbol', 'extensions' => ['lib', 'sym', 'schlib', 'zip', 'txt'], 'accept' => '.lib,.sym,.schlib,.zip,.txt'],
    'footprint_file' => ['column' => 'footprint_file', 'upload_field' => 'footprint_upload', 'folder' => 'footprints', 'label' => 'Footprint', 'extensions' => ['kicad_mod', 'mod', 'pretty', 'zip', 'txt'], 'accept' => '.kicad_mod,.mod,.pretty,.zip,.txt'],
    'step_file' => ['column' => 'step_file', 'upload_field' => 'step_upload', 'folder' => 'step', 'label' => '3D CAD', 'extensions' => ['step', 'stp', 'iges', 'igs', 'zip'], 'accept' => '.step,.stp,.iges,.igs,.zip'],
    'image_url' => ['column' => 'image_url', 'upload_field' => 'image_upload', 'folder' => 'images', 'label' => 'Image', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
];

$multiUploadConfig = [
    'documents' => ['column' => 'document_upload', 'upload_field' => 'document_uploads', 'folder' => 'documents-extra', 'label' => 'Documents', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'images' => ['column' => 'image_upload', 'upload_field' => 'image_uploads', 'folder' => 'images-extra', 'label' => 'Images', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
    'cad' => ['column' => 'cad_upload', 'upload_field' => 'cad_uploads', 'folder' => 'cad-extra', 'label' => 'CAD ZIP / 3D Files', 'extensions' => ['zip', 'step', 'stp', 'iges', 'igs', 'lib', 'sym', 'schlib', 'kicad_mod', 'mod', 'pretty'], 'accept' => '.zip,.step,.stp,.iges,.igs,.lib,.sym,.schlib,.kicad_mod,.mod,.pretty'],
];

if (<?php
$pageTitle = 'Components';
require_once __DIR__ . '/../includes/auth.php';
requireRole('employee');

function ensureComponentSchema(PDO $pdo): void {
    $column = $pdo->query("SHOW COLUMNS FROM components LIKE 'electava_part_number'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE components ADD COLUMN electava_part_number VARCHAR(255) DEFAULT NULL AFTER part_number");
        $pdo->exec("UPDATE components SET electava_part_number = part_number WHERE electava_part_number IS NULL OR electava_part_number = ''");
    }
    $assetLinksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'asset_links'")->fetch();
    if (!$assetLinksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN asset_links LONGTEXT DEFAULT NULL AFTER specifications");
    }
    $quantityBreaksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'quantity_breaks'")->fetch();
    if (!$quantityBreaksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN quantity_breaks LONGTEXT DEFAULT NULL AFTER price");
    }
}

function parseSpecifications(array $keys, array $values): ?string {
    $specs = [];
    $count = max(count($keys), count($values));
    for ($i = 0; $i < $count; $i++) {
        $key = trim((string)($keys[$i] ?? ''));
        $value = trim((string)($values[$i] ?? ''));
        if ($key === '') {
            continue;
        }
        $specs[$key] = $value;
    }
    return empty($specs) ? null : json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function specificationsArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    return is_array($decoded) ? $decoded : [];
}

function assetLinksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }
    return [
        'documents' => is_array($decoded['documents'] ?? null) ? $decoded['documents'] : [],
        'images' => is_array($decoded['images'] ?? null) ? $decoded['images'] : [],
        'cad' => is_array($decoded['cad'] ?? null) ? $decoded['cad'] : [],
    ];
}

function quantityBreaksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $qty = (int)($entry['qty'] ?? $entry['quantity'] ?? 0);
        $price = $entry['price'] ?? null;

        if ($qty < 1 || !is_numeric((string)$price)) {
            continue;
        }

        $normalized[$qty] = [
            'qty' => $qty,
            'price' => round((float)$price, 4),
        ];
    }

    ksort($normalized);
    return array_values($normalized);
}

function parseQuantityBreaks(array $quantities, array $prices): array {
    $tiers = [];
    $count = max(count($quantities), count($prices));

    for ($i = 0; $i < $count; $i++) {
        $qtyRaw = trim((string)($quantities[$i] ?? ''));
        $priceRaw = trim((string)($prices[$i] ?? ''));

        if ($qtyRaw === '' && $priceRaw === '') {
            continue;
        }

        if ($qtyRaw === '' || !ctype_digit($qtyRaw) || (int)$qtyRaw < 1) {
            throw new RuntimeException('Each pricing row needs a valid quantity of 1 or more.');
        }

        if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            throw new RuntimeException('Each pricing row needs a valid price of 0 or more.');
        }

        $qty = (int)$qtyRaw;
        $tiers[$qty] = [
            'qty' => $qty,
            'price' => round((float)$priceRaw, 4),
        ];
    }

    ksort($tiers);
    return array_values($tiers);
}

function parseLinkEntries(array $labels, array $urls): array {
    $entries = [];
    $count = max(count($labels), count($urls));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $url = trim((string)($urls[$i] ?? ''));
        if ($url === '') {
            continue;
        }
        $entries[] = [
            'label' => $label !== '' ? $label : $url,
            'url' => $url,
        ];
    }
    return $entries;
}

function isLocalUploadPath(?string $path): bool {
    return is_string($path) && str_starts_with($path, '/uploads/');
}

function deleteLocalUpload(?string $relativePath): void {
    if (!isLocalUploadPath($relativePath)) {
        return;
    }
    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function storeComponentUpload(PDO $pdo, int $componentId, int $uid, array $config): ?string {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['error'])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $config['label'] . '.');
    }

    $originalName = (string)($file['name'] ?? 'file');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $config['extensions'], true)) {
        throw new RuntimeException('Invalid file type for ' . $config['label'] . '.');
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $componentId . DIRECTORY_SEPARATOR . $config['folder'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeName = trim((string)$safeName, '-');
    if ($safeName === '') {
        $safeName = $config['folder'];
    }

    $storedName = $safeName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save ' . $config['label'] . '.');
    }

    $relativePath = '/uploads/components/' . $componentId . '/' . $config['folder'] . '/' . $storedName;
    $stmt = $pdo->prepare("
        INSERT INTO files (original_name, stored_name, file_path, file_type, file_size, mime_type, related_type, related_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, 'component', ?, ?)
    ");
    $stmt->execute([
        $originalName,
        $storedName,
        $relativePath,
        $config['column'],
        (int)($file['size'] ?? 0),
        $file['type'] ?? null,
        $componentId,
        $uid,
    ]);

    return $relativePath;
}

function normalizeUploadBatch(array $fileInput): array {
    if (!isset($fileInput['name'])) {
        return [];
    }

    if (!is_array($fileInput['name'])) {
        return [$fileInput];
    }

    $files = [];
    foreach ($fileInput['name'] as $index => $name) {
        $files[] = [
            'name' => $fileInput['name'][$index] ?? '',
            'type' => $fileInput['type'][$index] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
            'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$index] ?? 0,
        ];
    }
    return $files;
}

function storeComponentUploads(PDO $pdo, int $componentId, int $uid, array $config): array {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName])) {
        return [];
    }

    $storedPaths = [];
    foreach (normalizeUploadBatch($_FILES[$fieldName]) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['__single_upload__'] = $file;
        $singleConfig = $config;
        $singleConfig['upload_field'] = '__single_upload__';
        $stored = storeComponentUpload($pdo, $componentId, $uid, $singleConfig);
        if ($stored !== null) {
            $storedPaths[] = $stored;
        }
    }
    unset($_FILES['__single_upload__']);

    return $storedPaths;
}

function uploadBucketFromType(string $fileType): string {
    if (str_contains($fileType, 'image')) {
        return 'images';
    }
    if (str_contains($fileType, 'cad') || str_contains($fileType, 'symbol') || str_contains($fileType, 'footprint') || str_contains($fileType, 'step')) {
        return 'cad';
    }
    return 'documents';
}

function loadComponentUploads(PDO $pdo, array $componentIds): array {
    if (!$componentIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, related_id, original_name, file_path, file_type, created_at
        FROM files
        WHERE related_type = 'component' AND related_id IN ($placeholders)
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute($componentIds);

    $grouped = [];
    while ($row = $stmt->fetch()) {
        $bucket = uploadBucketFromType((string)$row['file_type']);
        $grouped[(int)$row['related_id']][$bucket][] = [
            'id' => (int)$row['id'],
            'name' => $row['original_name'],
            'path' => $row['file_path'],
            'file_type' => $row['file_type'],
        ];
    }
    return $grouped;
}

function deleteComponentUploads(PDO $pdo, int $componentId, int $uid, array $fileIds): void {
    $fileIds = array_values(array_filter(array_map('intval', $fileIds)));
    if (!$fileIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
    $params = array_merge([$componentId, $uid], $fileIds);
    $stmt = $pdo->prepare("
        SELECT id, file_path
        FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    if (!$files) {
        return;
    }

    foreach ($files as $file) {
        deleteLocalUpload($file['file_path'] ?? null);
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $deleteStmt->execute($params);
}

function isAjaxRequest(): bool {
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function buildComponentsUrl(array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($value) => $value !== null && $value !== ''));
    return 'components.php' . ($query !== '' ? '?' . $query : '');
}

ensureComponentSchema($pdo);

$uid = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$assetConfig = [
    'datasheet_url' => ['column' => 'datasheet_url', 'upload_field' => 'datasheet_file', 'folder' => 'datasheets', 'label' => 'Document', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'symbol_file' => ['column' => 'symbol_file', 'upload_field' => 'symbol_upload', 'folder' => 'symbols', 'label' => 'CAD Symbol', 'extensions' => ['lib', 'sym', 'schlib', 'zip', 'txt'], 'accept' => '.lib,.sym,.schlib,.zip,.txt'],
    'footprint_file' => ['column' => 'footprint_file', 'upload_field' => 'footprint_upload', 'folder' => 'footprints', 'label' => 'Footprint', 'extensions' => ['kicad_mod', 'mod', 'pretty', 'zip', 'txt'], 'accept' => '.kicad_mod,.mod,.pretty,.zip,.txt'],
    'step_file' => ['column' => 'step_file', 'upload_field' => 'step_upload', 'folder' => 'step', 'label' => '3D CAD', 'extensions' => ['step', 'stp', 'iges', 'igs', 'zip'], 'accept' => '.step,.stp,.iges,.igs,.zip'],
    'image_url' => ['column' => 'image_url', 'upload_field' => 'image_upload', 'folder' => 'images', 'label' => 'Image', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
];

$multiUploadConfig = [
    'documents' => ['column' => 'document_upload', 'upload_field' => 'document_uploads', 'folder' => 'documents-extra', 'label' => 'Documents', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'images' => ['column' => 'image_upload', 'upload_field' => 'image_uploads', 'folder' => 'images-extra', 'label' => 'Images', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
    'cad' => ['column' => 'cad_upload', 'upload_field' => 'cad_uploads', 'folder' => 'cad-extra', 'label' => 'CAD ZIP / 3D Files', 'extensions' => ['zip', 'step', 'stp', 'iges', 'igs', 'lib', 'sym', 'schlib', 'kicad_mod', 'mod', 'pretty'], 'accept' => '.zip,.step,.stp,.iges,.igs,.lib,.sym,.schlib,.kicad_mod,.mod,.pretty'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_reference') {
            $referenceType = $_POST['reference_type'] ?? '';
            $referenceName = trim((string)($_POST['reference_name'] ?? ''));

            if ($referenceName === '') {
                throw new RuntimeException('Name is required.');
            }

            if ($referenceType === 'manufacturer') {
                $website = trim((string)($_POST['reference_website'] ?? ''));
                $stmt = $pdo->prepare("SELECT id, name FROM manufacturers WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$referenceName]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO manufacturers (name, website) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $website !== '' ? $website : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_manufacturer', 'manufacturer', $referenceId, 'Created manufacturer: ' . $referenceName);
                }
            } elseif ($referenceType === 'category') {
                $parentId = (int)($_POST['reference_parent_id'] ?? 0);
                $stmt = $pdo->prepare("
                    SELECT id, name
                    FROM categories
                    WHERE LOWER(name) = LOWER(?)
                      AND ((parent_id IS NULL AND ? = 0) OR parent_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$referenceName, $parentId, $parentId]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $parentId > 0 ? $parentId : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_category', 'category', $referenceId, 'Created category: ' . $referenceName);
                }
            } else {
                throw new RuntimeException('Invalid reference type.');
            }

            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'type' => $referenceType,
                    'id' => $referenceId,
                    'name' => $referenceName,
                    'parent_id' => $referenceType === 'category' ? (int)($_POST['reference_parent_id'] ?? 0) : null,
                    'created' => $created,
                    'message' => $created ? ucfirst($referenceType) . ' added successfully.' : ucfirst($referenceType) . ' already exists and is ready to use.',
                ]);
                exit;
            }

            $msg = ucfirst($referenceType) . ' saved successfully.';
        } elseif ($_POST['action'] === 'save_component') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $isEdit = $componentId > 0;
            $existing = null;

            if ($isEdit) {
                $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ? AND created_by = ?");
                $stmt->execute([$componentId, $uid]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Component not found for editing.');
                }
            }

            $partNumber = trim((string)($_POST['part_number'] ?? ''));
            $electavaPartNumber = trim((string)($_POST['electava_part_number'] ?? ''));
            $name = $partNumber;
            $description = trim((string)($_POST['description'] ?? ''));
            $manufacturerId = (int)($_POST['manufacturer_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $quantityBreaks = parseQuantityBreaks($_POST['tier_qty'] ?? [], $_POST['tier_price'] ?? []);
            $specifications = parseSpecifications($_POST['spec_key'] ?? [], $_POST['spec_value'] ?? []);
            $datasheetText = trim((string)($_POST['datasheet_url_text'] ?? ''));
            $assetLinks = [
                'documents' => parseLinkEntries($_POST['document_link_label'] ?? $_POST['documents_link_label'] ?? [], $_POST['document_link_url'] ?? $_POST['documents_link_url'] ?? []),
                'images' => parseLinkEntries($_POST['image_link_label'] ?? $_POST['images_link_label'] ?? [], $_POST['image_link_url'] ?? $_POST['images_link_url'] ?? []),
                'cad' => parseLinkEntries($_POST['cad_link_label'] ?? [], $_POST['cad_link_url'] ?? []),
            ];

            $errors = [];
            if ($partNumber === '') { $errors[] = 'Part Number is mandatory.'; }
            if ($electavaPartNumber === '') { $errors[] = 'Electava Part Number is mandatory.'; }
            if ($manufacturerId <= 0) { $errors[] = 'Manufacturer is mandatory.'; }
            if ($description === '') { $errors[] = 'Description is mandatory.'; }
            if (empty($quantityBreaks)) { $errors[] = 'Add at least one quantity based pricing row.'; }
            foreach ($quantityBreaks as $tier) {
                if ($tier['qty'] === 1) {
                    $price = (float)$tier['price'];
                    break;
                }
            }
            if ($price <= 0 && !empty($quantityBreaks)) {
                $price = (float)$quantityBreaks[0]['price'];
            }
            if ($errors) {
                throw new RuntimeException(implode(' ', $errors));
            }

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE components
                    SET part_number = ?, electava_part_number = ?, name = ?, description = ?, manufacturer_id = ?, category_id = ?, price = ?, quantity_breaks = ?, stock = ?, specifications = ?, asset_links = ?
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $componentId, $uid]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO components (part_number, electava_part_number, name, description, manufacturer_id, category_id, price, quantity_breaks, stock, status, specifications, asset_links, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid]);
                $componentId = (int)$pdo->lastInsertId();
                $existing = ['datasheet_url' => null, 'symbol_file' => null, 'footprint_file' => null, 'step_file' => null, 'image_url' => null, 'asset_links' => null, 'quantity_breaks' => null];
            }

            $currentAssets = [
                'datasheet_url' => $existing['datasheet_url'] ?? null,
                'symbol_file' => $existing['symbol_file'] ?? null,
                'footprint_file' => $existing['footprint_file'] ?? null,
                'step_file' => $existing['step_file'] ?? null,
                'image_url' => $existing['image_url'] ?? null,
            ];

            if ($datasheetText !== '') {
                $currentAssets['datasheet_url'] = $datasheetText;
            } elseif (!$isEdit) {
                $currentAssets['datasheet_url'] = null;
            }

            if (isset($_POST['remove_file_ids']) && is_array($_POST['remove_file_ids'])) {
                deleteComponentUploads($pdo, $componentId, $uid, $_POST['remove_file_ids']);
            }

            foreach ($assetConfig as $column => $config) {
                $removeField = 'remove_' . $column;
                if (isset($_POST[$removeField]) && $_POST[$removeField] === '1') {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = null;
                }

                $uploaded = storeComponentUpload($pdo, $componentId, $uid, $config);
                if ($uploaded !== null) {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = $uploaded;
                }
            }

            $extraUploads = [
                'documents' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['documents']),
                'images' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['images']),
                'cad' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['cad']),
            ];

            if (!empty($assetLinks['documents'])) {
                $currentAssets['datasheet_url'] = $assetLinks['documents'][0]['url'];
            } elseif (!empty($extraUploads['documents']) && empty($currentAssets['datasheet_url'])) {
                $currentAssets['datasheet_url'] = $extraUploads['documents'][0];
            }

            if (!empty($assetLinks['images'])) {
                $currentAssets['image_url'] = $assetLinks['images'][0]['url'];
            } elseif (!empty($extraUploads['images']) && empty($currentAssets['image_url'])) {
                $currentAssets['image_url'] = $extraUploads['images'][0];
            }

            if (!empty($assetLinks['cad'])) {
                $currentAssets['step_file'] = $assetLinks['cad'][0]['url'];
            } elseif (!empty($extraUploads['cad']) && empty($currentAssets['step_file'])) {
                $currentAssets['step_file'] = $extraUploads['cad'][0];
            }

            $stmt = $pdo->prepare("UPDATE components SET datasheet_url = ?, symbol_file = ?, footprint_file = ?, step_file = ?, image_url = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([
                $currentAssets['datasheet_url'],
                $currentAssets['symbol_file'],
                $currentAssets['footprint_file'],
                $currentAssets['step_file'],
                $currentAssets['image_url'],
                $componentId,
                $uid,
            ]);

            logAudit($pdo, $isEdit ? 'update_component' : 'create_component', 'component', $componentId, ($isEdit ? 'Updated' : 'Created') . ': ' . $name);
            $saveIntent = $_POST['save_intent'] ?? 'submit';
            $redirectParams = [
                'flash' => $isEdit ? 'Component updated successfully.' : 'Component saved as a draft.',
                'type' => 'success',
            ];
            if ($saveIntent === 'next') {
                $redirectParams['mode'] = 'create';
                $redirectParams['flash'] = 'Component saved. The form is ready for the next component.';
            } else {
                $redirectParams['mode'] = 'edit';
                $redirectParams['component_id'] = $componentId;
            }

            header('Location: ' . buildComponentsUrl($redirectParams));
            exit;
        } elseif ($_POST['action'] === 'submit_for_approval') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND created_by = ? AND status = 'draft'");
            $stmt->execute([$componentId, $uid]);
            if ($stmt->rowCount() > 0) {
                logAudit($pdo, 'submit_component', 'component', $componentId, 'Submitted for approval');
                $msg = 'Component submitted for approval.';
            } else {
                $msg = 'Only your draft components can be submitted.';
                $msgType = 'error';
            }
            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => $msgType === 'error' ? 'error' : 'success',
            ]));
            exit;
        } elseif ($_POST['action'] === 'bulk_submit_for_approval') {
            $componentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['component_ids'] ?? []))));
            if (!$componentIds) {
                throw new RuntimeException('Select at least one draft component to submit.');
            }

            $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
            $selectStmt = $pdo->prepare("
                SELECT id, name, part_number
                FROM components
                WHERE created_by = ? AND status = 'draft' AND id IN ($placeholders)
            ");
            $selectStmt->execute(array_merge([$uid], $componentIds));
            $draftComponents = $selectStmt->fetchAll();

            if (!$draftComponents) {
                throw new RuntimeException('No draft components were available for bulk submit.');
            }

            $draftIds = array_map(static fn(array $component): int => (int)$component['id'], $draftComponents);
            $draftPlaceholders = implode(',', array_fill(0, count($draftIds), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE components
                SET status = 'pending_approval'
                WHERE created_by = ? AND status = 'draft' AND id IN ($draftPlaceholders)
            ");
            $updateStmt->execute(array_merge([$uid], $draftIds));

            foreach ($draftComponents as $component) {
                logAudit(
                    $pdo,
                    'submit_component',
                    'component',
                    (int)$component['id'],
                    'Submitted for approval: ' . ($component['name'] ?: $component['part_number'])
                );
            }

            $submittedCount = count($draftComponents);
            $msg = $submittedCount === 1
                ? '1 component submitted for approval.'
                : $submittedCount . ' components submitted for approval.';

            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => 'success',
            ]));
            exit;
        }
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }
        $msg = $e->getMessage();
        $msgType = 'error';
    }
}

if (isset($_GET['flash']) && $_GET['flash'] !== '') {
    $msg = (string)$_GET['flash'];
    $msgType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string)($_GET['search'] ?? ''));
$sql = "
    SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
    FROM components c
    LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.created_by = ?
";
$params = [$uid];
if ($statusFilter !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ? OR c.electava_part_number LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll();
$componentUploads = loadComponentUploads($pdo, array_map(fn($component) => (int)$component['id'], $components));
foreach ($components as &$component) {
    $component['specifications_array'] = specificationsArray($component['specifications'] ?? null);
    $component['asset_links_array'] = assetLinksArray($component['asset_links'] ?? null);
    $component['quantity_breaks_array'] = quantityBreaksArray($component['quantity_breaks'] ?? null);
    $component['uploads'] = $componentUploads[(int)$component['id']] ?? ['documents' => [], 'images' => [], 'cad' => []];
}
unset($component);

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$totalComp = count($components);
$draftCount = count(array_filter($components, fn($component) => $component['status'] === 'draft'));
$pendingCount = count(array_filter($components, fn($component) => $component['status'] === 'pending_approval'));
$activeCount = count(array_filter($components, fn($component) => $component['status'] === 'active'));

$pageMode = $_GET['mode'] ?? '';
if (!in_array($pageMode, ['create', 'edit', 'view'], true)) {
    $pageMode = '';
}

$selectedComponentId = (int)($_GET['component_id'] ?? 0);
$formComponent = null;
if (in_array($pageMode, ['edit', 'view'], true) && $selectedComponentId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
        FROM components c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.created_by = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedComponentId, $uid]);
    $formComponent = $stmt->fetch();

    if ($formComponent) {
        $formUploads = loadComponentUploads($pdo, [$selectedComponentId]);
        $formComponent['specifications_array'] = specificationsArray($formComponent['specifications'] ?? null);
        $formComponent['asset_links_array'] = assetLinksArray($formComponent['asset_links'] ?? null);
        $formComponent['quantity_breaks_array'] = quantityBreaksArray($formComponent['quantity_breaks'] ?? null);
        $formComponent['uploads'] = $formUploads[$selectedComponentId] ?? ['documents' => [], 'images' => [], 'cad' => []];
    } else {
        $pageMode = '';
        $msg = 'Component not found.';
        $msgType = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msgType === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?> border px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">Components</h2>
        <p class="text-sm text-slate-500 mt-1">
            <?= $pageMode === '' ? 'Create, view, and edit component listings with mandatory part fields, optional spec rows, and upload areas for docs, CAD, and images.' : 'Complete the full component form on this page, then submit it or save and continue with the next component.' ?>
        </p>
    </div>
    <?php if ($pageMode === ''): ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'create'])) ?>" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium inline-flex items-center">
        <i class="fa-solid fa-plus mr-1.5"></i>New Component
    </a>
    <?php else: ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200 inline-flex items-center">
        <i class="fa-solid fa-table-list mr-1.5"></i>Component List
    </a>
    <?php endif; ?>
</div>

<?php if ($pageMode === ''): ?>
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-white"><?= $totalComp ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Total</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-slate-300"><?= $draftCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Drafts</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-amber-400"><?= $pendingCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-emerald-400"><?= $activeCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Active</div></div>
</div>

<div class="flex items-center gap-3 mb-5">
    <form method="GET" class="flex-1 flex items-center gap-2">
        <div class="relative flex-1">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by part number, Electava part number, or description..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
        </div>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
        </select>
        <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
    </form>
</div>

<div class="glass-card rounded-2xl p-4 border border-slate-700/50 mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Bulk Submit for Approval</h3>
        <p id="bulkSelectionSummary" class="text-xs text-slate-500 mt-1">0 selected from <?= $draftCount ?> draft components.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-slate-300 <?= $draftCount === 0 ? 'opacity-50' : '' ?>">
            <input type="checkbox" id="selectAllDrafts" class="rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40" <?= $draftCount === 0 ? 'disabled' : '' ?>>
            Select All Drafts
        </label>
        <form id="bulkApprovalForm" method="POST">
            <input type="hidden" name="action" value="bulk_submit_for_approval">
            <button type="submit" id="bulkSubmitButton" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed" <?= $draftCount === 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane mr-1.5"></i>Submit Selected
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($components as $component): ?>
    <?php
    $payload = [
        'id' => (int)$component['id'],
        'part_number' => $component['part_number'],
        'electava_part_number' => $component['electava_part_number'] ?: $component['part_number'],
        'name' => $component['name'],
        'description' => $component['description'],
        'manufacturer_id' => $component['manufacturer_id'] ? (int)$component['manufacturer_id'] : '',
        'category_id' => $component['category_id'] ? (int)$component['category_id'] : '',
        'price' => (string)$component['price'],
        'quantity_breaks' => $component['quantity_breaks_array'],
        'stock' => (string)$component['stock'],
        'datasheet_url' => $component['datasheet_url'],
        'symbol_file' => $component['symbol_file'],
        'footprint_file' => $component['footprint_file'],
        'step_file' => $component['step_file'],
        'image_url' => $component['image_url'],
        'specifications' => $component['specifications_array'],
        'asset_links' => $component['asset_links_array'],
        'uploads' => $component['uploads'],
    ];
    ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex items-start gap-3 min-w-0">
                <?php if (!empty($component['image_url'])): ?>
                <img src="<?= htmlspecialchars($component['image_url']) ?>" alt="<?= htmlspecialchars($component['name']) ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-700/70 bg-slate-900/60">
                <?php else: ?>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/15 to-emerald-500/15 flex items-center justify-center border border-blue-500/20 shrink-0"><i class="fa-solid fa-microchip text-blue-400"></i></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate font-mono"><?= htmlspecialchars($component['part_number']) ?></h3>
                    <div class="text-[11px] text-cyan-300 font-mono mt-1">Electava: <?= htmlspecialchars($component['electava_part_number'] ?: $component['part_number']) ?></div>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <?php if ($component['status'] === 'draft'): ?>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300 bg-slate-900/50 border border-slate-700/70 rounded-lg px-2 py-1.5">
                    <input
                        type="checkbox"
                        name="component_ids[]"
                        value="<?= (int)$component['id'] ?>"
                        form="bulkApprovalForm"
                        class="bulk-component-checkbox rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40"
                    >
                    Select
                </label>
                <?php endif; ?>
                <?= statusBadge($component['status']) ?>
            </div>
        </div>
        <p class="text-xs text-slate-400 mb-3 min-h-[38px]"><?= htmlspecialchars($component['description']) ?></p>
        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Mfr:</span> <span class="text-slate-300"><?= htmlspecialchars($component['manufacturer_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Cat:</span> <span class="text-slate-300"><?= htmlspecialchars($component['category_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Price:</span> <span class="text-emerald-400">INR <?= number_format((float)$component['price'], 2) ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Stock:</span> <span class="text-white"><?= number_format((int)$component['stock']) ?></span></div>
        </div>
        <?php if (!empty($component['quantity_breaks_array'])): ?>
        <div class="mb-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-3 py-3">
            <div class="text-[11px] uppercase tracking-widest text-emerald-300 mb-2">Tier Pricing</div>
            <div class="flex flex-wrap gap-2">
                <?php foreach (array_slice($component['quantity_breaks_array'], 0, 4) as $tier): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-200">
                    <?= (int)$tier['qty'] ?> qty - INR <?= number_format((float)$tier['price'], 2) ?>
                </span>
                <?php endforeach; ?>
                <?php if (count($component['quantity_breaks_array']) > 4): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-400">
                    +<?= count($component['quantity_breaks_array']) - 4 ?> more
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach (['datasheet_url' => 'Doc', 'symbol_file' => 'Symbol', 'footprint_file' => 'Footprint', 'step_file' => '3D CAD', 'image_url' => 'Image'] as $column => $label): ?>
                <?php if (!empty($component[$column])): ?><span class="px-2 py-1 rounded-lg text-[11px] bg-cyan-500/10 text-cyan-300 border border-cyan-500/20"><?= $label ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'view', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-eye mr-1"></i>View</a>
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'edit', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</a>
            <?php if ($component['status'] === 'draft'): ?>
            <form method="POST" class="flex-1">
                <input type="hidden" name="action" value="submit_for_approval">
                <input type="hidden" name="component_id" value="<?= (int)$component['id'] ?>">
                <button class="w-full text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-2 rounded-lg hover:bg-emerald-600/40 transition font-medium"><i class="fa-solid fa-paper-plane mr-1"></i>Submit for Approval</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="text-[10px] text-slate-600 mt-3"><?= timeAgo($component['created_at']) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($components)): ?>
    <div class="col-span-full glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4"><i class="fa-solid fa-microchip text-slate-600 text-2xl"></i></div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Components Yet</h3>
        <p class="text-sm text-slate-600">Add your first component with required fields and optional uploads.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($pageMode !== ''): ?>
<div class="glass-card rounded-3xl p-6 lg:p-8 shadow-2xl border border-slate-700/50 mt-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div>
                <h3 id="componentModalTitle" class="text-lg font-semibold text-white"><i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component</h3>
                <p class="text-xs text-slate-500 mt-1">Part Number, Electava Part Number, Manufacturer, and Description are mandatory. Optional rows can be added or deleted.</p>
            </div>
        </div>
        <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">
            <i class="fa-solid fa-arrow-left mr-1.5"></i>Back to List
        </a>
    </div>
    <form id="componentForm" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="action" value="save_component">
            <input type="hidden" name="component_id" id="component_id" value="0">
            <input type="hidden" name="name" id="name" value="">
            <input type="hidden" name="price" id="price" value="0">
            <input type="hidden" name="datasheet_url_text" id="datasheet_url_text" value="">
            <div class="grid lg:grid-cols-2 gap-5">
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-4">Main Details</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" id="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Electava Part Number *</label><input type="text" name="electava_part_number" id="electava_part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Manufacturer *</label>
                                    <button type="button" data-reference-trigger="manufacturer" onclick="openReferenceModal('manufacturer')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Manufacturer
                                    </button>
                                </div>
                                <select name="manufacturer_id" id="manufacturer_id" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Select manufacturer</option>
                                    <?php foreach ($manufacturers as $manufacturer): ?><option value="<?= (int)$manufacturer['id'] ?>"><?= htmlspecialchars($manufacturer['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Stock</label><input type="number" name="stock" id="stock" min="0" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Category</label>
                                    <button type="button" data-reference-trigger="category" onclick="openReferenceModal('category')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Category
                                    </button>
                                </div>
                                <select name="category_id" id="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Optional</option>
                                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-900/35 border border-slate-800 p-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-sm font-medium text-white">Quantity Based Pricing</div>
                                    <div class="text-[11px] text-slate-500 mt-1">Add different prices for 1 qty, 10 qty, 50 qty, or any other quantity break.</div>
                                </div>
                                <button type="button" data-pricing-add onclick="addPricingRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 whitespace-nowrap">
                                    <i class="fa-solid fa-plus mr-1"></i>Add Pricing
                                </button>
                            </div>
                            <div id="pricingRows" class="space-y-3"></div>
                        </div>
                        <div class="mt-4"><label class="block text-xs text-slate-400 mb-1.5">Description *</label><textarea name="description" id="description" rows="4" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-white">Optional Specifications</h4>
                            <button type="button" onclick="addSpecRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Row</button>
                        </div>
                        <div id="specRows" class="space-y-3"></div>
                    </div>
                </section>
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Assets Options</h4>
                                <p class="text-[11px] text-slate-500 mt-1">Add labels with URLs or upload local files for documents, images, and EDA/CAD models.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Documents</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload local documents.</div>
                                    </div>
                                    <button type="button" data-link-add="documents" onclick="addLinkRow('documents', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="documentLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Documents</label>
                                    <input type="file" name="document_uploads[]" id="document_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['documents']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Images</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use an image URL or upload local images.</div>
                                    </div>
                                    <button type="button" data-link-add="images" onclick="addLinkRow('images', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="imageLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Images</label>
                                    <input type="file" name="image_uploads[]" id="image_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['images']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">EDA/CAD Models</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload ZIP / EDA / CAD model files.</div>
                                    </div>
                                    <button type="button" data-link-add="cad" onclick="addLinkRow('cad', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="cadLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local EDA/CAD ZIP Files</label>
                                    <input type="file" name="cad_uploads[]" id="cad_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['cad']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Current Assets</h4>
                                <p class="text-[11px] text-slate-500 mt-1">See current primary assets, uploaded files, and remove options here.</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs">
                            <?php foreach ($assetConfig as $column => $config): ?>
                            <div id="asset-<?= htmlspecialchars($column) ?>" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-slate-300 font-medium">Primary <?= htmlspecialchars($config['label']) ?></div>
                                        <a id="asset-link-<?= htmlspecialchars($column) ?>" href="#" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline"></a>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-red-300"><input type="checkbox" name="remove_<?= htmlspecialchars($column) ?>" id="remove_<?= htmlspecialchars($column) ?>" value="1">Remove</label>
                                </div>
                                <?php if ($column === 'image_url'): ?><img id="asset-preview-image_url" src="" alt="Current image" class="hidden mt-3 w-20 h-20 rounded-xl object-cover border border-slate-700/80"><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <div id="uploadedAssetsPanel" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="text-slate-300 font-medium mb-3">Uploaded Extras</div>
                                <div id="uploadedAssetsList" class="space-y-2"></div>
                            </div>
                            <div id="currentAssetsEmptyState" class="bg-slate-900/35 border border-dashed border-slate-700/70 rounded-xl px-3 py-3 text-slate-500">
                                Current files will appear here after you upload or save assets.
                            </div>
                        </div>
                    </div>
                    <div id="previewPanel" class="hidden glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-3">View Summary</h4>
                        <div id="previewContent" class="text-sm text-slate-300 space-y-2"></div>
                    </div>
                </section>
            </div>
            <div class="flex flex-wrap justify-end gap-3 pt-2 border-t border-slate-800/70">
                <button type="button" id="previewToggleButton" onclick="togglePreview()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200"><i class="fa-solid fa-eye mr-1"></i>View</button>
                <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" id="componentCancelButton" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">Cancel</a>
                <button type="submit" id="componentNextButton" name="save_intent" value="next" class="btn-secondary px-5 py-2 rounded-lg text-sm text-slate-100 font-medium">Next</button>
                <button type="submit" id="componentSubmitButton" name="save_intent" value="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
    </form>
</div>
<?php endif; ?>

<div id="referenceModal" class="hidden fixed inset-0 modal-backdrop z-[60] flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 id="referenceModalTitle" class="text-lg font-semibold text-white">Add Reference</h3>
                <p id="referenceModalSubtitle" class="text-xs text-slate-500 mt-1">Save a reusable option for employee component entries.</p>
            </div>
            <button type="button" onclick="closeReferenceModal()" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="referenceForm" class="space-y-4">
            <input type="hidden" name="action" value="create_reference">
            <input type="hidden" name="reference_type" id="reference_type" value="">
            <div>
                <label id="referenceNameLabel" class="block text-xs text-slate-400 mb-1.5">Name</label>
                <input type="text" name="reference_name" id="reference_name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="">
            </div>
            <div id="referenceWebsiteRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Website</label>
                <input type="url" name="reference_website" id="reference_website" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="https://manufacturer-site.com">
            </div>
            <div id="referenceParentRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Parent Category</label>
                <select name="reference_parent_id" id="reference_parent_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                    <option value="0">No parent</option>
                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="referenceFeedback" class="hidden text-sm rounded-xl px-3 py-2"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeReferenceModal()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button type="submit" id="referenceSubmitButton" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const initialComponentData = <?= json_encode($formComponent ? [
    'id' => (int)$formComponent['id'],
    'part_number' => $formComponent['part_number'],
    'electava_part_number' => $formComponent['electava_part_number'] ?: $formComponent['part_number'],
    'name' => $formComponent['name'],
    'description' => $formComponent['description'],
    'manufacturer_id' => $formComponent['manufacturer_id'] ? (int)$formComponent['manufacturer_id'] : '',
    'category_id' => $formComponent['category_id'] ? (int)$formComponent['category_id'] : '',
    'price' => (string)$formComponent['price'],
    'quantity_breaks' => $formComponent['quantity_breaks_array'],
    'stock' => (string)$formComponent['stock'],
    'datasheet_url' => $formComponent['datasheet_url'],
    'symbol_file' => $formComponent['symbol_file'],
    'footprint_file' => $formComponent['footprint_file'],
    'step_file' => $formComponent['step_file'],
    'image_url' => $formComponent['image_url'],
    'specifications' => $formComponent['specifications_array'],
    'asset_links' => $formComponent['asset_links_array'],
    'uploads' => $formComponent['uploads'],
] : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const pageMode = <?= json_encode($pageMode) ?>;
const specRows = document.getElementById('specRows');
const pricingRows = document.getElementById('pricingRows');
const componentForm = document.getElementById('componentForm');
const previewPanel = document.getElementById('previewPanel');
const previewContent = document.getElementById('previewContent');
const previewToggleButton = document.getElementById('previewToggleButton');
const componentSubmitButton = document.getElementById('componentSubmitButton');
const componentNextButton = document.getElementById('componentNextButton');
const componentModalTitle = document.getElementById('componentModalTitle');
const referenceModal = document.getElementById('referenceModal');
const referenceForm = document.getElementById('referenceForm');
const referenceModalTitle = document.getElementById('referenceModalTitle');
const referenceModalSubtitle = document.getElementById('referenceModalSubtitle');
const referenceNameLabel = document.getElementById('referenceNameLabel');
const referenceNameInput = document.getElementById('reference_name');
const referenceWebsiteRow = document.getElementById('referenceWebsiteRow');
const referenceParentRow = document.getElementById('referenceParentRow');
const referenceFeedback = document.getElementById('referenceFeedback');
const referenceSubmitButton = document.getElementById('referenceSubmitButton');
const uploadedAssetsPanel = document.getElementById('uploadedAssetsPanel');
const uploadedAssetsList = document.getElementById('uploadedAssetsList');
const currentAssetsEmptyState = document.getElementById('currentAssetsEmptyState');
const selectAllDrafts = document.getElementById('selectAllDrafts');
const bulkSubmitButton = document.getElementById('bulkSubmitButton');
const bulkSelectionSummary = document.getElementById('bulkSelectionSummary');
const bulkComponentCheckboxes = Array.from(document.querySelectorAll('.bulk-component-checkbox'));
const uploadInputMap = { documents: 'document_uploads', images: 'image_uploads', cad: 'cad_uploads' };
let currentUploadedAssets = { documents: [], images: [], cad: [] };

function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function createSpecRow(key = '', value = '') {
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[1fr,1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="spec_key[]" value="${escapeHtml(key)}" placeholder="Specification name" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="text" name="spec_value[]" value="${escapeHtml(value)}" placeholder="Value" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}
function addSpecRow(key = '', value = '') { specRows.appendChild(createSpecRow(key, value)); }
function resetSpecRows(specifications = []) {
    if (!specRows) return;
    specRows.innerHTML = '';
    if (!specifications.length) { addSpecRow('', ''); return; }
    specifications.forEach(([key, value]) => addSpecRow(key, value));
}

function createPricingPlaceholder() {
    const empty = document.createElement('div');
    empty.className = 'pricing-empty-state text-xs text-slate-500 border border-dashed border-slate-700/70 rounded-xl px-3 py-3';
    empty.textContent = 'No quantity pricing added yet. Use Add Pricing for 1 qty, 10 qty, 50 qty, or more.';
    return empty;
}

function syncPricingPlaceholder() {
    if (!pricingRows) return;
    const rowCount = pricingRows.querySelectorAll('[data-pricing-row]').length;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (rowCount === 0 && !placeholder) {
        pricingRows.appendChild(createPricingPlaceholder());
    }
    if (rowCount > 0 && placeholder) {
        placeholder.remove();
    }
}

function createPricingRow(qty = '', price = '') {
    const row = document.createElement('div');
    row.dataset.pricingRow = 'true';
    row.className = 'grid grid-cols-[minmax(0,160px),minmax(0,1fr),auto] gap-3 items-center';
    row.innerHTML = `<input type="number" name="tier_qty[]" min="1" step="1" value="${escapeHtml(qty)}" placeholder="Quantity" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="number" name="tier_price[]" min="0" step="0.01" value="${escapeHtml(price)}" placeholder="Price in INR" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        syncPricingPlaceholder();
    });
    return row;
}

function addPricingRow(qty = '', price = '') {
    if (!pricingRows) return;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (placeholder) {
        placeholder.remove();
    }
    pricingRows.appendChild(createPricingRow(qty, price));
}

function resetPricingRows(entries = []) {
    if (!pricingRows) return;
    pricingRows.innerHTML = '';
    if (!entries.length) {
        syncPricingPlaceholder();
        return;
    }
    entries.forEach((entry) => addPricingRow(entry.qty || entry.quantity || '', entry.price ?? ''));
    syncPricingPlaceholder();
}

function createLinkRow(group, label = '', url = '') {
    const fieldMap = { documents: 'document', images: 'image', cad: 'cad' };
    const fieldPrefix = fieldMap[group] || group;
    const labelPlaceholders = { documents: 'Document label', images: 'Image label', cad: 'EDA/CAD label' };
    const urlPlaceholders = { documents: 'Document URL', images: 'Image URL', cad: 'EDA/CAD URL' };
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[minmax(0,180px),1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="${fieldPrefix}_link_label[]" value="${escapeHtml(label)}" placeholder="${escapeHtml(labelPlaceholders[group] || 'Label')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="url" name="${fieldPrefix}_link_url[]" value="${escapeHtml(url)}" placeholder="${escapeHtml(urlPlaceholders[group] || 'URL')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}

function addLinkRow(group, label = '', url = '') {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    document.getElementById(map[group]).appendChild(createLinkRow(group, label, url));
}

function resetLinkRows(group, entries = []) {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    const container = document.getElementById(map[group]);
    if (!container) return;
    container.innerHTML = '';
    if (!entries.length) {
        addLinkRow(group, '', '');
        return;
    }
    entries.forEach((entry) => addLinkRow(group, entry.label || '', entry.url || ''));
}

function clearUploads() {
    ['document_uploads', 'image_uploads', 'cad_uploads'].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });
}

function formatFileSize(bytes = 0) {
    const size = Number(bytes) || 0;
    if (size <= 0) return '';
    if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${size} bytes`;
}

function getSelectedUploadEntries() {
    return Object.entries(uploadInputMap).reduce((entries, [group, inputId]) => {
        const input = document.getElementById(inputId);
        const files = input?.files ? Array.from(input.files) : [];
        entries[group] = files.map((file, index) => ({
            id: `pending-${group}-${index}-${file.name}`,
            name: file.name,
            pending: true,
            note: formatFileSize(file.size),
        }));
        return entries;
    }, { documents: [], images: [], cad: [] });
}

function refreshCurrentAssetsState() {
    const primaryVisible = ['datasheet_url', 'symbol_file', 'footprint_file', 'step_file', 'image_url']
        .map((column) => document.getElementById(`asset-${column}`))
        .filter((block) => block && !block.classList.contains('hidden')).length;
    const uploadedVisible = uploadedAssetsList ? uploadedAssetsList.children.length : 0;

    if (uploadedAssetsPanel) {
        uploadedAssetsPanel.classList.toggle('hidden', uploadedVisible === 0);
    }

    if (currentAssetsEmptyState) {
        currentAssetsEmptyState.classList.toggle('hidden', primaryVisible > 0 || uploadedVisible > 0);
    }
}

function resetAssetBlock(column) {
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    const checkbox = document.getElementById(`remove_${column}`);
    if (!block || !link || !checkbox) return;
    block.classList.add('hidden'); link.textContent = ''; link.href = '#'; checkbox.checked = false;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.classList.add('hidden'); img.src = ''; }
    refreshCurrentAssetsState();
}

function setAssetBlock(column, value) {
    resetAssetBlock(column);
    if (!value) return;
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    if (!block || !link) return;
    block.classList.remove('hidden'); link.textContent = value; link.href = value;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.src = value; img.classList.remove('hidden'); }
    refreshCurrentAssetsState();
}

function setFormDisabled(disabled) {
    if (!componentForm) return;
    componentForm.querySelectorAll('input, textarea, select').forEach((field) => { if (field.type !== 'hidden') field.disabled = disabled; });
    if (specRows) {
        specRows.querySelectorAll('button').forEach((button) => { button.disabled = disabled; button.classList.toggle('opacity-50', disabled); });
    }
    if (pricingRows) {
        pricingRows.querySelectorAll('button, input').forEach((control) => {
            control.disabled = disabled;
            control.classList.toggle('opacity-50', disabled && control.tagName === 'BUTTON');
        });
    }
    document.querySelectorAll('[data-reference-trigger]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-pricing-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-link-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('#documentLinkRows button, #imageLinkRows button, #cadLinkRows button, #uploadedAssetsList input[type="checkbox"]').forEach((control) => {
        control.disabled = disabled;
    });
}

function renderUploadedAssets(uploads = {}) {
    if (!uploadedAssetsList || !uploadedAssetsPanel) return;
    currentUploadedAssets = {
        documents: Array.isArray(uploads.documents) ? uploads.documents : [],
        images: Array.isArray(uploads.images) ? uploads.images : [],
        cad: Array.isArray(uploads.cad) ? uploads.cad : [],
    };
    uploadedAssetsList.innerHTML = '';
    const groupLabels = { documents: 'Documents', images: 'Images', cad: 'EDA/CAD' };
    const selectedUploads = getSelectedUploadEntries();
    ['documents', 'images', 'cad'].forEach((group) => {
        currentUploadedAssets[group].forEach((file) => {
            const item = document.createElement('label');
            item.className = 'flex items-center justify-between gap-3 bg-slate-950/40 border border-slate-800 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-200 truncate">${escapeHtml(file.name)}</div></div><a href="${escapeHtml(file.path)}" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline">${escapeHtml(file.path)}</a></div><span class="inline-flex items-center gap-2 text-red-300 shrink-0"><input type="checkbox" name="remove_file_ids[]" value="${escapeHtml(file.id)}">Remove</span>`;
            uploadedAssetsList.appendChild(item);
        });
        selectedUploads[group].forEach((file) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-cyan-500/10 border border-cyan-500/20 text-cyan-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-100 truncate">${escapeHtml(file.name)}</div></div><div class="text-cyan-200 break-all">Selected now${file.note ? ` · ${escapeHtml(file.note)}` : ''}</div></div><span class="text-[11px] text-cyan-300 shrink-0">Ready to upload</span>`;
            uploadedAssetsList.appendChild(item);
        });
    });
    refreshCurrentAssetsState();
}

function buildPreview() {
    if (!componentForm || !previewContent) return;
    const manufacturer = document.getElementById('manufacturer_id').selectedOptions[0]?.text || '-';
    const category = document.getElementById('category_id').selectedOptions[0]?.text || '-';
    const specEntries = Array.from(document.querySelectorAll('input[name="spec_key[]"]')).map((input, index) => [input.value.trim(), document.querySelectorAll('input[name="spec_value[]"]')[index].value.trim()]).filter(([key, value]) => key || value);
    const pricingEntries = Array.from(document.querySelectorAll('input[name="tier_qty[]"]')).map((input, index) => ({
        qty: input.value.trim(),
        price: document.querySelectorAll('input[name="tier_price[]"]')[index].value.trim(),
    })).filter((entry) => entry.qty !== '' || entry.price !== '');
    const docLinks = Array.from(document.querySelectorAll('input[name="document_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const imageLinks = Array.from(document.querySelectorAll('input[name="image_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const cadLinks = Array.from(document.querySelectorAll('input[name="cad_link_url[]"]')).filter((input) => input.value.trim() !== '');
    previewContent.innerHTML = `<div><span class="text-slate-500">Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('part_number').value || '-')}</span></div><div><span class="text-slate-500">Electava Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('electava_part_number').value || '-')}</span></div><div><span class="text-slate-500">Manufacturer:</span> <span class="text-white">${escapeHtml(manufacturer)}</span></div><div><span class="text-slate-500">Category:</span> <span class="text-white">${escapeHtml(category === 'Optional' ? '-' : category)}</span></div><div><span class="text-slate-500">Quantity Pricing:</span> <div class="mt-1">${pricingEntries.length ? pricingEntries.map((entry) => `<div class="text-white">${escapeHtml(entry.qty || '-')} qty: INR ${escapeHtml(entry.price || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div><div><span class="text-slate-500">Description:</span> <span class="text-white">${escapeHtml(document.getElementById('description').value || '-')}</span></div><div><span class="text-slate-500">Asset Entries:</span> <span class="text-white">Docs ${docLinks.length}, Images ${imageLinks.length}, EDA/CAD ${cadLinks.length}</span></div><div><span class="text-slate-500">Uploads Selected:</span> <span class="text-white">Docs ${document.getElementById('document_uploads').files.length}, Images ${document.getElementById('image_uploads').files.length}, EDA/CAD ${document.getElementById('cad_uploads').files.length}</span></div><div><span class="text-slate-500">Specifications:</span> <div class="mt-1">${specEntries.length ? specEntries.map(([key, value]) => `<div class="text-white">${escapeHtml(key)}: ${escapeHtml(value || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div>`;
}

function togglePreview(forceOpen = null) {
    if (!previewPanel || !previewToggleButton) return;
    const open = forceOpen === null ? previewPanel.classList.contains('hidden') : forceOpen;
    if (open) { buildPreview(); previewPanel.classList.remove('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye-slash mr-1"></i>Hide View'; }
    else { previewPanel.classList.add('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye mr-1"></i>View'; }
}

function populateComponentForm(mode = 'create', component = null) {
    if (!componentForm) return;

    componentForm.reset();
    clearUploads();
    resetSpecRows([]);
    resetPricingRows([]);
    resetLinkRows('documents', []);
    resetLinkRows('images', []);
    resetLinkRows('cad', []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach(resetAssetBlock);
    renderUploadedAssets({});
    togglePreview(false);

    document.getElementById('component_id').value = component?.id || 0;
    document.getElementById('part_number').value = component?.part_number || '';
    document.getElementById('electava_part_number').value = component?.electava_part_number || '';
    document.getElementById('name').value = component?.part_number || component?.name || '';
    document.getElementById('description').value = component?.description || '';
    document.getElementById('manufacturer_id').value = component?.manufacturer_id || '';
    document.getElementById('category_id').value = component?.category_id || '';
    document.getElementById('price').value = component?.price || '0';
    resetPricingRows(component?.quantity_breaks?.length ? component.quantity_breaks : (component?.price ? [{ qty: 1, price: component.price }] : []));
    document.getElementById('stock').value = component?.stock || '0';
    document.getElementById('datasheet_url_text').value = component?.datasheet_url && !String(component.datasheet_url).startsWith('/uploads/') ? component.datasheet_url : '';
    resetSpecRows(component?.specifications ? Object.entries(component.specifications) : []);
    resetLinkRows('documents', component?.asset_links?.documents || []);
    resetLinkRows('images', component?.asset_links?.images || []);
    resetLinkRows('cad', component?.asset_links?.cad || []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach((column) => setAssetBlock(column, component?.[column] || ''));
    renderUploadedAssets(component?.uploads || {});

    const viewOnly = mode === 'view';
    setFormDisabled(viewOnly);
    if (componentSubmitButton) componentSubmitButton.classList.toggle('hidden', viewOnly);
    if (componentNextButton) componentNextButton.classList.toggle('hidden', viewOnly);
    if (previewToggleButton) previewToggleButton.classList.remove('hidden');
    if (componentModalTitle) {
        componentModalTitle.innerHTML = viewOnly
            ? '<i class="fa-solid fa-eye text-blue-400 mr-2"></i>View Component'
            : mode === 'edit'
                ? '<i class="fa-solid fa-pen-to-square text-blue-400 mr-2"></i>Edit Component'
                : '<i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component';
    }
    if (viewOnly) {
        togglePreview(true);
    }
}

if (componentForm) {
    populateComponentForm(pageMode || 'create', initialComponentData);
    componentForm.addEventListener('input', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    componentForm.addEventListener('change', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    Object.values(uploadInputMap).forEach((inputId) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', () => {
            renderUploadedAssets(currentUploadedAssets);
        });
    });
}

function updateBulkSelectionState() {
    const total = bulkComponentCheckboxes.length;
    const selected = bulkComponentCheckboxes.filter((checkbox) => checkbox.checked).length;

    if (bulkSelectionSummary) {
        bulkSelectionSummary.textContent = `${selected} selected from ${total} draft components.`;
    }
    if (bulkSubmitButton) {
        bulkSubmitButton.disabled = selected === 0;
    }
    if (selectAllDrafts) {
        selectAllDrafts.checked = total > 0 && selected === total;
        selectAllDrafts.indeterminate = selected > 0 && selected < total;
    }
}

if (selectAllDrafts) {
    selectAllDrafts.addEventListener('change', () => {
        bulkComponentCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllDrafts.checked;
        });
        updateBulkSelectionState();
    });
}

bulkComponentCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', updateBulkSelectionState);
});

updateBulkSelectionState();

function showReferenceFeedback(message, isError = false) {
    referenceFeedback.textContent = message;
    referenceFeedback.className = `${isError ? 'bg-red-500/10 border border-red-500/20 text-red-300' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300'} text-sm rounded-xl px-3 py-2`;
    referenceFeedback.classList.remove('hidden');
}
function closeReferenceModal() {
    referenceModal.classList.add('hidden');
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
}
function openReferenceModal(type) {
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
    document.getElementById('reference_type').value = type;
    if (type === 'manufacturer') {
        referenceModalTitle.textContent = 'Add Manufacturer';
        referenceModalSubtitle.textContent = 'Save a manufacturer name so employees can reuse it in the dropdown.';
        referenceNameLabel.textContent = 'Manufacturer Name';
        referenceNameInput.placeholder = 'e.g. Infineon';
        referenceWebsiteRow.classList.remove('hidden');
        referenceParentRow.classList.add('hidden');
    } else {
        referenceModalTitle.textContent = 'Add Category';
        referenceModalSubtitle.textContent = 'Save a category so it appears in the employee dropdown next time too.';
        referenceNameLabel.textContent = 'Category Name';
        referenceNameInput.placeholder = 'e.g. Sensors';
        referenceWebsiteRow.classList.add('hidden');
        referenceParentRow.classList.remove('hidden');
    }
    referenceModal.classList.remove('hidden');
    referenceNameInput.focus();
}
function upsertSelectOption(selectEl, optionValue, optionLabel) {
    let option = Array.from(selectEl.options).find((item) => item.value === String(optionValue));
    if (!option) {
        option = document.createElement('option');
        option.value = String(optionValue);
        option.textContent = optionLabel;
        selectEl.appendChild(option);
    }
    option.selected = true;
}
referenceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    referenceFeedback.classList.add('hidden');
    referenceSubmitButton.disabled = true;
    try {
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(referenceForm),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Unable to save reference.');
        }

        if (payload.type === 'manufacturer') {
            upsertSelectOption(document.getElementById('manufacturer_id'), payload.id, payload.name);
        } else if (payload.type === 'category') {
            upsertSelectOption(document.getElementById('category_id'), payload.id, payload.name);
            upsertSelectOption(document.getElementById('reference_parent_id'), payload.id, payload.name);
        }

        showReferenceFeedback(payload.message || 'Saved successfully.');
        setTimeout(() => closeReferenceModal(), 600);
    } catch (error) {
        showReferenceFeedback(error.message || 'Unable to save reference.', true);
    } finally {
        referenceSubmitButton.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_METHOD'] === 'POST' && isset(<?php
$pageTitle = 'Components';
require_once __DIR__ . '/../includes/auth.php';
requireRole('employee');

function ensureComponentSchema(PDO $pdo): void {
    $column = $pdo->query("SHOW COLUMNS FROM components LIKE 'electava_part_number'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE components ADD COLUMN electava_part_number VARCHAR(255) DEFAULT NULL AFTER part_number");
        $pdo->exec("UPDATE components SET electava_part_number = part_number WHERE electava_part_number IS NULL OR electava_part_number = ''");
    }
    $assetLinksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'asset_links'")->fetch();
    if (!$assetLinksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN asset_links LONGTEXT DEFAULT NULL AFTER specifications");
    }
    $quantityBreaksColumn = $pdo->query("SHOW COLUMNS FROM components LIKE 'quantity_breaks'")->fetch();
    if (!$quantityBreaksColumn) {
        $pdo->exec("ALTER TABLE components ADD COLUMN quantity_breaks LONGTEXT DEFAULT NULL AFTER price");
    }
}

function parseSpecifications(array $keys, array $values): ?string {
    $specs = [];
    $count = max(count($keys), count($values));
    for ($i = 0; $i < $count; $i++) {
        $key = trim((string)($keys[$i] ?? ''));
        $value = trim((string)($values[$i] ?? ''));
        if ($key === '') {
            continue;
        }
        $specs[$key] = $value;
    }
    return empty($specs) ? null : json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function specificationsArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    return is_array($decoded) ? $decoded : [];
}

function assetLinksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }
    return [
        'documents' => is_array($decoded['documents'] ?? null) ? $decoded['documents'] : [],
        'images' => is_array($decoded['images'] ?? null) ? $decoded['images'] : [],
        'cad' => is_array($decoded['cad'] ?? null) ? $decoded['cad'] : [],
    ];
}

function quantityBreaksArray(?string $json): array {
    $decoded = $json ? json_decode($json, true) : [];
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $qty = (int)($entry['qty'] ?? $entry['quantity'] ?? 0);
        $price = $entry['price'] ?? null;

        if ($qty < 1 || !is_numeric((string)$price)) {
            continue;
        }

        $normalized[$qty] = [
            'qty' => $qty,
            'price' => round((float)$price, 4),
        ];
    }

    ksort($normalized);
    return array_values($normalized);
}

function parseQuantityBreaks(array $quantities, array $prices): array {
    $tiers = [];
    $count = max(count($quantities), count($prices));

    for ($i = 0; $i < $count; $i++) {
        $qtyRaw = trim((string)($quantities[$i] ?? ''));
        $priceRaw = trim((string)($prices[$i] ?? ''));

        if ($qtyRaw === '' && $priceRaw === '') {
            continue;
        }

        if ($qtyRaw === '' || !ctype_digit($qtyRaw) || (int)$qtyRaw < 1) {
            throw new RuntimeException('Each pricing row needs a valid quantity of 1 or more.');
        }

        if ($priceRaw === '' || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            throw new RuntimeException('Each pricing row needs a valid price of 0 or more.');
        }

        $qty = (int)$qtyRaw;
        $tiers[$qty] = [
            'qty' => $qty,
            'price' => round((float)$priceRaw, 4),
        ];
    }

    ksort($tiers);
    return array_values($tiers);
}

function parseLinkEntries(array $labels, array $urls): array {
    $entries = [];
    $count = max(count($labels), count($urls));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $url = trim((string)($urls[$i] ?? ''));
        if ($url === '') {
            continue;
        }
        $entries[] = [
            'label' => $label !== '' ? $label : $url,
            'url' => $url,
        ];
    }
    return $entries;
}

function isLocalUploadPath(?string $path): bool {
    return is_string($path) && str_starts_with($path, '/uploads/');
}

function deleteLocalUpload(?string $relativePath): void {
    if (!isLocalUploadPath($relativePath)) {
        return;
    }
    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function storeComponentUpload(PDO $pdo, int $componentId, int $uid, array $config): ?string {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['error'])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $config['label'] . '.');
    }

    $originalName = (string)($file['name'] ?? 'file');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $config['extensions'], true)) {
        throw new RuntimeException('Invalid file type for ' . $config['label'] . '.');
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $componentId . DIRECTORY_SEPARATOR . $config['folder'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeName = trim((string)$safeName, '-');
    if ($safeName === '') {
        $safeName = $config['folder'];
    }

    $storedName = $safeName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save ' . $config['label'] . '.');
    }

    $relativePath = '/uploads/components/' . $componentId . '/' . $config['folder'] . '/' . $storedName;
    $stmt = $pdo->prepare("
        INSERT INTO files (original_name, stored_name, file_path, file_type, file_size, mime_type, related_type, related_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, 'component', ?, ?)
    ");
    $stmt->execute([
        $originalName,
        $storedName,
        $relativePath,
        $config['column'],
        (int)($file['size'] ?? 0),
        $file['type'] ?? null,
        $componentId,
        $uid,
    ]);

    return $relativePath;
}

function normalizeUploadBatch(array $fileInput): array {
    if (!isset($fileInput['name'])) {
        return [];
    }

    if (!is_array($fileInput['name'])) {
        return [$fileInput];
    }

    $files = [];
    foreach ($fileInput['name'] as $index => $name) {
        $files[] = [
            'name' => $fileInput['name'][$index] ?? '',
            'type' => $fileInput['type'][$index] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
            'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$index] ?? 0,
        ];
    }
    return $files;
}

function storeComponentUploads(PDO $pdo, int $componentId, int $uid, array $config): array {
    $fieldName = $config['upload_field'];
    if (empty($_FILES[$fieldName])) {
        return [];
    }

    $storedPaths = [];
    foreach (normalizeUploadBatch($_FILES[$fieldName]) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['__single_upload__'] = $file;
        $singleConfig = $config;
        $singleConfig['upload_field'] = '__single_upload__';
        $stored = storeComponentUpload($pdo, $componentId, $uid, $singleConfig);
        if ($stored !== null) {
            $storedPaths[] = $stored;
        }
    }
    unset($_FILES['__single_upload__']);

    return $storedPaths;
}

function uploadBucketFromType(string $fileType): string {
    if (str_contains($fileType, 'image')) {
        return 'images';
    }
    if (str_contains($fileType, 'cad') || str_contains($fileType, 'symbol') || str_contains($fileType, 'footprint') || str_contains($fileType, 'step')) {
        return 'cad';
    }
    return 'documents';
}

function loadComponentUploads(PDO $pdo, array $componentIds): array {
    if (!$componentIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, related_id, original_name, file_path, file_type, created_at
        FROM files
        WHERE related_type = 'component' AND related_id IN ($placeholders)
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute($componentIds);

    $grouped = [];
    while ($row = $stmt->fetch()) {
        $bucket = uploadBucketFromType((string)$row['file_type']);
        $grouped[(int)$row['related_id']][$bucket][] = [
            'id' => (int)$row['id'],
            'name' => $row['original_name'],
            'path' => $row['file_path'],
            'file_type' => $row['file_type'],
        ];
    }
    return $grouped;
}

function deleteComponentUploads(PDO $pdo, int $componentId, int $uid, array $fileIds): void {
    $fileIds = array_values(array_filter(array_map('intval', $fileIds)));
    if (!$fileIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
    $params = array_merge([$componentId, $uid], $fileIds);
    $stmt = $pdo->prepare("
        SELECT id, file_path
        FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    if (!$files) {
        return;
    }

    foreach ($files as $file) {
        deleteLocalUpload($file['file_path'] ?? null);
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM files
        WHERE related_type = 'component' AND related_id = ? AND uploaded_by = ? AND id IN ($placeholders)
    ");
    $deleteStmt->execute($params);
}

function isAjaxRequest(): bool {
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function buildComponentsUrl(array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($value) => $value !== null && $value !== ''));
    return 'components.php' . ($query !== '' ? '?' . $query : '');
}

ensureComponentSchema($pdo);

$uid = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$assetConfig = [
    'datasheet_url' => ['column' => 'datasheet_url', 'upload_field' => 'datasheet_file', 'folder' => 'datasheets', 'label' => 'Document', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'symbol_file' => ['column' => 'symbol_file', 'upload_field' => 'symbol_upload', 'folder' => 'symbols', 'label' => 'CAD Symbol', 'extensions' => ['lib', 'sym', 'schlib', 'zip', 'txt'], 'accept' => '.lib,.sym,.schlib,.zip,.txt'],
    'footprint_file' => ['column' => 'footprint_file', 'upload_field' => 'footprint_upload', 'folder' => 'footprints', 'label' => 'Footprint', 'extensions' => ['kicad_mod', 'mod', 'pretty', 'zip', 'txt'], 'accept' => '.kicad_mod,.mod,.pretty,.zip,.txt'],
    'step_file' => ['column' => 'step_file', 'upload_field' => 'step_upload', 'folder' => 'step', 'label' => '3D CAD', 'extensions' => ['step', 'stp', 'iges', 'igs', 'zip'], 'accept' => '.step,.stp,.iges,.igs,.zip'],
    'image_url' => ['column' => 'image_url', 'upload_field' => 'image_upload', 'folder' => 'images', 'label' => 'Image', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
];

$multiUploadConfig = [
    'documents' => ['column' => 'document_upload', 'upload_field' => 'document_uploads', 'folder' => 'documents-extra', 'label' => 'Documents', 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt'],
    'images' => ['column' => 'image_upload', 'upload_field' => 'image_uploads', 'folder' => 'images-extra', 'label' => 'Images', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'accept' => '.jpg,.jpeg,.png,.gif,.webp,.svg'],
    'cad' => ['column' => 'cad_upload', 'upload_field' => 'cad_uploads', 'folder' => 'cad-extra', 'label' => 'CAD ZIP / 3D Files', 'extensions' => ['zip', 'step', 'stp', 'iges', 'igs', 'lib', 'sym', 'schlib', 'kicad_mod', 'mod', 'pretty'], 'accept' => '.zip,.step,.stp,.iges,.igs,.lib,.sym,.schlib,.kicad_mod,.mod,.pretty'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_reference') {
            $referenceType = $_POST['reference_type'] ?? '';
            $referenceName = trim((string)($_POST['reference_name'] ?? ''));

            if ($referenceName === '') {
                throw new RuntimeException('Name is required.');
            }

            if ($referenceType === 'manufacturer') {
                $website = trim((string)($_POST['reference_website'] ?? ''));
                $stmt = $pdo->prepare("SELECT id, name FROM manufacturers WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$referenceName]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO manufacturers (name, website) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $website !== '' ? $website : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_manufacturer', 'manufacturer', $referenceId, 'Created manufacturer: ' . $referenceName);
                }
            } elseif ($referenceType === 'category') {
                $parentId = (int)($_POST['reference_parent_id'] ?? 0);
                $stmt = $pdo->prepare("
                    SELECT id, name
                    FROM categories
                    WHERE LOWER(name) = LOWER(?)
                      AND ((parent_id IS NULL AND ? = 0) OR parent_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$referenceName, $parentId, $parentId]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $parentId > 0 ? $parentId : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_category', 'category', $referenceId, 'Created category: ' . $referenceName);
                }
            } else {
                throw new RuntimeException('Invalid reference type.');
            }

            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'type' => $referenceType,
                    'id' => $referenceId,
                    'name' => $referenceName,
                    'parent_id' => $referenceType === 'category' ? (int)($_POST['reference_parent_id'] ?? 0) : null,
                    'created' => $created,
                    'message' => $created ? ucfirst($referenceType) . ' added successfully.' : ucfirst($referenceType) . ' already exists and is ready to use.',
                ]);
                exit;
            }

            $msg = ucfirst($referenceType) . ' saved successfully.';
        } elseif ($_POST['action'] === 'save_component') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $isEdit = $componentId > 0;
            $existing = null;

            if ($isEdit) {
                $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ? AND created_by = ?");
                $stmt->execute([$componentId, $uid]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Component not found for editing.');
                }
            }

            $partNumber = trim((string)($_POST['part_number'] ?? ''));
            $electavaPartNumber = trim((string)($_POST['electava_part_number'] ?? ''));
            $name = $partNumber;
            $description = trim((string)($_POST['description'] ?? ''));
            $manufacturerId = (int)($_POST['manufacturer_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $quantityBreaks = parseQuantityBreaks($_POST['tier_qty'] ?? [], $_POST['tier_price'] ?? []);
            $specifications = parseSpecifications($_POST['spec_key'] ?? [], $_POST['spec_value'] ?? []);
            $datasheetText = trim((string)($_POST['datasheet_url_text'] ?? ''));
            $assetLinks = [
                'documents' => parseLinkEntries($_POST['document_link_label'] ?? $_POST['documents_link_label'] ?? [], $_POST['document_link_url'] ?? $_POST['documents_link_url'] ?? []),
                'images' => parseLinkEntries($_POST['image_link_label'] ?? $_POST['images_link_label'] ?? [], $_POST['image_link_url'] ?? $_POST['images_link_url'] ?? []),
                'cad' => parseLinkEntries($_POST['cad_link_label'] ?? [], $_POST['cad_link_url'] ?? []),
            ];

            $errors = [];
            if ($partNumber === '') { $errors[] = 'Part Number is mandatory.'; }
            if ($electavaPartNumber === '') { $errors[] = 'Electava Part Number is mandatory.'; }
            if ($manufacturerId <= 0) { $errors[] = 'Manufacturer is mandatory.'; }
            if ($description === '') { $errors[] = 'Description is mandatory.'; }
            if (empty($quantityBreaks)) { $errors[] = 'Add at least one quantity based pricing row.'; }
            foreach ($quantityBreaks as $tier) {
                if ($tier['qty'] === 1) {
                    $price = (float)$tier['price'];
                    break;
                }
            }
            if ($price <= 0 && !empty($quantityBreaks)) {
                $price = (float)$quantityBreaks[0]['price'];
            }
            if ($errors) {
                throw new RuntimeException(implode(' ', $errors));
            }

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE components
                    SET part_number = ?, electava_part_number = ?, name = ?, description = ?, manufacturer_id = ?, category_id = ?, price = ?, quantity_breaks = ?, stock = ?, specifications = ?, asset_links = ?
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $componentId, $uid]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO components (part_number, electava_part_number, name, description, manufacturer_id, category_id, price, quantity_breaks, stock, status, specifications, asset_links, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid]);
                $componentId = (int)$pdo->lastInsertId();
                $existing = ['datasheet_url' => null, 'symbol_file' => null, 'footprint_file' => null, 'step_file' => null, 'image_url' => null, 'asset_links' => null, 'quantity_breaks' => null];
            }

            $currentAssets = [
                'datasheet_url' => $existing['datasheet_url'] ?? null,
                'symbol_file' => $existing['symbol_file'] ?? null,
                'footprint_file' => $existing['footprint_file'] ?? null,
                'step_file' => $existing['step_file'] ?? null,
                'image_url' => $existing['image_url'] ?? null,
            ];

            if ($datasheetText !== '') {
                $currentAssets['datasheet_url'] = $datasheetText;
            } elseif (!$isEdit) {
                $currentAssets['datasheet_url'] = null;
            }

            if (isset($_POST['remove_file_ids']) && is_array($_POST['remove_file_ids'])) {
                deleteComponentUploads($pdo, $componentId, $uid, $_POST['remove_file_ids']);
            }

            foreach ($assetConfig as $column => $config) {
                $removeField = 'remove_' . $column;
                if (isset($_POST[$removeField]) && $_POST[$removeField] === '1') {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = null;
                }

                $uploaded = storeComponentUpload($pdo, $componentId, $uid, $config);
                if ($uploaded !== null) {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = $uploaded;
                }
            }

            $extraUploads = [
                'documents' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['documents']),
                'images' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['images']),
                'cad' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['cad']),
            ];

            if (!empty($assetLinks['documents'])) {
                $currentAssets['datasheet_url'] = $assetLinks['documents'][0]['url'];
            } elseif (!empty($extraUploads['documents']) && empty($currentAssets['datasheet_url'])) {
                $currentAssets['datasheet_url'] = $extraUploads['documents'][0];
            }

            if (!empty($assetLinks['images'])) {
                $currentAssets['image_url'] = $assetLinks['images'][0]['url'];
            } elseif (!empty($extraUploads['images']) && empty($currentAssets['image_url'])) {
                $currentAssets['image_url'] = $extraUploads['images'][0];
            }

            if (!empty($assetLinks['cad'])) {
                $currentAssets['step_file'] = $assetLinks['cad'][0]['url'];
            } elseif (!empty($extraUploads['cad']) && empty($currentAssets['step_file'])) {
                $currentAssets['step_file'] = $extraUploads['cad'][0];
            }

            $stmt = $pdo->prepare("UPDATE components SET datasheet_url = ?, symbol_file = ?, footprint_file = ?, step_file = ?, image_url = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([
                $currentAssets['datasheet_url'],
                $currentAssets['symbol_file'],
                $currentAssets['footprint_file'],
                $currentAssets['step_file'],
                $currentAssets['image_url'],
                $componentId,
                $uid,
            ]);

            logAudit($pdo, $isEdit ? 'update_component' : 'create_component', 'component', $componentId, ($isEdit ? 'Updated' : 'Created') . ': ' . $name);
            $saveIntent = $_POST['save_intent'] ?? 'submit';
            $redirectParams = [
                'flash' => $isEdit ? 'Component updated successfully.' : 'Component saved as a draft.',
                'type' => 'success',
            ];
            if ($saveIntent === 'next') {
                $redirectParams['mode'] = 'create';
                $redirectParams['flash'] = 'Component saved. The form is ready for the next component.';
            } else {
                $redirectParams['mode'] = 'edit';
                $redirectParams['component_id'] = $componentId;
            }

            header('Location: ' . buildComponentsUrl($redirectParams));
            exit;
        } elseif ($_POST['action'] === 'submit_for_approval') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND created_by = ? AND status = 'draft'");
            $stmt->execute([$componentId, $uid]);
            if ($stmt->rowCount() > 0) {
                logAudit($pdo, 'submit_component', 'component', $componentId, 'Submitted for approval');
                $msg = 'Component submitted for approval.';
            } else {
                $msg = 'Only your draft components can be submitted.';
                $msgType = 'error';
            }
            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => $msgType === 'error' ? 'error' : 'success',
            ]));
            exit;
        } elseif ($_POST['action'] === 'bulk_submit_for_approval') {
            $componentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['component_ids'] ?? []))));
            if (!$componentIds) {
                throw new RuntimeException('Select at least one draft component to submit.');
            }

            $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
            $selectStmt = $pdo->prepare("
                SELECT id, name, part_number
                FROM components
                WHERE created_by = ? AND status = 'draft' AND id IN ($placeholders)
            ");
            $selectStmt->execute(array_merge([$uid], $componentIds));
            $draftComponents = $selectStmt->fetchAll();

            if (!$draftComponents) {
                throw new RuntimeException('No draft components were available for bulk submit.');
            }

            $draftIds = array_map(static fn(array $component): int => (int)$component['id'], $draftComponents);
            $draftPlaceholders = implode(',', array_fill(0, count($draftIds), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE components
                SET status = 'pending_approval'
                WHERE created_by = ? AND status = 'draft' AND id IN ($draftPlaceholders)
            ");
            $updateStmt->execute(array_merge([$uid], $draftIds));

            foreach ($draftComponents as $component) {
                logAudit(
                    $pdo,
                    'submit_component',
                    'component',
                    (int)$component['id'],
                    'Submitted for approval: ' . ($component['name'] ?: $component['part_number'])
                );
            }

            $submittedCount = count($draftComponents);
            $msg = $submittedCount === 1
                ? '1 component submitted for approval.'
                : $submittedCount . ' components submitted for approval.';

            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => 'success',
            ]));
            exit;
        }
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }
        $msg = $e->getMessage();
        $msgType = 'error';
    }
}

if (isset($_GET['flash']) && $_GET['flash'] !== '') {
    $msg = (string)$_GET['flash'];
    $msgType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string)($_GET['search'] ?? ''));
$sql = "
    SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
    FROM components c
    LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.created_by = ?
";
$params = [$uid];
if ($statusFilter !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ? OR c.electava_part_number LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll();
$componentUploads = loadComponentUploads($pdo, array_map(fn($component) => (int)$component['id'], $components));
foreach ($components as &$component) {
    $component['specifications_array'] = specificationsArray($component['specifications'] ?? null);
    $component['asset_links_array'] = assetLinksArray($component['asset_links'] ?? null);
    $component['quantity_breaks_array'] = quantityBreaksArray($component['quantity_breaks'] ?? null);
    $component['uploads'] = $componentUploads[(int)$component['id']] ?? ['documents' => [], 'images' => [], 'cad' => []];
}
unset($component);

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$totalComp = count($components);
$draftCount = count(array_filter($components, fn($component) => $component['status'] === 'draft'));
$pendingCount = count(array_filter($components, fn($component) => $component['status'] === 'pending_approval'));
$activeCount = count(array_filter($components, fn($component) => $component['status'] === 'active'));

$pageMode = $_GET['mode'] ?? '';
if (!in_array($pageMode, ['create', 'edit', 'view'], true)) {
    $pageMode = '';
}

$selectedComponentId = (int)($_GET['component_id'] ?? 0);
$formComponent = null;
if (in_array($pageMode, ['edit', 'view'], true) && $selectedComponentId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
        FROM components c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.created_by = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedComponentId, $uid]);
    $formComponent = $stmt->fetch();

    if ($formComponent) {
        $formUploads = loadComponentUploads($pdo, [$selectedComponentId]);
        $formComponent['specifications_array'] = specificationsArray($formComponent['specifications'] ?? null);
        $formComponent['asset_links_array'] = assetLinksArray($formComponent['asset_links'] ?? null);
        $formComponent['quantity_breaks_array'] = quantityBreaksArray($formComponent['quantity_breaks'] ?? null);
        $formComponent['uploads'] = $formUploads[$selectedComponentId] ?? ['documents' => [], 'images' => [], 'cad' => []];
    } else {
        $pageMode = '';
        $msg = 'Component not found.';
        $msgType = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msgType === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?> border px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">Components</h2>
        <p class="text-sm text-slate-500 mt-1">
            <?= $pageMode === '' ? 'Create, view, and edit component listings with mandatory part fields, optional spec rows, and upload areas for docs, CAD, and images.' : 'Complete the full component form on this page, then submit it or save and continue with the next component.' ?>
        </p>
    </div>
    <?php if ($pageMode === ''): ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'create'])) ?>" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium inline-flex items-center">
        <i class="fa-solid fa-plus mr-1.5"></i>New Component
    </a>
    <?php else: ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200 inline-flex items-center">
        <i class="fa-solid fa-table-list mr-1.5"></i>Component List
    </a>
    <?php endif; ?>
</div>

<?php if ($pageMode === ''): ?>
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-white"><?= $totalComp ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Total</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-slate-300"><?= $draftCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Drafts</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-amber-400"><?= $pendingCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-emerald-400"><?= $activeCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Active</div></div>
</div>

<div class="flex items-center gap-3 mb-5">
    <form method="GET" class="flex-1 flex items-center gap-2">
        <div class="relative flex-1">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by part number, Electava part number, or description..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
        </div>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
        </select>
        <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
    </form>
</div>

<div class="glass-card rounded-2xl p-4 border border-slate-700/50 mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Bulk Submit for Approval</h3>
        <p id="bulkSelectionSummary" class="text-xs text-slate-500 mt-1">0 selected from <?= $draftCount ?> draft components.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-slate-300 <?= $draftCount === 0 ? 'opacity-50' : '' ?>">
            <input type="checkbox" id="selectAllDrafts" class="rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40" <?= $draftCount === 0 ? 'disabled' : '' ?>>
            Select All Drafts
        </label>
        <form id="bulkApprovalForm" method="POST">
            <input type="hidden" name="action" value="bulk_submit_for_approval">
            <button type="submit" id="bulkSubmitButton" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed" <?= $draftCount === 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane mr-1.5"></i>Submit Selected
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($components as $component): ?>
    <?php
    $payload = [
        'id' => (int)$component['id'],
        'part_number' => $component['part_number'],
        'electava_part_number' => $component['electava_part_number'] ?: $component['part_number'],
        'name' => $component['name'],
        'description' => $component['description'],
        'manufacturer_id' => $component['manufacturer_id'] ? (int)$component['manufacturer_id'] : '',
        'category_id' => $component['category_id'] ? (int)$component['category_id'] : '',
        'price' => (string)$component['price'],
        'quantity_breaks' => $component['quantity_breaks_array'],
        'stock' => (string)$component['stock'],
        'datasheet_url' => $component['datasheet_url'],
        'symbol_file' => $component['symbol_file'],
        'footprint_file' => $component['footprint_file'],
        'step_file' => $component['step_file'],
        'image_url' => $component['image_url'],
        'specifications' => $component['specifications_array'],
        'asset_links' => $component['asset_links_array'],
        'uploads' => $component['uploads'],
    ];
    ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex items-start gap-3 min-w-0">
                <?php if (!empty($component['image_url'])): ?>
                <img src="<?= htmlspecialchars($component['image_url']) ?>" alt="<?= htmlspecialchars($component['name']) ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-700/70 bg-slate-900/60">
                <?php else: ?>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/15 to-emerald-500/15 flex items-center justify-center border border-blue-500/20 shrink-0"><i class="fa-solid fa-microchip text-blue-400"></i></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate font-mono"><?= htmlspecialchars($component['part_number']) ?></h3>
                    <div class="text-[11px] text-cyan-300 font-mono mt-1">Electava: <?= htmlspecialchars($component['electava_part_number'] ?: $component['part_number']) ?></div>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <?php if ($component['status'] === 'draft'): ?>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300 bg-slate-900/50 border border-slate-700/70 rounded-lg px-2 py-1.5">
                    <input
                        type="checkbox"
                        name="component_ids[]"
                        value="<?= (int)$component['id'] ?>"
                        form="bulkApprovalForm"
                        class="bulk-component-checkbox rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40"
                    >
                    Select
                </label>
                <?php endif; ?>
                <?= statusBadge($component['status']) ?>
            </div>
        </div>
        <p class="text-xs text-slate-400 mb-3 min-h-[38px]"><?= htmlspecialchars($component['description']) ?></p>
        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Mfr:</span> <span class="text-slate-300"><?= htmlspecialchars($component['manufacturer_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Cat:</span> <span class="text-slate-300"><?= htmlspecialchars($component['category_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Price:</span> <span class="text-emerald-400">INR <?= number_format((float)$component['price'], 2) ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Stock:</span> <span class="text-white"><?= number_format((int)$component['stock']) ?></span></div>
        </div>
        <?php if (!empty($component['quantity_breaks_array'])): ?>
        <div class="mb-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-3 py-3">
            <div class="text-[11px] uppercase tracking-widest text-emerald-300 mb-2">Tier Pricing</div>
            <div class="flex flex-wrap gap-2">
                <?php foreach (array_slice($component['quantity_breaks_array'], 0, 4) as $tier): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-200">
                    <?= (int)$tier['qty'] ?> qty - INR <?= number_format((float)$tier['price'], 2) ?>
                </span>
                <?php endforeach; ?>
                <?php if (count($component['quantity_breaks_array']) > 4): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-400">
                    +<?= count($component['quantity_breaks_array']) - 4 ?> more
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach (['datasheet_url' => 'Doc', 'symbol_file' => 'Symbol', 'footprint_file' => 'Footprint', 'step_file' => '3D CAD', 'image_url' => 'Image'] as $column => $label): ?>
                <?php if (!empty($component[$column])): ?><span class="px-2 py-1 rounded-lg text-[11px] bg-cyan-500/10 text-cyan-300 border border-cyan-500/20"><?= $label ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'view', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-eye mr-1"></i>View</a>
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'edit', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</a>
            <?php if ($component['status'] === 'draft'): ?>
            <form method="POST" class="flex-1">
                <input type="hidden" name="action" value="submit_for_approval">
                <input type="hidden" name="component_id" value="<?= (int)$component['id'] ?>">
                <button class="w-full text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-2 rounded-lg hover:bg-emerald-600/40 transition font-medium"><i class="fa-solid fa-paper-plane mr-1"></i>Submit for Approval</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="text-[10px] text-slate-600 mt-3"><?= timeAgo($component['created_at']) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($components)): ?>
    <div class="col-span-full glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4"><i class="fa-solid fa-microchip text-slate-600 text-2xl"></i></div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Components Yet</h3>
        <p class="text-sm text-slate-600">Add your first component with required fields and optional uploads.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($pageMode !== ''): ?>
<div class="glass-card rounded-3xl p-6 lg:p-8 shadow-2xl border border-slate-700/50 mt-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div>
                <h3 id="componentModalTitle" class="text-lg font-semibold text-white"><i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component</h3>
                <p class="text-xs text-slate-500 mt-1">Part Number, Electava Part Number, Manufacturer, and Description are mandatory. Optional rows can be added or deleted.</p>
            </div>
        </div>
        <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">
            <i class="fa-solid fa-arrow-left mr-1.5"></i>Back to List
        </a>
    </div>
    <form id="componentForm" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="action" value="save_component">
            <input type="hidden" name="component_id" id="component_id" value="0">
            <input type="hidden" name="name" id="name" value="">
            <input type="hidden" name="price" id="price" value="0">
            <input type="hidden" name="datasheet_url_text" id="datasheet_url_text" value="">
            <div class="grid lg:grid-cols-2 gap-5">
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-4">Main Details</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" id="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Electava Part Number *</label><input type="text" name="electava_part_number" id="electava_part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Manufacturer *</label>
                                    <button type="button" data-reference-trigger="manufacturer" onclick="openReferenceModal('manufacturer')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Manufacturer
                                    </button>
                                </div>
                                <select name="manufacturer_id" id="manufacturer_id" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Select manufacturer</option>
                                    <?php foreach ($manufacturers as $manufacturer): ?><option value="<?= (int)$manufacturer['id'] ?>"><?= htmlspecialchars($manufacturer['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Stock</label><input type="number" name="stock" id="stock" min="0" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Category</label>
                                    <button type="button" data-reference-trigger="category" onclick="openReferenceModal('category')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Category
                                    </button>
                                </div>
                                <select name="category_id" id="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Optional</option>
                                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-900/35 border border-slate-800 p-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-sm font-medium text-white">Quantity Based Pricing</div>
                                    <div class="text-[11px] text-slate-500 mt-1">Add different prices for 1 qty, 10 qty, 50 qty, or any other quantity break.</div>
                                </div>
                                <button type="button" data-pricing-add onclick="addPricingRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 whitespace-nowrap">
                                    <i class="fa-solid fa-plus mr-1"></i>Add Pricing
                                </button>
                            </div>
                            <div id="pricingRows" class="space-y-3"></div>
                        </div>
                        <div class="mt-4"><label class="block text-xs text-slate-400 mb-1.5">Description *</label><textarea name="description" id="description" rows="4" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-white">Optional Specifications</h4>
                            <button type="button" onclick="addSpecRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Row</button>
                        </div>
                        <div id="specRows" class="space-y-3"></div>
                    </div>
                </section>
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Assets Options</h4>
                                <p class="text-[11px] text-slate-500 mt-1">Add labels with URLs or upload local files for documents, images, and EDA/CAD models.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Documents</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload local documents.</div>
                                    </div>
                                    <button type="button" data-link-add="documents" onclick="addLinkRow('documents', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="documentLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Documents</label>
                                    <input type="file" name="document_uploads[]" id="document_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['documents']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Images</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use an image URL or upload local images.</div>
                                    </div>
                                    <button type="button" data-link-add="images" onclick="addLinkRow('images', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="imageLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Images</label>
                                    <input type="file" name="image_uploads[]" id="image_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['images']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">EDA/CAD Models</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload ZIP / EDA / CAD model files.</div>
                                    </div>
                                    <button type="button" data-link-add="cad" onclick="addLinkRow('cad', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="cadLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local EDA/CAD ZIP Files</label>
                                    <input type="file" name="cad_uploads[]" id="cad_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['cad']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Current Assets</h4>
                                <p class="text-[11px] text-slate-500 mt-1">See current primary assets, uploaded files, and remove options here.</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs">
                            <?php foreach ($assetConfig as $column => $config): ?>
                            <div id="asset-<?= htmlspecialchars($column) ?>" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-slate-300 font-medium">Primary <?= htmlspecialchars($config['label']) ?></div>
                                        <a id="asset-link-<?= htmlspecialchars($column) ?>" href="#" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline"></a>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-red-300"><input type="checkbox" name="remove_<?= htmlspecialchars($column) ?>" id="remove_<?= htmlspecialchars($column) ?>" value="1">Remove</label>
                                </div>
                                <?php if ($column === 'image_url'): ?><img id="asset-preview-image_url" src="" alt="Current image" class="hidden mt-3 w-20 h-20 rounded-xl object-cover border border-slate-700/80"><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <div id="uploadedAssetsPanel" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="text-slate-300 font-medium mb-3">Uploaded Extras</div>
                                <div id="uploadedAssetsList" class="space-y-2"></div>
                            </div>
                            <div id="currentAssetsEmptyState" class="bg-slate-900/35 border border-dashed border-slate-700/70 rounded-xl px-3 py-3 text-slate-500">
                                Current files will appear here after you upload or save assets.
                            </div>
                        </div>
                    </div>
                    <div id="previewPanel" class="hidden glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-3">View Summary</h4>
                        <div id="previewContent" class="text-sm text-slate-300 space-y-2"></div>
                    </div>
                </section>
            </div>
            <div class="flex flex-wrap justify-end gap-3 pt-2 border-t border-slate-800/70">
                <button type="button" id="previewToggleButton" onclick="togglePreview()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200"><i class="fa-solid fa-eye mr-1"></i>View</button>
                <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" id="componentCancelButton" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">Cancel</a>
                <button type="submit" id="componentNextButton" name="save_intent" value="next" class="btn-secondary px-5 py-2 rounded-lg text-sm text-slate-100 font-medium">Next</button>
                <button type="submit" id="componentSubmitButton" name="save_intent" value="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
    </form>
</div>
<?php endif; ?>

<div id="referenceModal" class="hidden fixed inset-0 modal-backdrop z-[60] flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 id="referenceModalTitle" class="text-lg font-semibold text-white">Add Reference</h3>
                <p id="referenceModalSubtitle" class="text-xs text-slate-500 mt-1">Save a reusable option for employee component entries.</p>
            </div>
            <button type="button" onclick="closeReferenceModal()" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="referenceForm" class="space-y-4">
            <input type="hidden" name="action" value="create_reference">
            <input type="hidden" name="reference_type" id="reference_type" value="">
            <div>
                <label id="referenceNameLabel" class="block text-xs text-slate-400 mb-1.5">Name</label>
                <input type="text" name="reference_name" id="reference_name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="">
            </div>
            <div id="referenceWebsiteRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Website</label>
                <input type="url" name="reference_website" id="reference_website" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="https://manufacturer-site.com">
            </div>
            <div id="referenceParentRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Parent Category</label>
                <select name="reference_parent_id" id="reference_parent_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                    <option value="0">No parent</option>
                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="referenceFeedback" class="hidden text-sm rounded-xl px-3 py-2"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeReferenceModal()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button type="submit" id="referenceSubmitButton" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const initialComponentData = <?= json_encode($formComponent ? [
    'id' => (int)$formComponent['id'],
    'part_number' => $formComponent['part_number'],
    'electava_part_number' => $formComponent['electava_part_number'] ?: $formComponent['part_number'],
    'name' => $formComponent['name'],
    'description' => $formComponent['description'],
    'manufacturer_id' => $formComponent['manufacturer_id'] ? (int)$formComponent['manufacturer_id'] : '',
    'category_id' => $formComponent['category_id'] ? (int)$formComponent['category_id'] : '',
    'price' => (string)$formComponent['price'],
    'quantity_breaks' => $formComponent['quantity_breaks_array'],
    'stock' => (string)$formComponent['stock'],
    'datasheet_url' => $formComponent['datasheet_url'],
    'symbol_file' => $formComponent['symbol_file'],
    'footprint_file' => $formComponent['footprint_file'],
    'step_file' => $formComponent['step_file'],
    'image_url' => $formComponent['image_url'],
    'specifications' => $formComponent['specifications_array'],
    'asset_links' => $formComponent['asset_links_array'],
    'uploads' => $formComponent['uploads'],
] : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const pageMode = <?= json_encode($pageMode) ?>;
const specRows = document.getElementById('specRows');
const pricingRows = document.getElementById('pricingRows');
const componentForm = document.getElementById('componentForm');
const previewPanel = document.getElementById('previewPanel');
const previewContent = document.getElementById('previewContent');
const previewToggleButton = document.getElementById('previewToggleButton');
const componentSubmitButton = document.getElementById('componentSubmitButton');
const componentNextButton = document.getElementById('componentNextButton');
const componentModalTitle = document.getElementById('componentModalTitle');
const referenceModal = document.getElementById('referenceModal');
const referenceForm = document.getElementById('referenceForm');
const referenceModalTitle = document.getElementById('referenceModalTitle');
const referenceModalSubtitle = document.getElementById('referenceModalSubtitle');
const referenceNameLabel = document.getElementById('referenceNameLabel');
const referenceNameInput = document.getElementById('reference_name');
const referenceWebsiteRow = document.getElementById('referenceWebsiteRow');
const referenceParentRow = document.getElementById('referenceParentRow');
const referenceFeedback = document.getElementById('referenceFeedback');
const referenceSubmitButton = document.getElementById('referenceSubmitButton');
const uploadedAssetsPanel = document.getElementById('uploadedAssetsPanel');
const uploadedAssetsList = document.getElementById('uploadedAssetsList');
const currentAssetsEmptyState = document.getElementById('currentAssetsEmptyState');
const selectAllDrafts = document.getElementById('selectAllDrafts');
const bulkSubmitButton = document.getElementById('bulkSubmitButton');
const bulkSelectionSummary = document.getElementById('bulkSelectionSummary');
const bulkComponentCheckboxes = Array.from(document.querySelectorAll('.bulk-component-checkbox'));
const uploadInputMap = { documents: 'document_uploads', images: 'image_uploads', cad: 'cad_uploads' };
let currentUploadedAssets = { documents: [], images: [], cad: [] };

function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function createSpecRow(key = '', value = '') {
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[1fr,1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="spec_key[]" value="${escapeHtml(key)}" placeholder="Specification name" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="text" name="spec_value[]" value="${escapeHtml(value)}" placeholder="Value" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}
function addSpecRow(key = '', value = '') { specRows.appendChild(createSpecRow(key, value)); }
function resetSpecRows(specifications = []) {
    if (!specRows) return;
    specRows.innerHTML = '';
    if (!specifications.length) { addSpecRow('', ''); return; }
    specifications.forEach(([key, value]) => addSpecRow(key, value));
}

function createPricingPlaceholder() {
    const empty = document.createElement('div');
    empty.className = 'pricing-empty-state text-xs text-slate-500 border border-dashed border-slate-700/70 rounded-xl px-3 py-3';
    empty.textContent = 'No quantity pricing added yet. Use Add Pricing for 1 qty, 10 qty, 50 qty, or more.';
    return empty;
}

function syncPricingPlaceholder() {
    if (!pricingRows) return;
    const rowCount = pricingRows.querySelectorAll('[data-pricing-row]').length;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (rowCount === 0 && !placeholder) {
        pricingRows.appendChild(createPricingPlaceholder());
    }
    if (rowCount > 0 && placeholder) {
        placeholder.remove();
    }
}

function createPricingRow(qty = '', price = '') {
    const row = document.createElement('div');
    row.dataset.pricingRow = 'true';
    row.className = 'grid grid-cols-[minmax(0,160px),minmax(0,1fr),auto] gap-3 items-center';
    row.innerHTML = `<input type="number" name="tier_qty[]" min="1" step="1" value="${escapeHtml(qty)}" placeholder="Quantity" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="number" name="tier_price[]" min="0" step="0.01" value="${escapeHtml(price)}" placeholder="Price in INR" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        syncPricingPlaceholder();
    });
    return row;
}

function addPricingRow(qty = '', price = '') {
    if (!pricingRows) return;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (placeholder) {
        placeholder.remove();
    }
    pricingRows.appendChild(createPricingRow(qty, price));
}

function resetPricingRows(entries = []) {
    if (!pricingRows) return;
    pricingRows.innerHTML = '';
    if (!entries.length) {
        syncPricingPlaceholder();
        return;
    }
    entries.forEach((entry) => addPricingRow(entry.qty || entry.quantity || '', entry.price ?? ''));
    syncPricingPlaceholder();
}

function createLinkRow(group, label = '', url = '') {
    const fieldMap = { documents: 'document', images: 'image', cad: 'cad' };
    const fieldPrefix = fieldMap[group] || group;
    const labelPlaceholders = { documents: 'Document label', images: 'Image label', cad: 'EDA/CAD label' };
    const urlPlaceholders = { documents: 'Document URL', images: 'Image URL', cad: 'EDA/CAD URL' };
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[minmax(0,180px),1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="${fieldPrefix}_link_label[]" value="${escapeHtml(label)}" placeholder="${escapeHtml(labelPlaceholders[group] || 'Label')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="url" name="${fieldPrefix}_link_url[]" value="${escapeHtml(url)}" placeholder="${escapeHtml(urlPlaceholders[group] || 'URL')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}

function addLinkRow(group, label = '', url = '') {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    document.getElementById(map[group]).appendChild(createLinkRow(group, label, url));
}

function resetLinkRows(group, entries = []) {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    const container = document.getElementById(map[group]);
    if (!container) return;
    container.innerHTML = '';
    if (!entries.length) {
        addLinkRow(group, '', '');
        return;
    }
    entries.forEach((entry) => addLinkRow(group, entry.label || '', entry.url || ''));
}

function clearUploads() {
    ['document_uploads', 'image_uploads', 'cad_uploads'].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });
}

function formatFileSize(bytes = 0) {
    const size = Number(bytes) || 0;
    if (size <= 0) return '';
    if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${size} bytes`;
}

function getSelectedUploadEntries() {
    return Object.entries(uploadInputMap).reduce((entries, [group, inputId]) => {
        const input = document.getElementById(inputId);
        const files = input?.files ? Array.from(input.files) : [];
        entries[group] = files.map((file, index) => ({
            id: `pending-${group}-${index}-${file.name}`,
            name: file.name,
            pending: true,
            note: formatFileSize(file.size),
        }));
        return entries;
    }, { documents: [], images: [], cad: [] });
}

function refreshCurrentAssetsState() {
    const primaryVisible = ['datasheet_url', 'symbol_file', 'footprint_file', 'step_file', 'image_url']
        .map((column) => document.getElementById(`asset-${column}`))
        .filter((block) => block && !block.classList.contains('hidden')).length;
    const uploadedVisible = uploadedAssetsList ? uploadedAssetsList.children.length : 0;

    if (uploadedAssetsPanel) {
        uploadedAssetsPanel.classList.toggle('hidden', uploadedVisible === 0);
    }

    if (currentAssetsEmptyState) {
        currentAssetsEmptyState.classList.toggle('hidden', primaryVisible > 0 || uploadedVisible > 0);
    }
}

function resetAssetBlock(column) {
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    const checkbox = document.getElementById(`remove_${column}`);
    if (!block || !link || !checkbox) return;
    block.classList.add('hidden'); link.textContent = ''; link.href = '#'; checkbox.checked = false;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.classList.add('hidden'); img.src = ''; }
    refreshCurrentAssetsState();
}

function setAssetBlock(column, value) {
    resetAssetBlock(column);
    if (!value) return;
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    if (!block || !link) return;
    block.classList.remove('hidden'); link.textContent = value; link.href = value;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.src = value; img.classList.remove('hidden'); }
    refreshCurrentAssetsState();
}

function setFormDisabled(disabled) {
    if (!componentForm) return;
    componentForm.querySelectorAll('input, textarea, select').forEach((field) => { if (field.type !== 'hidden') field.disabled = disabled; });
    if (specRows) {
        specRows.querySelectorAll('button').forEach((button) => { button.disabled = disabled; button.classList.toggle('opacity-50', disabled); });
    }
    if (pricingRows) {
        pricingRows.querySelectorAll('button, input').forEach((control) => {
            control.disabled = disabled;
            control.classList.toggle('opacity-50', disabled && control.tagName === 'BUTTON');
        });
    }
    document.querySelectorAll('[data-reference-trigger]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-pricing-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-link-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('#documentLinkRows button, #imageLinkRows button, #cadLinkRows button, #uploadedAssetsList input[type="checkbox"]').forEach((control) => {
        control.disabled = disabled;
    });
}

function renderUploadedAssets(uploads = {}) {
    if (!uploadedAssetsList || !uploadedAssetsPanel) return;
    currentUploadedAssets = {
        documents: Array.isArray(uploads.documents) ? uploads.documents : [],
        images: Array.isArray(uploads.images) ? uploads.images : [],
        cad: Array.isArray(uploads.cad) ? uploads.cad : [],
    };
    uploadedAssetsList.innerHTML = '';
    const groupLabels = { documents: 'Documents', images: 'Images', cad: 'EDA/CAD' };
    const selectedUploads = getSelectedUploadEntries();
    ['documents', 'images', 'cad'].forEach((group) => {
        currentUploadedAssets[group].forEach((file) => {
            const item = document.createElement('label');
            item.className = 'flex items-center justify-between gap-3 bg-slate-950/40 border border-slate-800 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-200 truncate">${escapeHtml(file.name)}</div></div><a href="${escapeHtml(file.path)}" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline">${escapeHtml(file.path)}</a></div><span class="inline-flex items-center gap-2 text-red-300 shrink-0"><input type="checkbox" name="remove_file_ids[]" value="${escapeHtml(file.id)}">Remove</span>`;
            uploadedAssetsList.appendChild(item);
        });
        selectedUploads[group].forEach((file) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-cyan-500/10 border border-cyan-500/20 text-cyan-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-100 truncate">${escapeHtml(file.name)}</div></div><div class="text-cyan-200 break-all">Selected now${file.note ? ` · ${escapeHtml(file.note)}` : ''}</div></div><span class="text-[11px] text-cyan-300 shrink-0">Ready to upload</span>`;
            uploadedAssetsList.appendChild(item);
        });
    });
    refreshCurrentAssetsState();
}

function buildPreview() {
    if (!componentForm || !previewContent) return;
    const manufacturer = document.getElementById('manufacturer_id').selectedOptions[0]?.text || '-';
    const category = document.getElementById('category_id').selectedOptions[0]?.text || '-';
    const specEntries = Array.from(document.querySelectorAll('input[name="spec_key[]"]')).map((input, index) => [input.value.trim(), document.querySelectorAll('input[name="spec_value[]"]')[index].value.trim()]).filter(([key, value]) => key || value);
    const pricingEntries = Array.from(document.querySelectorAll('input[name="tier_qty[]"]')).map((input, index) => ({
        qty: input.value.trim(),
        price: document.querySelectorAll('input[name="tier_price[]"]')[index].value.trim(),
    })).filter((entry) => entry.qty !== '' || entry.price !== '');
    const docLinks = Array.from(document.querySelectorAll('input[name="document_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const imageLinks = Array.from(document.querySelectorAll('input[name="image_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const cadLinks = Array.from(document.querySelectorAll('input[name="cad_link_url[]"]')).filter((input) => input.value.trim() !== '');
    previewContent.innerHTML = `<div><span class="text-slate-500">Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('part_number').value || '-')}</span></div><div><span class="text-slate-500">Electava Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('electava_part_number').value || '-')}</span></div><div><span class="text-slate-500">Manufacturer:</span> <span class="text-white">${escapeHtml(manufacturer)}</span></div><div><span class="text-slate-500">Category:</span> <span class="text-white">${escapeHtml(category === 'Optional' ? '-' : category)}</span></div><div><span class="text-slate-500">Quantity Pricing:</span> <div class="mt-1">${pricingEntries.length ? pricingEntries.map((entry) => `<div class="text-white">${escapeHtml(entry.qty || '-')} qty: INR ${escapeHtml(entry.price || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div><div><span class="text-slate-500">Description:</span> <span class="text-white">${escapeHtml(document.getElementById('description').value || '-')}</span></div><div><span class="text-slate-500">Asset Entries:</span> <span class="text-white">Docs ${docLinks.length}, Images ${imageLinks.length}, EDA/CAD ${cadLinks.length}</span></div><div><span class="text-slate-500">Uploads Selected:</span> <span class="text-white">Docs ${document.getElementById('document_uploads').files.length}, Images ${document.getElementById('image_uploads').files.length}, EDA/CAD ${document.getElementById('cad_uploads').files.length}</span></div><div><span class="text-slate-500">Specifications:</span> <div class="mt-1">${specEntries.length ? specEntries.map(([key, value]) => `<div class="text-white">${escapeHtml(key)}: ${escapeHtml(value || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div>`;
}

function togglePreview(forceOpen = null) {
    if (!previewPanel || !previewToggleButton) return;
    const open = forceOpen === null ? previewPanel.classList.contains('hidden') : forceOpen;
    if (open) { buildPreview(); previewPanel.classList.remove('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye-slash mr-1"></i>Hide View'; }
    else { previewPanel.classList.add('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye mr-1"></i>View'; }
}

function populateComponentForm(mode = 'create', component = null) {
    if (!componentForm) return;

    componentForm.reset();
    clearUploads();
    resetSpecRows([]);
    resetPricingRows([]);
    resetLinkRows('documents', []);
    resetLinkRows('images', []);
    resetLinkRows('cad', []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach(resetAssetBlock);
    renderUploadedAssets({});
    togglePreview(false);

    document.getElementById('component_id').value = component?.id || 0;
    document.getElementById('part_number').value = component?.part_number || '';
    document.getElementById('electava_part_number').value = component?.electava_part_number || '';
    document.getElementById('name').value = component?.part_number || component?.name || '';
    document.getElementById('description').value = component?.description || '';
    document.getElementById('manufacturer_id').value = component?.manufacturer_id || '';
    document.getElementById('category_id').value = component?.category_id || '';
    document.getElementById('price').value = component?.price || '0';
    resetPricingRows(component?.quantity_breaks?.length ? component.quantity_breaks : (component?.price ? [{ qty: 1, price: component.price }] : []));
    document.getElementById('stock').value = component?.stock || '0';
    document.getElementById('datasheet_url_text').value = component?.datasheet_url && !String(component.datasheet_url).startsWith('/uploads/') ? component.datasheet_url : '';
    resetSpecRows(component?.specifications ? Object.entries(component.specifications) : []);
    resetLinkRows('documents', component?.asset_links?.documents || []);
    resetLinkRows('images', component?.asset_links?.images || []);
    resetLinkRows('cad', component?.asset_links?.cad || []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach((column) => setAssetBlock(column, component?.[column] || ''));
    renderUploadedAssets(component?.uploads || {});

    const viewOnly = mode === 'view';
    setFormDisabled(viewOnly);
    if (componentSubmitButton) componentSubmitButton.classList.toggle('hidden', viewOnly);
    if (componentNextButton) componentNextButton.classList.toggle('hidden', viewOnly);
    if (previewToggleButton) previewToggleButton.classList.remove('hidden');
    if (componentModalTitle) {
        componentModalTitle.innerHTML = viewOnly
            ? '<i class="fa-solid fa-eye text-blue-400 mr-2"></i>View Component'
            : mode === 'edit'
                ? '<i class="fa-solid fa-pen-to-square text-blue-400 mr-2"></i>Edit Component'
                : '<i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component';
    }
    if (viewOnly) {
        togglePreview(true);
    }
}

if (componentForm) {
    populateComponentForm(pageMode || 'create', initialComponentData);
    componentForm.addEventListener('input', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    componentForm.addEventListener('change', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    Object.values(uploadInputMap).forEach((inputId) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', () => {
            renderUploadedAssets(currentUploadedAssets);
        });
    });
}

function updateBulkSelectionState() {
    const total = bulkComponentCheckboxes.length;
    const selected = bulkComponentCheckboxes.filter((checkbox) => checkbox.checked).length;

    if (bulkSelectionSummary) {
        bulkSelectionSummary.textContent = `${selected} selected from ${total} draft components.`;
    }
    if (bulkSubmitButton) {
        bulkSubmitButton.disabled = selected === 0;
    }
    if (selectAllDrafts) {
        selectAllDrafts.checked = total > 0 && selected === total;
        selectAllDrafts.indeterminate = selected > 0 && selected < total;
    }
}

if (selectAllDrafts) {
    selectAllDrafts.addEventListener('change', () => {
        bulkComponentCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllDrafts.checked;
        });
        updateBulkSelectionState();
    });
}

bulkComponentCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', updateBulkSelectionState);
});

updateBulkSelectionState();

function showReferenceFeedback(message, isError = false) {
    referenceFeedback.textContent = message;
    referenceFeedback.className = `${isError ? 'bg-red-500/10 border border-red-500/20 text-red-300' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300'} text-sm rounded-xl px-3 py-2`;
    referenceFeedback.classList.remove('hidden');
}
function closeReferenceModal() {
    referenceModal.classList.add('hidden');
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
}
function openReferenceModal(type) {
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
    document.getElementById('reference_type').value = type;
    if (type === 'manufacturer') {
        referenceModalTitle.textContent = 'Add Manufacturer';
        referenceModalSubtitle.textContent = 'Save a manufacturer name so employees can reuse it in the dropdown.';
        referenceNameLabel.textContent = 'Manufacturer Name';
        referenceNameInput.placeholder = 'e.g. Infineon';
        referenceWebsiteRow.classList.remove('hidden');
        referenceParentRow.classList.add('hidden');
    } else {
        referenceModalTitle.textContent = 'Add Category';
        referenceModalSubtitle.textContent = 'Save a category so it appears in the employee dropdown next time too.';
        referenceNameLabel.textContent = 'Category Name';
        referenceNameInput.placeholder = 'e.g. Sensors';
        referenceWebsiteRow.classList.add('hidden');
        referenceParentRow.classList.remove('hidden');
    }
    referenceModal.classList.remove('hidden');
    referenceNameInput.focus();
}
function upsertSelectOption(selectEl, optionValue, optionLabel) {
    let option = Array.from(selectEl.options).find((item) => item.value === String(optionValue));
    if (!option) {
        option = document.createElement('option');
        option.value = String(optionValue);
        option.textContent = optionLabel;
        selectEl.appendChild(option);
    }
    option.selected = true;
}
referenceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    referenceFeedback.classList.add('hidden');
    referenceSubmitButton.disabled = true;
    try {
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(referenceForm),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Unable to save reference.');
        }

        if (payload.type === 'manufacturer') {
            upsertSelectOption(document.getElementById('manufacturer_id'), payload.id, payload.name);
        } else if (payload.type === 'category') {
            upsertSelectOption(document.getElementById('category_id'), payload.id, payload.name);
            upsertSelectOption(document.getElementById('reference_parent_id'), payload.id, payload.name);
        }

        showReferenceFeedback(payload.message || 'Saved successfully.');
        setTimeout(() => closeReferenceModal(), 600);
    } catch (error) {
        showReferenceFeedback(error.message || 'Unable to save reference.', true);
    } finally {
        referenceSubmitButton.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
POST['action'])) {
    requireCsrf();
    try {
        if ($_POST['action'] === 'create_reference') {
            $referenceType = $_POST['reference_type'] ?? '';
            $referenceName = trim((string)($_POST['reference_name'] ?? ''));

            if ($referenceName === '') {
                throw new RuntimeException('Name is required.');
            }

            if ($referenceType === 'manufacturer') {
                $website = trim((string)($_POST['reference_website'] ?? ''));
                $stmt = $pdo->prepare("SELECT id, name FROM manufacturers WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$referenceName]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO manufacturers (name, website) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $website !== '' ? $website : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_manufacturer', 'manufacturer', $referenceId, 'Created manufacturer: ' . $referenceName);
                }
            } elseif ($referenceType === 'category') {
                $parentId = (int)($_POST['reference_parent_id'] ?? 0);
                $stmt = $pdo->prepare("
                    SELECT id, name
                    FROM categories
                    WHERE LOWER(name) = LOWER(?)
                      AND ((parent_id IS NULL AND ? = 0) OR parent_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$referenceName, $parentId, $parentId]);
                $existingReference = $stmt->fetch();

                if ($existingReference) {
                    $referenceId = (int)$existingReference['id'];
                    $created = false;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
                    $stmt->execute([$referenceName, $parentId > 0 ? $parentId : null]);
                    $referenceId = (int)$pdo->lastInsertId();
                    $created = true;
                    logAudit($pdo, 'create_category', 'category', $referenceId, 'Created category: ' . $referenceName);
                }
            } else {
                throw new RuntimeException('Invalid reference type.');
            }

            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'type' => $referenceType,
                    'id' => $referenceId,
                    'name' => $referenceName,
                    'parent_id' => $referenceType === 'category' ? (int)($_POST['reference_parent_id'] ?? 0) : null,
                    'created' => $created,
                    'message' => $created ? ucfirst($referenceType) . ' added successfully.' : ucfirst($referenceType) . ' already exists and is ready to use.',
                ]);
                exit;
            }

            $msg = ucfirst($referenceType) . ' saved successfully.';
        } elseif ($_POST['action'] === 'save_component') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $isEdit = $componentId > 0;
            $existing = null;

            if ($isEdit) {
                $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ? AND created_by = ?");
                $stmt->execute([$componentId, $uid]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Component not found for editing.');
                }
            }

            $partNumber = trim((string)($_POST['part_number'] ?? ''));
            $electavaPartNumber = trim((string)($_POST['electava_part_number'] ?? ''));
            $name = $partNumber;
            $description = trim((string)($_POST['description'] ?? ''));
            $manufacturerId = (int)($_POST['manufacturer_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $quantityBreaks = parseQuantityBreaks($_POST['tier_qty'] ?? [], $_POST['tier_price'] ?? []);
            $specifications = parseSpecifications($_POST['spec_key'] ?? [], $_POST['spec_value'] ?? []);
            $datasheetText = trim((string)($_POST['datasheet_url_text'] ?? ''));
            $assetLinks = [
                'documents' => parseLinkEntries($_POST['document_link_label'] ?? $_POST['documents_link_label'] ?? [], $_POST['document_link_url'] ?? $_POST['documents_link_url'] ?? []),
                'images' => parseLinkEntries($_POST['image_link_label'] ?? $_POST['images_link_label'] ?? [], $_POST['image_link_url'] ?? $_POST['images_link_url'] ?? []),
                'cad' => parseLinkEntries($_POST['cad_link_label'] ?? [], $_POST['cad_link_url'] ?? []),
            ];

            $errors = [];
            if ($partNumber === '') { $errors[] = 'Part Number is mandatory.'; }
            if ($electavaPartNumber === '') { $errors[] = 'Electava Part Number is mandatory.'; }
            if ($manufacturerId <= 0) { $errors[] = 'Manufacturer is mandatory.'; }
            if ($description === '') { $errors[] = 'Description is mandatory.'; }
            if (empty($quantityBreaks)) { $errors[] = 'Add at least one quantity based pricing row.'; }
            foreach ($quantityBreaks as $tier) {
                if ($tier['qty'] === 1) {
                    $price = (float)$tier['price'];
                    break;
                }
            }
            if ($price <= 0 && !empty($quantityBreaks)) {
                $price = (float)$quantityBreaks[0]['price'];
            }
            if ($errors) {
                throw new RuntimeException(implode(' ', $errors));
            }

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE components
                    SET part_number = ?, electava_part_number = ?, name = ?, description = ?, manufacturer_id = ?, category_id = ?, price = ?, quantity_breaks = ?, stock = ?, specifications = ?, asset_links = ?
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $componentId, $uid]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO components (part_number, electava_part_number, name, description, manufacturer_id, category_id, price, quantity_breaks, stock, status, specifications, asset_links, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
                ");
                $stmt->execute([$partNumber, $electavaPartNumber, $name, $description, $manufacturerId ?: null, $categoryId ?: null, $price, json_encode($quantityBreaks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stock, $specifications, json_encode($assetLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid]);
                $componentId = (int)$pdo->lastInsertId();
                $existing = ['datasheet_url' => null, 'symbol_file' => null, 'footprint_file' => null, 'step_file' => null, 'image_url' => null, 'asset_links' => null, 'quantity_breaks' => null];
            }

            $currentAssets = [
                'datasheet_url' => $existing['datasheet_url'] ?? null,
                'symbol_file' => $existing['symbol_file'] ?? null,
                'footprint_file' => $existing['footprint_file'] ?? null,
                'step_file' => $existing['step_file'] ?? null,
                'image_url' => $existing['image_url'] ?? null,
            ];

            if ($datasheetText !== '') {
                $currentAssets['datasheet_url'] = $datasheetText;
            } elseif (!$isEdit) {
                $currentAssets['datasheet_url'] = null;
            }

            if (isset($_POST['remove_file_ids']) && is_array($_POST['remove_file_ids'])) {
                deleteComponentUploads($pdo, $componentId, $uid, $_POST['remove_file_ids']);
            }

            foreach ($assetConfig as $column => $config) {
                $removeField = 'remove_' . $column;
                if (isset($_POST[$removeField]) && $_POST[$removeField] === '1') {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = null;
                }

                $uploaded = storeComponentUpload($pdo, $componentId, $uid, $config);
                if ($uploaded !== null) {
                    deleteLocalUpload($currentAssets[$column]);
                    $currentAssets[$column] = $uploaded;
                }
            }

            $extraUploads = [
                'documents' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['documents']),
                'images' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['images']),
                'cad' => storeComponentUploads($pdo, $componentId, $uid, $multiUploadConfig['cad']),
            ];

            if (!empty($assetLinks['documents'])) {
                $currentAssets['datasheet_url'] = $assetLinks['documents'][0]['url'];
            } elseif (!empty($extraUploads['documents']) && empty($currentAssets['datasheet_url'])) {
                $currentAssets['datasheet_url'] = $extraUploads['documents'][0];
            }

            if (!empty($assetLinks['images'])) {
                $currentAssets['image_url'] = $assetLinks['images'][0]['url'];
            } elseif (!empty($extraUploads['images']) && empty($currentAssets['image_url'])) {
                $currentAssets['image_url'] = $extraUploads['images'][0];
            }

            if (!empty($assetLinks['cad'])) {
                $currentAssets['step_file'] = $assetLinks['cad'][0]['url'];
            } elseif (!empty($extraUploads['cad']) && empty($currentAssets['step_file'])) {
                $currentAssets['step_file'] = $extraUploads['cad'][0];
            }

            $stmt = $pdo->prepare("UPDATE components SET datasheet_url = ?, symbol_file = ?, footprint_file = ?, step_file = ?, image_url = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([
                $currentAssets['datasheet_url'],
                $currentAssets['symbol_file'],
                $currentAssets['footprint_file'],
                $currentAssets['step_file'],
                $currentAssets['image_url'],
                $componentId,
                $uid,
            ]);

            logAudit($pdo, $isEdit ? 'update_component' : 'create_component', 'component', $componentId, ($isEdit ? 'Updated' : 'Created') . ': ' . $name);
            $saveIntent = $_POST['save_intent'] ?? 'submit';
            $redirectParams = [
                'flash' => $isEdit ? 'Component updated successfully.' : 'Component saved as a draft.',
                'type' => 'success',
            ];
            if ($saveIntent === 'next') {
                $redirectParams['mode'] = 'create';
                $redirectParams['flash'] = 'Component saved. The form is ready for the next component.';
            } else {
                $redirectParams['mode'] = 'edit';
                $redirectParams['component_id'] = $componentId;
            }

            header('Location: ' . buildComponentsUrl($redirectParams));
            exit;
        } elseif ($_POST['action'] === 'submit_for_approval') {
            $componentId = (int)($_POST['component_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE components SET status = 'pending_approval' WHERE id = ? AND created_by = ? AND status = 'draft'");
            $stmt->execute([$componentId, $uid]);
            if ($stmt->rowCount() > 0) {
                logAudit($pdo, 'submit_component', 'component', $componentId, 'Submitted for approval');
                $msg = 'Component submitted for approval.';
            } else {
                $msg = 'Only your draft components can be submitted.';
                $msgType = 'error';
            }
            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => $msgType === 'error' ? 'error' : 'success',
            ]));
            exit;
        } elseif ($_POST['action'] === 'bulk_submit_for_approval') {
            $componentIds = array_values(array_unique(array_filter(array_map('intval', $_POST['component_ids'] ?? []))));
            if (!$componentIds) {
                throw new RuntimeException('Select at least one draft component to submit.');
            }

            $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
            $selectStmt = $pdo->prepare("
                SELECT id, name, part_number
                FROM components
                WHERE created_by = ? AND status = 'draft' AND id IN ($placeholders)
            ");
            $selectStmt->execute(array_merge([$uid], $componentIds));
            $draftComponents = $selectStmt->fetchAll();

            if (!$draftComponents) {
                throw new RuntimeException('No draft components were available for bulk submit.');
            }

            $draftIds = array_map(static fn(array $component): int => (int)$component['id'], $draftComponents);
            $draftPlaceholders = implode(',', array_fill(0, count($draftIds), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE components
                SET status = 'pending_approval'
                WHERE created_by = ? AND status = 'draft' AND id IN ($draftPlaceholders)
            ");
            $updateStmt->execute(array_merge([$uid], $draftIds));

            foreach ($draftComponents as $component) {
                logAudit(
                    $pdo,
                    'submit_component',
                    'component',
                    (int)$component['id'],
                    'Submitted for approval: ' . ($component['name'] ?: $component['part_number'])
                );
            }

            $submittedCount = count($draftComponents);
            $msg = $submittedCount === 1
                ? '1 component submitted for approval.'
                : $submittedCount . ' components submitted for approval.';

            header('Location: ' . buildComponentsUrl([
                'flash' => $msg,
                'type' => 'success',
            ]));
            exit;
        }
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }
        $msg = $e->getMessage();
        $msgType = 'error';
    }
}

if (isset($_GET['flash']) && $_GET['flash'] !== '') {
    $msg = (string)$_GET['flash'];
    $msgType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
}

$statusFilter = $_GET['status'] ?? '';
$search = trim((string)($_GET['search'] ?? ''));
$sql = "
    SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
    FROM components c
    LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.created_by = ?
";
$params = [$uid];
if ($statusFilter !== '') {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.part_number LIKE ? OR c.electava_part_number LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll();
$componentUploads = loadComponentUploads($pdo, array_map(fn($component) => (int)$component['id'], $components));
foreach ($components as &$component) {
    $component['specifications_array'] = specificationsArray($component['specifications'] ?? null);
    $component['asset_links_array'] = assetLinksArray($component['asset_links'] ?? null);
    $component['quantity_breaks_array'] = quantityBreaksArray($component['quantity_breaks'] ?? null);
    $component['uploads'] = $componentUploads[(int)$component['id']] ?? ['documents' => [], 'images' => [], 'cad' => []];
}
unset($component);

$manufacturers = $pdo->query("SELECT id, name FROM manufacturers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$totalComp = count($components);
$draftCount = count(array_filter($components, fn($component) => $component['status'] === 'draft'));
$pendingCount = count(array_filter($components, fn($component) => $component['status'] === 'pending_approval'));
$activeCount = count(array_filter($components, fn($component) => $component['status'] === 'active'));

$pageMode = $_GET['mode'] ?? '';
if (!in_array($pageMode, ['create', 'edit', 'view'], true)) {
    $pageMode = '';
}

$selectedComponentId = (int)($_GET['component_id'] ?? 0);
$formComponent = null;
if (in_array($pageMode, ['edit', 'view'], true) && $selectedComponentId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.name AS manufacturer_name, cat.name AS category_name
        FROM components c
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.created_by = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedComponentId, $uid]);
    $formComponent = $stmt->fetch();

    if ($formComponent) {
        $formUploads = loadComponentUploads($pdo, [$selectedComponentId]);
        $formComponent['specifications_array'] = specificationsArray($formComponent['specifications'] ?? null);
        $formComponent['asset_links_array'] = assetLinksArray($formComponent['asset_links'] ?? null);
        $formComponent['quantity_breaks_array'] = quantityBreaksArray($formComponent['quantity_breaks'] ?? null);
        $formComponent['uploads'] = $formUploads[$selectedComponentId] ?? ['documents' => [], 'images' => [], 'cad' => []];
    } else {
        $pageMode = '';
        $msg = 'Component not found.';
        $msgType = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="<?= $msgType === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?> border px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?>"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">Components</h2>
        <p class="text-sm text-slate-500 mt-1">
            <?= $pageMode === '' ? 'Create, view, and edit component listings with mandatory part fields, optional spec rows, and upload areas for docs, CAD, and images.' : 'Complete the full component form on this page, then submit it or save and continue with the next component.' ?>
        </p>
    </div>
    <?php if ($pageMode === ''): ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'create'])) ?>" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium inline-flex items-center">
        <i class="fa-solid fa-plus mr-1.5"></i>New Component
    </a>
    <?php else: ?>
    <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200 inline-flex items-center">
        <i class="fa-solid fa-table-list mr-1.5"></i>Component List
    </a>
    <?php endif; ?>
</div>

<?php if ($pageMode === ''): ?>
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-white"><?= $totalComp ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Total</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-slate-300"><?= $draftCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Drafts</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-amber-400"><?= $pendingCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending</div></div>
    <div class="glass-card stat-glow p-4 rounded-2xl"><div class="text-xl font-bold text-emerald-400"><?= $activeCount ?></div><div class="text-[10px] text-slate-500 uppercase tracking-widest">Active</div></div>
</div>

<div class="flex items-center gap-3 mb-5">
    <form method="GET" class="flex-1 flex items-center gap-2">
        <div class="relative flex-1">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by part number, Electava part number, or description..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
        </div>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
        </select>
        <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
    </form>
</div>

<div class="glass-card rounded-2xl p-4 border border-slate-700/50 mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Bulk Submit for Approval</h3>
        <p id="bulkSelectionSummary" class="text-xs text-slate-500 mt-1">0 selected from <?= $draftCount ?> draft components.</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-slate-300 <?= $draftCount === 0 ? 'opacity-50' : '' ?>">
            <input type="checkbox" id="selectAllDrafts" class="rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40" <?= $draftCount === 0 ? 'disabled' : '' ?>>
            Select All Drafts
        </label>
        <form id="bulkApprovalForm" method="POST">
            <input type="hidden" name="action" value="bulk_submit_for_approval">
            <button type="submit" id="bulkSubmitButton" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed" <?= $draftCount === 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane mr-1.5"></i>Submit Selected
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($components as $component): ?>
    <?php
    $payload = [
        'id' => (int)$component['id'],
        'part_number' => $component['part_number'],
        'electava_part_number' => $component['electava_part_number'] ?: $component['part_number'],
        'name' => $component['name'],
        'description' => $component['description'],
        'manufacturer_id' => $component['manufacturer_id'] ? (int)$component['manufacturer_id'] : '',
        'category_id' => $component['category_id'] ? (int)$component['category_id'] : '',
        'price' => (string)$component['price'],
        'quantity_breaks' => $component['quantity_breaks_array'],
        'stock' => (string)$component['stock'],
        'datasheet_url' => $component['datasheet_url'],
        'symbol_file' => $component['symbol_file'],
        'footprint_file' => $component['footprint_file'],
        'step_file' => $component['step_file'],
        'image_url' => $component['image_url'],
        'specifications' => $component['specifications_array'],
        'asset_links' => $component['asset_links_array'],
        'uploads' => $component['uploads'],
    ];
    ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex items-start gap-3 min-w-0">
                <?php if (!empty($component['image_url'])): ?>
                <img src="<?= htmlspecialchars($component['image_url']) ?>" alt="<?= htmlspecialchars($component['name']) ?>" class="w-12 h-12 rounded-xl object-cover border border-slate-700/70 bg-slate-900/60">
                <?php else: ?>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/15 to-emerald-500/15 flex items-center justify-center border border-blue-500/20 shrink-0"><i class="fa-solid fa-microchip text-blue-400"></i></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate font-mono"><?= htmlspecialchars($component['part_number']) ?></h3>
                    <div class="text-[11px] text-cyan-300 font-mono mt-1">Electava: <?= htmlspecialchars($component['electava_part_number'] ?: $component['part_number']) ?></div>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <?php if ($component['status'] === 'draft'): ?>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300 bg-slate-900/50 border border-slate-700/70 rounded-lg px-2 py-1.5">
                    <input
                        type="checkbox"
                        name="component_ids[]"
                        value="<?= (int)$component['id'] ?>"
                        form="bulkApprovalForm"
                        class="bulk-component-checkbox rounded border-slate-600 bg-slate-900/60 text-emerald-500 focus:ring-emerald-500/40"
                    >
                    Select
                </label>
                <?php endif; ?>
                <?= statusBadge($component['status']) ?>
            </div>
        </div>
        <p class="text-xs text-slate-400 mb-3 min-h-[38px]"><?= htmlspecialchars($component['description']) ?></p>
        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Mfr:</span> <span class="text-slate-300"><?= htmlspecialchars($component['manufacturer_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Cat:</span> <span class="text-slate-300"><?= htmlspecialchars($component['category_name'] ?? '-') ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Price:</span> <span class="text-emerald-400">INR <?= number_format((float)$component['price'], 2) ?></span></div>
            <div class="bg-slate-800/30 px-2 py-1.5 rounded-lg"><span class="text-slate-500">Stock:</span> <span class="text-white"><?= number_format((int)$component['stock']) ?></span></div>
        </div>
        <?php if (!empty($component['quantity_breaks_array'])): ?>
        <div class="mb-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-3 py-3">
            <div class="text-[11px] uppercase tracking-widest text-emerald-300 mb-2">Tier Pricing</div>
            <div class="flex flex-wrap gap-2">
                <?php foreach (array_slice($component['quantity_breaks_array'], 0, 4) as $tier): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-200">
                    <?= (int)$tier['qty'] ?> qty - INR <?= number_format((float)$tier['price'], 2) ?>
                </span>
                <?php endforeach; ?>
                <?php if (count($component['quantity_breaks_array']) > 4): ?>
                <span class="px-2 py-1 rounded-lg text-[11px] bg-slate-900/50 border border-slate-700/70 text-slate-400">
                    +<?= count($component['quantity_breaks_array']) - 4 ?> more
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach (['datasheet_url' => 'Doc', 'symbol_file' => 'Symbol', 'footprint_file' => 'Footprint', 'step_file' => '3D CAD', 'image_url' => 'Image'] as $column => $label): ?>
                <?php if (!empty($component[$column])): ?><span class="px-2 py-1 rounded-lg text-[11px] bg-cyan-500/10 text-cyan-300 border border-cyan-500/20"><?= $label ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'view', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-eye mr-1"></i>View</a>
            <a href="<?= htmlspecialchars(buildComponentsUrl(['mode' => 'edit', 'component_id' => (int)$component['id']])) ?>" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 inline-flex items-center"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</a>
            <?php if ($component['status'] === 'draft'): ?>
            <form method="POST" class="flex-1">
                <input type="hidden" name="action" value="submit_for_approval">
                <input type="hidden" name="component_id" value="<?= (int)$component['id'] ?>">
                <button class="w-full text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-2 rounded-lg hover:bg-emerald-600/40 transition font-medium"><i class="fa-solid fa-paper-plane mr-1"></i>Submit for Approval</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="text-[10px] text-slate-600 mt-3"><?= timeAgo($component['created_at']) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($components)): ?>
    <div class="col-span-full glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4"><i class="fa-solid fa-microchip text-slate-600 text-2xl"></i></div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Components Yet</h3>
        <p class="text-sm text-slate-600">Add your first component with required fields and optional uploads.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($pageMode !== ''): ?>
<div class="glass-card rounded-3xl p-6 lg:p-8 shadow-2xl border border-slate-700/50 mt-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div>
                <h3 id="componentModalTitle" class="text-lg font-semibold text-white"><i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component</h3>
                <p class="text-xs text-slate-500 mt-1">Part Number, Electava Part Number, Manufacturer, and Description are mandatory. Optional rows can be added or deleted.</p>
            </div>
        </div>
        <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">
            <i class="fa-solid fa-arrow-left mr-1.5"></i>Back to List
        </a>
    </div>
    <form id="componentForm" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="action" value="save_component">
            <input type="hidden" name="component_id" id="component_id" value="0">
            <input type="hidden" name="name" id="name" value="">
            <input type="hidden" name="price" id="price" value="0">
            <input type="hidden" name="datasheet_url_text" id="datasheet_url_text" value="">
            <div class="grid lg:grid-cols-2 gap-5">
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-4">Main Details</h4>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div><label class="block text-xs text-slate-400 mb-1.5">Part Number *</label><input type="text" name="part_number" id="part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Electava Part Number *</label><input type="text" name="electava_part_number" id="electava_part_number" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Manufacturer *</label>
                                    <button type="button" data-reference-trigger="manufacturer" onclick="openReferenceModal('manufacturer')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Manufacturer
                                    </button>
                                </div>
                                <select name="manufacturer_id" id="manufacturer_id" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Select manufacturer</option>
                                    <?php foreach ($manufacturers as $manufacturer): ?><option value="<?= (int)$manufacturer['id'] ?>"><?= htmlspecialchars($manufacturer['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="block text-xs text-slate-400 mb-1.5">Stock</label><input type="number" name="stock" id="stock" min="0" value="0" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                        </div>
                        <div class="mt-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs text-slate-400">Category</label>
                                    <button type="button" data-reference-trigger="category" onclick="openReferenceModal('category')" class="text-[11px] text-emerald-300 hover:text-emerald-200">
                                        <i class="fa-solid fa-plus mr-1"></i>Add Category
                                    </button>
                                </div>
                                <select name="category_id" id="category_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="">Optional</option>
                                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-slate-900/35 border border-slate-800 p-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-sm font-medium text-white">Quantity Based Pricing</div>
                                    <div class="text-[11px] text-slate-500 mt-1">Add different prices for 1 qty, 10 qty, 50 qty, or any other quantity break.</div>
                                </div>
                                <button type="button" data-pricing-add onclick="addPricingRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200 whitespace-nowrap">
                                    <i class="fa-solid fa-plus mr-1"></i>Add Pricing
                                </button>
                            </div>
                            <div id="pricingRows" class="space-y-3"></div>
                        </div>
                        <div class="mt-4"><label class="block text-xs text-slate-400 mb-1.5">Description *</label><textarea name="description" id="description" rows="4" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-white">Optional Specifications</h4>
                            <button type="button" onclick="addSpecRow('', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Row</button>
                        </div>
                        <div id="specRows" class="space-y-3"></div>
                    </div>
                </section>
                <section class="space-y-4">
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Assets Options</h4>
                                <p class="text-[11px] text-slate-500 mt-1">Add labels with URLs or upload local files for documents, images, and EDA/CAD models.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Documents</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload local documents.</div>
                                    </div>
                                    <button type="button" data-link-add="documents" onclick="addLinkRow('documents', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="documentLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Documents</label>
                                    <input type="file" name="document_uploads[]" id="document_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['documents']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">Images</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use an image URL or upload local images.</div>
                                    </div>
                                    <button type="button" data-link-add="images" onclick="addLinkRow('images', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="imageLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local Images</label>
                                    <input type="file" name="image_uploads[]" id="image_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['images']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>

                            <div class="bg-slate-900/35 border border-slate-800 rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="text-sm font-medium text-white">EDA/CAD Models</div>
                                        <div class="text-[11px] text-slate-500 mt-1">Add a label, then use a URL or upload ZIP / EDA / CAD model files.</div>
                                    </div>
                                    <button type="button" data-link-add="cad" onclick="addLinkRow('cad', '', '')" class="btn-secondary px-3 py-2 rounded-lg text-xs text-slate-200"><i class="fa-solid fa-plus mr-1"></i>Add Item</button>
                                </div>
                                <div id="cadLinkRows" class="space-y-2"></div>
                                <div class="mt-3">
                                    <label class="block text-xs text-slate-400 mb-1.5">Upload Local EDA/CAD ZIP Files</label>
                                    <input type="file" name="cad_uploads[]" id="cad_uploads" multiple accept="<?= htmlspecialchars($multiUploadConfig['cad']['accept']) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-white">Current Assets</h4>
                                <p class="text-[11px] text-slate-500 mt-1">See current primary assets, uploaded files, and remove options here.</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs">
                            <?php foreach ($assetConfig as $column => $config): ?>
                            <div id="asset-<?= htmlspecialchars($column) ?>" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-slate-300 font-medium">Primary <?= htmlspecialchars($config['label']) ?></div>
                                        <a id="asset-link-<?= htmlspecialchars($column) ?>" href="#" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline"></a>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-red-300"><input type="checkbox" name="remove_<?= htmlspecialchars($column) ?>" id="remove_<?= htmlspecialchars($column) ?>" value="1">Remove</label>
                                </div>
                                <?php if ($column === 'image_url'): ?><img id="asset-preview-image_url" src="" alt="Current image" class="hidden mt-3 w-20 h-20 rounded-xl object-cover border border-slate-700/80"><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <div id="uploadedAssetsPanel" class="hidden bg-slate-900/40 border border-slate-800 rounded-xl px-3 py-3">
                                <div class="text-slate-300 font-medium mb-3">Uploaded Extras</div>
                                <div id="uploadedAssetsList" class="space-y-2"></div>
                            </div>
                            <div id="currentAssetsEmptyState" class="bg-slate-900/35 border border-dashed border-slate-700/70 rounded-xl px-3 py-3 text-slate-500">
                                Current files will appear here after you upload or save assets.
                            </div>
                        </div>
                    </div>
                    <div id="previewPanel" class="hidden glass-panel rounded-2xl p-4 border border-slate-800/60">
                        <h4 class="text-sm font-semibold text-white mb-3">View Summary</h4>
                        <div id="previewContent" class="text-sm text-slate-300 space-y-2"></div>
                    </div>
                </section>
            </div>
            <div class="flex flex-wrap justify-end gap-3 pt-2 border-t border-slate-800/70">
                <button type="button" id="previewToggleButton" onclick="togglePreview()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-200"><i class="fa-solid fa-eye mr-1"></i>View</button>
                <a href="<?= htmlspecialchars(buildComponentsUrl()) ?>" id="componentCancelButton" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300 inline-flex items-center">Cancel</a>
                <button type="submit" id="componentNextButton" name="save_intent" value="next" class="btn-secondary px-5 py-2 rounded-lg text-sm text-slate-100 font-medium">Next</button>
                <button type="submit" id="componentSubmitButton" name="save_intent" value="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
    </form>
</div>
<?php endif; ?>

<div id="referenceModal" class="hidden fixed inset-0 modal-backdrop z-[60] flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 id="referenceModalTitle" class="text-lg font-semibold text-white">Add Reference</h3>
                <p id="referenceModalSubtitle" class="text-xs text-slate-500 mt-1">Save a reusable option for employee component entries.</p>
            </div>
            <button type="button" onclick="closeReferenceModal()" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="referenceForm" class="space-y-4">
            <input type="hidden" name="action" value="create_reference">
            <input type="hidden" name="reference_type" id="reference_type" value="">
            <div>
                <label id="referenceNameLabel" class="block text-xs text-slate-400 mb-1.5">Name</label>
                <input type="text" name="reference_name" id="reference_name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="">
            </div>
            <div id="referenceWebsiteRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Website</label>
                <input type="url" name="reference_website" id="reference_website" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="https://manufacturer-site.com">
            </div>
            <div id="referenceParentRow" class="hidden">
                <label class="block text-xs text-slate-400 mb-1.5">Parent Category</label>
                <select name="reference_parent_id" id="reference_parent_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                    <option value="0">No parent</option>
                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="referenceFeedback" class="hidden text-sm rounded-xl px-3 py-2"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeReferenceModal()" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button type="submit" id="referenceSubmitButton" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const initialComponentData = <?= json_encode($formComponent ? [
    'id' => (int)$formComponent['id'],
    'part_number' => $formComponent['part_number'],
    'electava_part_number' => $formComponent['electava_part_number'] ?: $formComponent['part_number'],
    'name' => $formComponent['name'],
    'description' => $formComponent['description'],
    'manufacturer_id' => $formComponent['manufacturer_id'] ? (int)$formComponent['manufacturer_id'] : '',
    'category_id' => $formComponent['category_id'] ? (int)$formComponent['category_id'] : '',
    'price' => (string)$formComponent['price'],
    'quantity_breaks' => $formComponent['quantity_breaks_array'],
    'stock' => (string)$formComponent['stock'],
    'datasheet_url' => $formComponent['datasheet_url'],
    'symbol_file' => $formComponent['symbol_file'],
    'footprint_file' => $formComponent['footprint_file'],
    'step_file' => $formComponent['step_file'],
    'image_url' => $formComponent['image_url'],
    'specifications' => $formComponent['specifications_array'],
    'asset_links' => $formComponent['asset_links_array'],
    'uploads' => $formComponent['uploads'],
] : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const pageMode = <?= json_encode($pageMode) ?>;
const specRows = document.getElementById('specRows');
const pricingRows = document.getElementById('pricingRows');
const componentForm = document.getElementById('componentForm');
const previewPanel = document.getElementById('previewPanel');
const previewContent = document.getElementById('previewContent');
const previewToggleButton = document.getElementById('previewToggleButton');
const componentSubmitButton = document.getElementById('componentSubmitButton');
const componentNextButton = document.getElementById('componentNextButton');
const componentModalTitle = document.getElementById('componentModalTitle');
const referenceModal = document.getElementById('referenceModal');
const referenceForm = document.getElementById('referenceForm');
const referenceModalTitle = document.getElementById('referenceModalTitle');
const referenceModalSubtitle = document.getElementById('referenceModalSubtitle');
const referenceNameLabel = document.getElementById('referenceNameLabel');
const referenceNameInput = document.getElementById('reference_name');
const referenceWebsiteRow = document.getElementById('referenceWebsiteRow');
const referenceParentRow = document.getElementById('referenceParentRow');
const referenceFeedback = document.getElementById('referenceFeedback');
const referenceSubmitButton = document.getElementById('referenceSubmitButton');
const uploadedAssetsPanel = document.getElementById('uploadedAssetsPanel');
const uploadedAssetsList = document.getElementById('uploadedAssetsList');
const currentAssetsEmptyState = document.getElementById('currentAssetsEmptyState');
const selectAllDrafts = document.getElementById('selectAllDrafts');
const bulkSubmitButton = document.getElementById('bulkSubmitButton');
const bulkSelectionSummary = document.getElementById('bulkSelectionSummary');
const bulkComponentCheckboxes = Array.from(document.querySelectorAll('.bulk-component-checkbox'));
const uploadInputMap = { documents: 'document_uploads', images: 'image_uploads', cad: 'cad_uploads' };
let currentUploadedAssets = { documents: [], images: [], cad: [] };

function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function createSpecRow(key = '', value = '') {
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[1fr,1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="spec_key[]" value="${escapeHtml(key)}" placeholder="Specification name" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="text" name="spec_value[]" value="${escapeHtml(value)}" placeholder="Value" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}
function addSpecRow(key = '', value = '') { specRows.appendChild(createSpecRow(key, value)); }
function resetSpecRows(specifications = []) {
    if (!specRows) return;
    specRows.innerHTML = '';
    if (!specifications.length) { addSpecRow('', ''); return; }
    specifications.forEach(([key, value]) => addSpecRow(key, value));
}

function createPricingPlaceholder() {
    const empty = document.createElement('div');
    empty.className = 'pricing-empty-state text-xs text-slate-500 border border-dashed border-slate-700/70 rounded-xl px-3 py-3';
    empty.textContent = 'No quantity pricing added yet. Use Add Pricing for 1 qty, 10 qty, 50 qty, or more.';
    return empty;
}

function syncPricingPlaceholder() {
    if (!pricingRows) return;
    const rowCount = pricingRows.querySelectorAll('[data-pricing-row]').length;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (rowCount === 0 && !placeholder) {
        pricingRows.appendChild(createPricingPlaceholder());
    }
    if (rowCount > 0 && placeholder) {
        placeholder.remove();
    }
}

function createPricingRow(qty = '', price = '') {
    const row = document.createElement('div');
    row.dataset.pricingRow = 'true';
    row.className = 'grid grid-cols-[minmax(0,160px),minmax(0,1fr),auto] gap-3 items-center';
    row.innerHTML = `<input type="number" name="tier_qty[]" min="1" step="1" value="${escapeHtml(qty)}" placeholder="Quantity" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="number" name="tier_price[]" min="0" step="0.01" value="${escapeHtml(price)}" placeholder="Price in INR" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        syncPricingPlaceholder();
    });
    return row;
}

function addPricingRow(qty = '', price = '') {
    if (!pricingRows) return;
    const placeholder = pricingRows.querySelector('.pricing-empty-state');
    if (placeholder) {
        placeholder.remove();
    }
    pricingRows.appendChild(createPricingRow(qty, price));
}

function resetPricingRows(entries = []) {
    if (!pricingRows) return;
    pricingRows.innerHTML = '';
    if (!entries.length) {
        syncPricingPlaceholder();
        return;
    }
    entries.forEach((entry) => addPricingRow(entry.qty || entry.quantity || '', entry.price ?? ''));
    syncPricingPlaceholder();
}

function createLinkRow(group, label = '', url = '') {
    const fieldMap = { documents: 'document', images: 'image', cad: 'cad' };
    const fieldPrefix = fieldMap[group] || group;
    const labelPlaceholders = { documents: 'Document label', images: 'Image label', cad: 'EDA/CAD label' };
    const urlPlaceholders = { documents: 'Document URL', images: 'Image URL', cad: 'EDA/CAD URL' };
    const row = document.createElement('div');
    row.className = 'grid grid-cols-[minmax(0,180px),1fr,auto] gap-3 items-center';
    row.innerHTML = `<input type="text" name="${fieldPrefix}_link_label[]" value="${escapeHtml(label)}" placeholder="${escapeHtml(labelPlaceholders[group] || 'Label')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><input type="url" name="${fieldPrefix}_link_url[]" value="${escapeHtml(url)}" placeholder="${escapeHtml(urlPlaceholders[group] || 'URL')}" class="input-field w-full px-3 py-2 rounded-lg text-sm"><button type="button" class="btn-danger px-3 py-2 rounded-lg text-xs whitespace-nowrap"><i class="fa-solid fa-trash mr-1"></i>Delete</button>`;
    row.querySelector('button').addEventListener('click', () => row.remove());
    return row;
}

function addLinkRow(group, label = '', url = '') {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    document.getElementById(map[group]).appendChild(createLinkRow(group, label, url));
}

function resetLinkRows(group, entries = []) {
    const map = { documents: 'documentLinkRows', images: 'imageLinkRows', cad: 'cadLinkRows' };
    const container = document.getElementById(map[group]);
    if (!container) return;
    container.innerHTML = '';
    if (!entries.length) {
        addLinkRow(group, '', '');
        return;
    }
    entries.forEach((entry) => addLinkRow(group, entry.label || '', entry.url || ''));
}

function clearUploads() {
    ['document_uploads', 'image_uploads', 'cad_uploads'].forEach((id) => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });
}

function formatFileSize(bytes = 0) {
    const size = Number(bytes) || 0;
    if (size <= 0) return '';
    if (size >= 1024 * 1024) return `${(size / (1024 * 1024)).toFixed(2)} MB`;
    if (size >= 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${size} bytes`;
}

function getSelectedUploadEntries() {
    return Object.entries(uploadInputMap).reduce((entries, [group, inputId]) => {
        const input = document.getElementById(inputId);
        const files = input?.files ? Array.from(input.files) : [];
        entries[group] = files.map((file, index) => ({
            id: `pending-${group}-${index}-${file.name}`,
            name: file.name,
            pending: true,
            note: formatFileSize(file.size),
        }));
        return entries;
    }, { documents: [], images: [], cad: [] });
}

function refreshCurrentAssetsState() {
    const primaryVisible = ['datasheet_url', 'symbol_file', 'footprint_file', 'step_file', 'image_url']
        .map((column) => document.getElementById(`asset-${column}`))
        .filter((block) => block && !block.classList.contains('hidden')).length;
    const uploadedVisible = uploadedAssetsList ? uploadedAssetsList.children.length : 0;

    if (uploadedAssetsPanel) {
        uploadedAssetsPanel.classList.toggle('hidden', uploadedVisible === 0);
    }

    if (currentAssetsEmptyState) {
        currentAssetsEmptyState.classList.toggle('hidden', primaryVisible > 0 || uploadedVisible > 0);
    }
}

function resetAssetBlock(column) {
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    const checkbox = document.getElementById(`remove_${column}`);
    if (!block || !link || !checkbox) return;
    block.classList.add('hidden'); link.textContent = ''; link.href = '#'; checkbox.checked = false;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.classList.add('hidden'); img.src = ''; }
    refreshCurrentAssetsState();
}

function setAssetBlock(column, value) {
    resetAssetBlock(column);
    if (!value) return;
    const block = document.getElementById(`asset-${column}`);
    const link = document.getElementById(`asset-link-${column}`);
    if (!block || !link) return;
    block.classList.remove('hidden'); link.textContent = value; link.href = value;
    if (column === 'image_url') { const img = document.getElementById('asset-preview-image_url'); img.src = value; img.classList.remove('hidden'); }
    refreshCurrentAssetsState();
}

function setFormDisabled(disabled) {
    if (!componentForm) return;
    componentForm.querySelectorAll('input, textarea, select').forEach((field) => { if (field.type !== 'hidden') field.disabled = disabled; });
    if (specRows) {
        specRows.querySelectorAll('button').forEach((button) => { button.disabled = disabled; button.classList.toggle('opacity-50', disabled); });
    }
    if (pricingRows) {
        pricingRows.querySelectorAll('button, input').forEach((control) => {
            control.disabled = disabled;
            control.classList.toggle('opacity-50', disabled && control.tagName === 'BUTTON');
        });
    }
    document.querySelectorAll('[data-reference-trigger]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-pricing-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('[data-link-add]').forEach((button) => {
        button.disabled = disabled;
        button.classList.toggle('opacity-50', disabled);
        button.classList.toggle('pointer-events-none', disabled);
    });
    document.querySelectorAll('#documentLinkRows button, #imageLinkRows button, #cadLinkRows button, #uploadedAssetsList input[type="checkbox"]').forEach((control) => {
        control.disabled = disabled;
    });
}

function renderUploadedAssets(uploads = {}) {
    if (!uploadedAssetsList || !uploadedAssetsPanel) return;
    currentUploadedAssets = {
        documents: Array.isArray(uploads.documents) ? uploads.documents : [],
        images: Array.isArray(uploads.images) ? uploads.images : [],
        cad: Array.isArray(uploads.cad) ? uploads.cad : [],
    };
    uploadedAssetsList.innerHTML = '';
    const groupLabels = { documents: 'Documents', images: 'Images', cad: 'EDA/CAD' };
    const selectedUploads = getSelectedUploadEntries();
    ['documents', 'images', 'cad'].forEach((group) => {
        currentUploadedAssets[group].forEach((file) => {
            const item = document.createElement('label');
            item.className = 'flex items-center justify-between gap-3 bg-slate-950/40 border border-slate-800 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-200 truncate">${escapeHtml(file.name)}</div></div><a href="${escapeHtml(file.path)}" target="_blank" rel="noreferrer" class="text-cyan-300 break-all hover:underline">${escapeHtml(file.path)}</a></div><span class="inline-flex items-center gap-2 text-red-300 shrink-0"><input type="checkbox" name="remove_file_ids[]" value="${escapeHtml(file.id)}">Remove</span>`;
            uploadedAssetsList.appendChild(item);
        });
        selectedUploads[group].forEach((file) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl px-3 py-2';
            item.innerHTML = `<div class="min-w-0"><div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-lg text-[10px] uppercase tracking-widest bg-cyan-500/10 border border-cyan-500/20 text-cyan-300">${escapeHtml(groupLabels[group] || group)}</span><div class="text-slate-100 truncate">${escapeHtml(file.name)}</div></div><div class="text-cyan-200 break-all">Selected now${file.note ? ` · ${escapeHtml(file.note)}` : ''}</div></div><span class="text-[11px] text-cyan-300 shrink-0">Ready to upload</span>`;
            uploadedAssetsList.appendChild(item);
        });
    });
    refreshCurrentAssetsState();
}

function buildPreview() {
    if (!componentForm || !previewContent) return;
    const manufacturer = document.getElementById('manufacturer_id').selectedOptions[0]?.text || '-';
    const category = document.getElementById('category_id').selectedOptions[0]?.text || '-';
    const specEntries = Array.from(document.querySelectorAll('input[name="spec_key[]"]')).map((input, index) => [input.value.trim(), document.querySelectorAll('input[name="spec_value[]"]')[index].value.trim()]).filter(([key, value]) => key || value);
    const pricingEntries = Array.from(document.querySelectorAll('input[name="tier_qty[]"]')).map((input, index) => ({
        qty: input.value.trim(),
        price: document.querySelectorAll('input[name="tier_price[]"]')[index].value.trim(),
    })).filter((entry) => entry.qty !== '' || entry.price !== '');
    const docLinks = Array.from(document.querySelectorAll('input[name="document_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const imageLinks = Array.from(document.querySelectorAll('input[name="image_link_url[]"]')).filter((input) => input.value.trim() !== '');
    const cadLinks = Array.from(document.querySelectorAll('input[name="cad_link_url[]"]')).filter((input) => input.value.trim() !== '');
    previewContent.innerHTML = `<div><span class="text-slate-500">Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('part_number').value || '-')}</span></div><div><span class="text-slate-500">Electava Part Number:</span> <span class="text-white">${escapeHtml(document.getElementById('electava_part_number').value || '-')}</span></div><div><span class="text-slate-500">Manufacturer:</span> <span class="text-white">${escapeHtml(manufacturer)}</span></div><div><span class="text-slate-500">Category:</span> <span class="text-white">${escapeHtml(category === 'Optional' ? '-' : category)}</span></div><div><span class="text-slate-500">Quantity Pricing:</span> <div class="mt-1">${pricingEntries.length ? pricingEntries.map((entry) => `<div class="text-white">${escapeHtml(entry.qty || '-')} qty: INR ${escapeHtml(entry.price || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div><div><span class="text-slate-500">Description:</span> <span class="text-white">${escapeHtml(document.getElementById('description').value || '-')}</span></div><div><span class="text-slate-500">Asset Entries:</span> <span class="text-white">Docs ${docLinks.length}, Images ${imageLinks.length}, EDA/CAD ${cadLinks.length}</span></div><div><span class="text-slate-500">Uploads Selected:</span> <span class="text-white">Docs ${document.getElementById('document_uploads').files.length}, Images ${document.getElementById('image_uploads').files.length}, EDA/CAD ${document.getElementById('cad_uploads').files.length}</span></div><div><span class="text-slate-500">Specifications:</span> <div class="mt-1">${specEntries.length ? specEntries.map(([key, value]) => `<div class="text-white">${escapeHtml(key)}: ${escapeHtml(value || '-')}</div>`).join('') : '<div class="text-white">-</div>'}</div></div>`;
}

function togglePreview(forceOpen = null) {
    if (!previewPanel || !previewToggleButton) return;
    const open = forceOpen === null ? previewPanel.classList.contains('hidden') : forceOpen;
    if (open) { buildPreview(); previewPanel.classList.remove('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye-slash mr-1"></i>Hide View'; }
    else { previewPanel.classList.add('hidden'); previewToggleButton.innerHTML = '<i class="fa-solid fa-eye mr-1"></i>View'; }
}

function populateComponentForm(mode = 'create', component = null) {
    if (!componentForm) return;

    componentForm.reset();
    clearUploads();
    resetSpecRows([]);
    resetPricingRows([]);
    resetLinkRows('documents', []);
    resetLinkRows('images', []);
    resetLinkRows('cad', []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach(resetAssetBlock);
    renderUploadedAssets({});
    togglePreview(false);

    document.getElementById('component_id').value = component?.id || 0;
    document.getElementById('part_number').value = component?.part_number || '';
    document.getElementById('electava_part_number').value = component?.electava_part_number || '';
    document.getElementById('name').value = component?.part_number || component?.name || '';
    document.getElementById('description').value = component?.description || '';
    document.getElementById('manufacturer_id').value = component?.manufacturer_id || '';
    document.getElementById('category_id').value = component?.category_id || '';
    document.getElementById('price').value = component?.price || '0';
    resetPricingRows(component?.quantity_breaks?.length ? component.quantity_breaks : (component?.price ? [{ qty: 1, price: component.price }] : []));
    document.getElementById('stock').value = component?.stock || '0';
    document.getElementById('datasheet_url_text').value = component?.datasheet_url && !String(component.datasheet_url).startsWith('/uploads/') ? component.datasheet_url : '';
    resetSpecRows(component?.specifications ? Object.entries(component.specifications) : []);
    resetLinkRows('documents', component?.asset_links?.documents || []);
    resetLinkRows('images', component?.asset_links?.images || []);
    resetLinkRows('cad', component?.asset_links?.cad || []);
    ['datasheet_url','symbol_file','footprint_file','step_file','image_url'].forEach((column) => setAssetBlock(column, component?.[column] || ''));
    renderUploadedAssets(component?.uploads || {});

    const viewOnly = mode === 'view';
    setFormDisabled(viewOnly);
    if (componentSubmitButton) componentSubmitButton.classList.toggle('hidden', viewOnly);
    if (componentNextButton) componentNextButton.classList.toggle('hidden', viewOnly);
    if (previewToggleButton) previewToggleButton.classList.remove('hidden');
    if (componentModalTitle) {
        componentModalTitle.innerHTML = viewOnly
            ? '<i class="fa-solid fa-eye text-blue-400 mr-2"></i>View Component'
            : mode === 'edit'
                ? '<i class="fa-solid fa-pen-to-square text-blue-400 mr-2"></i>Edit Component'
                : '<i class="fa-solid fa-microchip text-blue-400 mr-2"></i>New Component';
    }
    if (viewOnly) {
        togglePreview(true);
    }
}

if (componentForm) {
    populateComponentForm(pageMode || 'create', initialComponentData);
    componentForm.addEventListener('input', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    componentForm.addEventListener('change', () => { if (previewPanel && !previewPanel.classList.contains('hidden')) buildPreview(); });
    Object.values(uploadInputMap).forEach((inputId) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener('change', () => {
            renderUploadedAssets(currentUploadedAssets);
        });
    });
}

function updateBulkSelectionState() {
    const total = bulkComponentCheckboxes.length;
    const selected = bulkComponentCheckboxes.filter((checkbox) => checkbox.checked).length;

    if (bulkSelectionSummary) {
        bulkSelectionSummary.textContent = `${selected} selected from ${total} draft components.`;
    }
    if (bulkSubmitButton) {
        bulkSubmitButton.disabled = selected === 0;
    }
    if (selectAllDrafts) {
        selectAllDrafts.checked = total > 0 && selected === total;
        selectAllDrafts.indeterminate = selected > 0 && selected < total;
    }
}

if (selectAllDrafts) {
    selectAllDrafts.addEventListener('change', () => {
        bulkComponentCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllDrafts.checked;
        });
        updateBulkSelectionState();
    });
}

bulkComponentCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', updateBulkSelectionState);
});

updateBulkSelectionState();

function showReferenceFeedback(message, isError = false) {
    referenceFeedback.textContent = message;
    referenceFeedback.className = `${isError ? 'bg-red-500/10 border border-red-500/20 text-red-300' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300'} text-sm rounded-xl px-3 py-2`;
    referenceFeedback.classList.remove('hidden');
}
function closeReferenceModal() {
    referenceModal.classList.add('hidden');
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
}
function openReferenceModal(type) {
    referenceForm.reset();
    referenceFeedback.classList.add('hidden');
    document.getElementById('reference_type').value = type;
    if (type === 'manufacturer') {
        referenceModalTitle.textContent = 'Add Manufacturer';
        referenceModalSubtitle.textContent = 'Save a manufacturer name so employees can reuse it in the dropdown.';
        referenceNameLabel.textContent = 'Manufacturer Name';
        referenceNameInput.placeholder = 'e.g. Infineon';
        referenceWebsiteRow.classList.remove('hidden');
        referenceParentRow.classList.add('hidden');
    } else {
        referenceModalTitle.textContent = 'Add Category';
        referenceModalSubtitle.textContent = 'Save a category so it appears in the employee dropdown next time too.';
        referenceNameLabel.textContent = 'Category Name';
        referenceNameInput.placeholder = 'e.g. Sensors';
        referenceWebsiteRow.classList.add('hidden');
        referenceParentRow.classList.remove('hidden');
    }
    referenceModal.classList.remove('hidden');
    referenceNameInput.focus();
}
function upsertSelectOption(selectEl, optionValue, optionLabel) {
    let option = Array.from(selectEl.options).find((item) => item.value === String(optionValue));
    if (!option) {
        option = document.createElement('option');
        option.value = String(optionValue);
        option.textContent = optionLabel;
        selectEl.appendChild(option);
    }
    option.selected = true;
}
referenceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    referenceFeedback.classList.add('hidden');
    referenceSubmitButton.disabled = true;
    try {
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(referenceForm),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Unable to save reference.');
        }

        if (payload.type === 'manufacturer') {
            upsertSelectOption(document.getElementById('manufacturer_id'), payload.id, payload.name);
        } else if (payload.type === 'category') {
            upsertSelectOption(document.getElementById('category_id'), payload.id, payload.name);
            upsertSelectOption(document.getElementById('reference_parent_id'), payload.id, payload.name);
        }

        showReferenceFeedback(payload.message || 'Saved successfully.');
        setTimeout(() => closeReferenceModal(), 600);
    } catch (error) {
        showReferenceFeedback(error.message || 'Unable to save reference.', true);
    } finally {
        referenceSubmitButton.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
