<?php
$pageTitle = 'Vendor Portal';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];

// Get vendor
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

// Stats
$activeListings = $pdo->prepare("SELECT COUNT(*) FROM components WHERE vendor_id = ? AND status = 'active'");
$activeListings->execute([$uid]); $listingCount = $activeListings->fetchColumn();

$pendingOrders = 0; $shippedOrders = 0; $monthlyRevenue = 0;
if ($vendor) {
    $po1 = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ? AND status IN ('pending','confirmed','processing')");
    $po1->execute([$vendor['id']]); $pendingOrders = $po1->fetchColumn();

    $po2 = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ? AND status = 'shipped'");
    $po2->execute([$vendor['id']]); $shippedOrders = $po2->fetchColumn();

    $rev = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM purchase_orders WHERE vendor_id = ? AND status IN ('shipped','delivered') AND ordered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $rev->execute([$vendor['id']]); $monthlyRevenue = $rev->fetchColumn();
}

$lowStock = $pdo->prepare("SELECT COUNT(*) FROM components WHERE vendor_id = ? AND status = 'active' AND stock <= low_stock_threshold");
$lowStock->execute([$uid]); $lowStockCount = $lowStock->fetchColumn();

// Recent orders
$recentOrders = [];
if ($vendor) {
    $ro = $pdo->prepare("SELECT po.*, c.name as component_name, c.part_number FROM purchase_orders po LEFT JOIN components c ON po.component_id = c.id WHERE po.vendor_id = ? ORDER BY po.ordered_at DESC LIMIT 5");
    $ro->execute([$vendor['id']]); $recentOrders = $ro->fetchAll();
}

// Low stock components
$lowStockItems = $pdo->prepare("SELECT name, part_number, stock, low_stock_threshold FROM components WHERE vendor_id = ? AND status = 'active' AND stock <= low_stock_threshold ORDER BY stock ASC LIMIT 5");
$lowStockItems->execute([$uid]); $alerts = $lowStockItems->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Welcome, <?= htmlspecialchars($vendor['company_name'] ?? $_SESSION['full_name']) ?></h2>
    <p class="text-sm text-slate-500 mt-1">Your vendor portal overview.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-5 rounded-2xl relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-boxes-stacked text-emerald-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $listingCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Active Listings</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-box text-amber-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $pendingOrders ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Pending Orders</div>
        <?php if ($shippedOrders > 0): ?><div class="text-[10px] text-purple-400 mt-1"><i class="fa-solid fa-truck mr-1"></i><?= $shippedOrders ?> In Transit</div><?php endif; ?>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-star text-purple-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= number_format($vendor['rating'] ?? 0, 1) ?> <span class="text-sm text-slate-500">/ 5</span></div>
        <div class="text-xs text-slate-500 mt-0.5">Performance Rating</div>
        <div class="flex items-center gap-0.5 mt-1">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="fa-<?= $i <= round($vendor['rating'] ?? 0) ? 'solid' : 'regular' ?> fa-star text-amber-400 text-[10px]"></i>
            <?php endfor; ?>
        </div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-indian-rupee-sign text-cyan-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white">₹<?= number_format($monthlyRevenue, 0) ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Monthly Revenue</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Orders -->
    <div class="glass-card p-5 rounded-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-white">Recent Purchase Orders</h3>
            <a href="orders.php" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="space-y-3">
            <?php foreach ($recentOrders as $o): ?>
            <div class="bg-slate-800/30 p-4 rounded-xl border border-slate-700/50 flex justify-between items-center hover:border-emerald-500/20 transition">
                <div>
                    <div class="font-medium text-white text-sm"><?= htmlspecialchars($o['order_number']) ?></div>
                    <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($o['component_name'] ?? 'Unknown') ?> × <?= $o['quantity'] ?></div>
                    <div class="text-[10px] text-slate-600 mt-0.5"><?= timeAgo($o['ordered_at']) ?></div>
                </div>
                <div class="text-right">
                    <?= statusBadge($o['status']) ?>
                    <div class="text-xs text-slate-400 mt-1 font-medium">₹<?= number_format($o['total_price'], 0) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentOrders)): ?>
            <div class="text-center py-8">
                <i class="fa-solid fa-inbox text-slate-600 text-2xl mb-2"></i>
                <p class="text-sm text-slate-500">No orders yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Alerts -->
    <div class="glass-card p-5 rounded-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-white">Inventory Alerts</h3>
            <a href="inventory.php" class="text-xs text-emerald-400 hover:text-emerald-300 transition">Manage <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>

        <?php if (!empty($alerts)): ?>
        <?php foreach ($alerts as $a): ?>
        <div class="bg-<?= $a['stock'] === 0 ? 'red' : 'amber' ?>-500/5 border border-<?= $a['stock'] === 0 ? 'red' : 'amber' ?>-500/15 rounded-xl p-3 flex items-center gap-3 mb-2">
            <i class="fa-solid fa-triangle-exclamation text-<?= $a['stock'] === 0 ? 'red' : 'amber' ?>-400 shrink-0"></i>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-white truncate"><?= htmlspecialchars($a['name']) ?></div>
                <div class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($a['part_number']) ?></div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-bold text-<?= $a['stock'] === 0 ? 'red' : 'amber' ?>-400"><?= $a['stock'] ?></div>
                <div class="text-[10px] text-slate-600">/ <?= $a['low_stock_threshold'] ?> min</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="bg-emerald-500/5 border border-emerald-500/15 rounded-xl p-4 text-center">
            <i class="fa-solid fa-check-circle text-emerald-400 text-2xl mb-2"></i>
            <p class="text-sm text-emerald-400 font-medium">All stock levels healthy</p>
        </div>
        <?php endif; ?>

        <div class="mt-4 flex gap-3">
            <a href="products.php" class="flex-1 bg-slate-800/50 border border-slate-700/50 rounded-xl p-3 flex items-center justify-center gap-2 cursor-pointer hover:bg-slate-800 transition group">
                <i class="fa-solid fa-plus text-slate-500 group-hover:text-emerald-400 transition"></i>
                <span class="text-sm text-slate-400 group-hover:text-emerald-400 transition">Add Product</span>
            </a>
            <a href="inventory.php" class="flex-1 bg-slate-800/50 border border-slate-700/50 rounded-xl p-3 flex items-center justify-center gap-2 cursor-pointer hover:bg-slate-800 transition group">
                <i class="fa-solid fa-warehouse text-slate-500 group-hover:text-emerald-400 transition"></i>
                <span class="text-sm text-slate-400 group-hover:text-emerald-400 transition">Inventory</span>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
