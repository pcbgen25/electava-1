<?php
$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor record
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    
    if ($_POST['action'] === 'confirm') {
        $pdo->prepare("UPDATE purchase_orders SET status = 'confirmed' WHERE id = ? AND vendor_id = ? AND status = 'pending'")->execute([$orderId, $vendor['id']]);
        logAudit($pdo, 'vendor_confirm_order', 'purchase_order', $orderId, 'Order confirmed by vendor');
        $msg = 'Order confirmed!';
    } elseif ($_POST['action'] === 'ship') {
        $tracking = trim($_POST['tracking_number'] ?? '');
        $carrier = trim($_POST['shipping_carrier'] ?? '');
        $pdo->prepare("UPDATE purchase_orders SET status = 'shipped', tracking_number = ?, shipping_carrier = ?, shipped_at = NOW() WHERE id = ? AND vendor_id = ?")->execute([$tracking, $carrier, $orderId, $vendor['id']]);
        logAudit($pdo, 'vendor_ship_order', 'purchase_order', $orderId, "Tracking: $tracking ($carrier)");
        $msg = 'Order marked as shipped!';
    } elseif ($_POST['action'] === 'process') {
        $pdo->prepare("UPDATE purchase_orders SET status = 'processing' WHERE id = ? AND vendor_id = ? AND status = 'confirmed'")->execute([$orderId, $vendor['id']]);
        $msg = 'Order is now processing.';
    }
}

// Fetch orders
$filter = $_GET['status'] ?? '';
$sql = "SELECT po.*, c.name as component_name, c.part_number 
        FROM purchase_orders po 
        LEFT JOIN components c ON po.component_id = c.id 
        WHERE po.vendor_id = ?";
$params = [$vendor['id']];
if ($filter) { $sql .= " AND po.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY po.ordered_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $orders = $stmt->fetchAll();

$totalOrders = count($orders);
$pendingCount = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$processingCount = count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmed', 'processing'])));
$shippedCount = count(array_filter($orders, fn($o) => $o['status'] === 'shipped'));
$deliveredCount = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$totalRevenue = array_sum(array_column($orders, 'total_price'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Purchase Orders</h2>
    <p class="text-sm text-slate-500 mt-1">Manage incoming purchase orders — confirm, process, and ship.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-truck-fast text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalOrders ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Total Orders</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-clock text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $pendingCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-gears text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $processingCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Processing</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-truck text-purple-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $shippedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Shipped</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-indian-rupee-sign text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white">₹<?= number_format($totalRevenue, 0) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Revenue</div>
    </div>
</div>

<!-- Filters -->
<div class="flex items-center gap-2 mb-5">
    <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['pending','confirmed','processing','shipped','delivered'] as $s): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
</div>

<!-- Orders -->
<div class="space-y-4">
    <?php foreach ($orders as $o): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="text-emerald-400 font-mono font-bold text-sm"><?= htmlspecialchars($o['order_number']) ?></span>
                    <?= statusBadge($o['status']) ?>
                </div>
                <div class="text-xs text-slate-500">Ordered <?= date('M j, Y H:i', strtotime($o['ordered_at'])) ?></div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-white">₹<?= number_format($o['total_price'], 2) ?></div>
                <div class="text-[10px] text-slate-600"><?= $o['quantity'] ?> × ₹<?= number_format($o['unit_price'], 2) ?></div>
            </div>
        </div>

        <div class="bg-slate-800/30 rounded-xl p-3 mb-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-microchip text-blue-400 text-xs"></i>
                </div>
                <div>
                    <div class="text-sm font-medium text-white"><?= htmlspecialchars($o['component_name'] ?? 'Unknown Component') ?></div>
                    <div class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($o['part_number'] ?? '') ?></div>
                </div>
                <div class="ml-auto text-sm font-bold text-slate-300">Qty: <?= number_format($o['quantity']) ?></div>
            </div>
        </div>

        <?php if ($o['tracking_number']): ?>
        <div class="bg-purple-500/5 rounded-xl p-3 mb-3 border border-purple-500/10">
            <div class="text-xs text-slate-500">Tracking</div>
            <div class="text-sm text-purple-400 font-mono"><?= htmlspecialchars($o['tracking_number']) ?></div>
            <?php if ($o['shipping_carrier']): ?><div class="text-xs text-slate-500"><?= htmlspecialchars($o['shipping_carrier']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex items-center gap-2 pt-3 border-t border-slate-800/50">
            <?php if ($o['status'] === 'pending'): ?>
            <form method="POST"><input type="hidden" name="action" value="confirm"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-4 py-2 rounded-lg hover:bg-emerald-600/40 transition font-medium"><i class="fa-solid fa-check mr-1"></i>Confirm Order</button>
            </form>
            <?php elseif ($o['status'] === 'confirmed'): ?>
            <form method="POST"><input type="hidden" name="action" value="process"><input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <button class="text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 px-4 py-2 rounded-lg hover:bg-blue-600/40 transition font-medium"><i class="fa-solid fa-gears mr-1"></i>Start Processing</button>
            </form>
            <?php elseif ($o['status'] === 'processing'): ?>
            <form method="POST" class="flex items-center gap-2 flex-1">
                <input type="hidden" name="action" value="ship">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <input type="text" name="tracking_number" placeholder="Tracking #" required class="input-field px-3 py-2 rounded-lg text-xs flex-1">
                <select name="shipping_carrier" class="input-field px-2 py-2 rounded-lg text-xs">
                    <option value="DTDC">DTDC</option>
                    <option value="BlueDart">BlueDart</option>
                    <option value="Delhivery">Delhivery</option>
                    <option value="India Post">India Post</option>
                    <option value="FedEx">FedEx</option>
                    <option value="DHL">DHL</option>
                </select>
                <button class="text-xs bg-purple-600/20 text-purple-400 border border-purple-500/30 px-4 py-2 rounded-lg hover:bg-purple-600/40 transition font-medium shrink-0"><i class="fa-solid fa-truck mr-1"></i>Ship</button>
            </form>
            <?php elseif ($o['status'] === 'shipped'): ?>
            <span class="text-xs text-purple-400"><i class="fa-solid fa-truck-moving mr-1"></i>In Transit</span>
            <?php if ($o['shipped_at']): ?><span class="text-xs text-slate-600 ml-2">Shipped <?= timeAgo($o['shipped_at']) ?></span><?php endif; ?>
            <?php elseif ($o['status'] === 'delivered'): ?>
            <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Delivered</span>
            <?php if ($o['delivered_at']): ?><span class="text-xs text-slate-600 ml-2"><?= date('M j, Y', strtotime($o['delivered_at'])) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($orders)): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4">
            <i class="fa-solid fa-truck-fast text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Purchase Orders</h3>
        <p class="text-sm text-slate-600 max-w-sm mx-auto">Purchase orders will appear here when buyers order your components.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
