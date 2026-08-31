<?php
$pageTitle = 'Company Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole('vendor');

$uid = $_SESSION['user_id'];
$msg = '';

// Get vendor + user
$vendorStmt = $pdo->prepare("SELECT v.*, e.full_name, e.email, e.phone as user_phone FROM vendors v JOIN users e ON v.user_id = e.id WHERE v.user_id = ?");
$vendorStmt->execute([$uid]);
$vendor = $vendorStmt->fetch();

if (!$vendor) {
    echo '<div class="glass-card p-8 rounded-2xl text-center"><h3 class="text-xl text-red-400 font-bold mb-2">Vendor Profile Not Found</h3><p class="text-slate-500">Please contact an administrator.</p></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'update_profile') {
        $pdo->prepare("UPDATE vendors SET company_name = ?, contact_person = ?, phone = ?, address = ?, shipping_address = ?, payment_terms = ?, bank_details = ? WHERE user_id = ?")->execute([
            trim($_POST['company_name']),
            trim($_POST['contact_person']),
            trim($_POST['phone']),
            trim($_POST['address']),
            trim($_POST['shipping_address']),
            trim($_POST['payment_terms']),
            trim($_POST['bank_details']),
            $uid
        ]);
        logAudit($pdo, 'update_vendor_profile', 'vendor', $vendor['id'], 'Profile updated');
        $msg = 'Profile updated successfully!';
        // Refresh
        $vendorStmt->execute([$uid]);
        $vendor = $vendorStmt->fetch();
    }
}

// Stats
$totalProducts = $pdo->prepare("SELECT COUNT(*) FROM components WHERE vendor_id = ? AND status = 'active'");
$totalProducts->execute([$uid]);
$productCount = $totalProducts->fetchColumn();

$totalOrders = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ?");
$totalOrders->execute([$vendor['id']]);
$orderCount = $totalOrders->fetchColumn();

$totalRevenue = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM purchase_orders WHERE vendor_id = ? AND status IN ('shipped','delivered')");
$totalRevenue->execute([$vendor['id']]);
$revenue = $totalRevenue->fetchColumn();
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Company Profile</h2>
    <p class="text-sm text-slate-500 mt-1">Manage your vendor profile, contact details, and business information.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="glass-card rounded-2xl p-6 border border-slate-700/50">
        <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-2xl font-bold text-white shadow-xl shadow-emerald-500/20 mb-4">
                <?= strtoupper(substr($vendor['company_name'], 0, 2)) ?>
            </div>
            <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($vendor['company_name']) ?></h3>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($vendor['contact_person'] ?? '') ?></p>
            <?php if ($vendor['is_approved']): ?>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mt-2">
                <i class="fa-solid fa-badge-check text-[10px]"></i>Verified Vendor
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20 mt-2">
                <i class="fa-solid fa-clock text-[10px]"></i>Pending Verification
            </span>
            <?php endif; ?>
        </div>

        <div class="space-y-4 border-t border-slate-800/60 pt-4">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">Rating</span>
                <div class="flex items-center gap-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa-<?= $i <= round($vendor['rating'] ?? 0) ? 'solid' : 'regular' ?> fa-star text-amber-400 text-xs"></i>
                    <?php endfor; ?>
                    <span class="text-xs text-slate-400 ml-1"><?= number_format($vendor['rating'] ?? 0, 1) ?></span>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">Total Orders</span>
                <span class="text-sm font-medium text-white"><?= number_format($orderCount) ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">Active Products</span>
                <span class="text-sm font-medium text-white"><?= number_format($productCount) ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">On-Time Delivery</span>
                <span class="text-sm font-medium text-emerald-400"><?= $vendor['on_time_delivery_rate'] ?>%</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">Total Revenue</span>
                <span class="text-sm font-bold text-emerald-400">₹<?= number_format($revenue, 0) ?></span>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="lg:col-span-2 glass-card rounded-2xl p-6 border border-slate-700/50">
        <h3 class="text-lg font-semibold text-white mb-5"><i class="fa-solid fa-building text-emerald-400 mr-2"></i>Edit Profile</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Company Name</label>
                    <input type="text" name="company_name" value="<?= htmlspecialchars($vendor['company_name']) ?>" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Contact Person</label>
                    <input type="text" name="contact_person" value="<?= htmlspecialchars($vendor['contact_person'] ?? '') ?>" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Payment Terms</label>
                    <input type="text" name="payment_terms" value="<?= htmlspecialchars($vendor['payment_terms'] ?? '') ?>" placeholder="e.g. Net 30" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Business Address</label>
                <textarea name="address" rows="2" class="input-field w-full px-3 py-2.5 rounded-xl text-sm"><?= htmlspecialchars($vendor['address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Shipping / Warehouse Address</label>
                <textarea name="shipping_address" rows="2" class="input-field w-full px-3 py-2.5 rounded-xl text-sm"><?= htmlspecialchars($vendor['shipping_address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Bank Details</label>
                <textarea name="bank_details" rows="3" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Account Name, Bank, IFSC, Account Number..."><?= htmlspecialchars($vendor['bank_details'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end pt-2">
                <button class="btn-primary px-6 py-2.5 rounded-xl text-sm text-white font-medium shadow-lg shadow-emerald-600/20">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
