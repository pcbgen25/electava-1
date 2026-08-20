<?php
$pageTitle = 'Inventory';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle bulk stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_stock') {
        $compId = (int)$_POST['component_id'];
        $newStock = (int)$_POST['stock'];
        $pdo->prepare("UPDATE components SET stock = ? WHERE id = ? AND vendor_id = ?")->execute([$newStock, $compId, $uid]);
        logAudit($pdo, 'vendor_update_stock', 'component', $compId, "Stock → $newStock");
        $msg = 'Stock updated.';
    } elseif ($_POST['action'] === 'update_threshold') {
        $compId = (int)$_POST['component_id'];
        $threshold = (int)$_POST['low_stock_threshold'];
        $pdo->prepare("UPDATE components SET low_stock_threshold = ? WHERE id = ? AND vendor_id = ?")->execute([$threshold, $compId, $uid]);
        $msg = 'Low stock threshold updated.';
    }
}

// Fetch all components with stock info
$sql = "SELECT c.*, m.name as manufacturer_name, cat.name as category_name 
        FROM components c 
        LEFT JOIN manufacturers m ON c.manufacturer_id = m.id 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.vendor_id = ? AND c.status IN ('active', 'pending_approval')
        ORDER BY c.stock ASC, c.name ASC";
$stmt = $pdo->prepare($sql); $stmt->execute([$uid]); $inventory = $stmt->fetchAll();

$totalItems = count($inventory);
$totalUnits = array_sum(array_column($inventory, 'stock'));
$lowStockItems = array_filter($inventory, fn($i) => $i['stock'] <= $i['low_stock_threshold']);
$outOfStock = array_filter($inventory, fn($i) => $i['stock'] === 0);
$totalValue = array_sum(array_map(fn($i) => $i['stock'] * $i['price'], $inventory));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Inventory Management</h2>
    <p class="text-sm text-slate-500 mt-1">Monitor stock levels, update quantities, and manage low-stock alerts.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-warehouse text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalItems ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Products</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-cubes text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= number_format($totalUnits) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Total Units</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-<?= count($lowStockItems) > 0 ? 'amber' : 'white' ?>-400"><?= count($lowStockItems) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Low Stock</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center"><i class="fa-solid fa-xmark text-red-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-<?= count($outOfStock) > 0 ? 'red' : 'white' ?>-400"><?= count($outOfStock) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Out of Stock</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-indian-rupee-sign text-purple-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white">₹<?= number_format($totalValue, 0) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Inventory Value</div>
    </div>
</div>

<!-- Low Stock Alerts -->
<?php if (!empty($lowStockItems)): ?>
<div class="bg-red-500/5 border border-red-500/15 rounded-2xl p-4 mb-6">
    <h3 class="text-sm font-semibold text-red-400 mb-3 flex items-center gap-2">
        <i class="fa-solid fa-bell"></i>Low Stock Alerts (<?= count($lowStockItems) ?>)
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
        <?php foreach (array_slice($lowStockItems, 0, 6) as $li): ?>
        <div class="bg-slate-900/50 rounded-xl p-3 flex items-center justify-between border border-red-500/10">
            <div>
                <div class="text-sm font-medium text-white"><?= htmlspecialchars($li['name']) ?></div>
                <div class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($li['part_number']) ?></div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold <?= $li['stock'] === 0 ? 'text-red-400' : 'text-amber-400' ?>"><?= $li['stock'] ?></div>
                <div class="text-[10px] text-slate-600">/ <?= $li['low_stock_threshold'] ?> min</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Inventory Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Component</th>
                <th class="px-5 py-4 font-semibold">Category</th>
                <th class="px-5 py-4 font-semibold">Unit Price</th>
                <th class="px-5 py-4 font-semibold">Current Stock</th>
                <th class="px-5 py-4 font-semibold">Low Threshold</th>
                <th class="px-5 py-4 font-semibold">Stock Value</th>
                <th class="px-5 py-4 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($inventory as $i): 
                $stockPct = $i['low_stock_threshold'] > 0 ? min(100, ($i['stock'] / max(1, $i['low_stock_threshold'] * 3)) * 100) : 100;
                $stockColor = $i['stock'] === 0 ? 'red' : ($i['stock'] <= $i['low_stock_threshold'] ? 'amber' : 'emerald');
            ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500/15 to-purple-500/15 flex items-center justify-center border border-blue-500/20 shrink-0">
                            <i class="fa-solid fa-microchip text-blue-400 text-xs"></i>
                        </div>
                        <div>
                            <div class="font-medium text-white text-sm"><?= htmlspecialchars($i['name']) ?></div>
                            <div class="text-[10px] text-emerald-400 font-mono"><?= htmlspecialchars($i['part_number']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($i['category_name'] ?? '—') ?></td>
                <td class="px-5 py-4 text-xs text-slate-300">₹<?= number_format($i['price'], 2) ?></td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="component_id" value="<?= $i['id'] ?>">
                        <input type="number" name="stock" value="<?= $i['stock'] ?>" class="input-field w-20 px-2 py-1 rounded text-xs text-center font-bold text-<?= $stockColor ?>-400 border-<?= $stockColor ?>-500/30" onchange="this.form.submit()">
                    </form>
                    <div class="w-20 h-1 bg-slate-800 rounded-full mt-1.5 overflow-hidden">
                        <div class="h-full bg-<?= $stockColor ?>-500/60 rounded-full transition-all" style="width:<?= $stockPct ?>%"></div>
                    </div>
                </td>
                <td class="px-5 py-4">
                    <form method="POST" class="flex items-center gap-1">
                        <input type="hidden" name="action" value="update_threshold">
                        <input type="hidden" name="component_id" value="<?= $i['id'] ?>">
                        <input type="number" name="low_stock_threshold" value="<?= $i['low_stock_threshold'] ?>" class="input-field w-16 px-2 py-1 rounded text-xs text-center" onchange="this.form.submit()">
                    </form>
                </td>
                <td class="px-5 py-4 text-xs text-slate-300">₹<?= number_format($i['stock'] * $i['price'], 0) ?></td>
                <td class="px-5 py-4">
                    <?php if ($i['stock'] === 0): ?>
                    <span class="text-xs text-red-400 bg-red-500/10 px-2 py-0.5 rounded-full border border-red-500/20 font-medium">Out of Stock</span>
                    <?php elseif ($i['stock'] <= $i['low_stock_threshold']): ?>
                    <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/20 font-medium">Low Stock</span>
                    <?php else: ?>
                    <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 font-medium">In Stock</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($inventory)): ?>
            <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500 text-sm">No active inventory items. Add products from the <a href="products.php" class="text-emerald-400 hover:underline">Products</a> page.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
