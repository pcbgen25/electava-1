<?php
$pageTitle = 'Employee Login Log Tracking';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if (!empty($_GET['user'])) { $where .= " AND u.username LIKE ?"; $params[] = '%'.$_GET['user'].'%'; }
if (!empty($_GET['device'])) { $where .= " AND l.device_type LIKE ?"; $params[] = '%'.$_GET['device'].'%'; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs l LEFT JOIN users u ON l.user_id = u.id $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT l.*, u.username, u.full_name, u.role FROM login_logs l LEFT JOIN users u ON l.user_id = u.id $where ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Employee Login Tracking</h2>
    <p class="text-sm text-slate-500 mt-1">Monitor when and how employees access the workspace (PC, Mobile, Browsers).</p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Employee Username</label>
        <input type="text" name="user" value="<?= htmlspecialchars($_GET['user']??'') ?>" class="input-field px-3 py-2 rounded-lg text-xs w-48" placeholder="Search by username...">
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Device Type</label>
        <select name="device" class="input-field px-3 py-2 rounded-lg text-xs w-36">
            <option value="">All Devices</option>
            <option value="Desktop" <?= ($_GET['device']??'') === 'Desktop' ? 'selected' : '' ?>>PC / Desktop</option>
            <option value="Mobile" <?= ($_GET['device']??'') === 'Mobile' ? 'selected' : '' ?>>Mobile Phone</option>
            <option value="Tablet" <?= ($_GET['device']??'') === 'Tablet' ? 'selected' : '' ?>>Tablet</option>
        </select>
    </div>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white shadow-lg shadow-emerald-500/20">Filter</button>
    <a href="login_logs.php" class="btn-secondary px-4 py-2 rounded-lg text-xs text-slate-400">Clear</a>
    <span class="text-xs text-slate-600 ml-auto font-medium"><?= number_format($total) ?> logins tracked</span>
</form>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Timestamp</th>
                <th class="px-5 py-4 font-semibold">Employee</th>
                <th class="px-5 py-4 font-semibold">Role</th>
                <th class="px-5 py-4 font-semibold">Status</th>
                <th class="px-5 py-4 font-semibold">Device (PC/Mobile)</th>
                <th class="px-5 py-4 font-semibold">Browser Info</th>
                <th class="px-5 py-4 font-semibold">IP Address</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($logs as $l): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4 text-xs text-slate-400 whitespace-nowrap"><?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?></td>
                <td class="px-5 py-4">
                    <div class="text-white text-sm font-medium"><?= htmlspecialchars($l['full_name']??$l['username']??'Unknown User') ?></div>
                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($l['username']??'') ?></div>
                </td>
                <td class="px-5 py-4">
                    <span class="px-2 py-1 bg-slate-800 text-slate-300 rounded text-[10px] tracking-widest uppercase border border-slate-700"><?= $l['role'] ?: 'None' ?></span>
                </td>
                <td class="px-5 py-4">
                    <?= statusBadge($l['status']) ?>
                </td>
                <td class="px-5 py-4">
                    <?php if($l['device_type'] === 'Mobile'): ?>
                        <span class="text-xs text-blue-400 flex items-center gap-2"><i class="fa-solid fa-mobile-screen"></i> Mobile</span>
                    <?php elseif($l['device_type'] === 'Desktop'): ?>
                        <span class="text-xs text-emerald-400 flex items-center gap-2"><i class="fa-solid fa-desktop"></i> PC/Desktop</span>
                    <?php else: ?>
                        <span class="text-xs text-slate-400 flex items-center gap-2"><i class="fa-solid fa-tablet-screen-button"></i> Tablet</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4">
                    <div class="text-slate-300 text-xs flex items-center gap-2">
                        <?php if($l['browser'] == 'Chrome') echo '<i class="fa-brands fa-chrome text-yellow-500"></i>'; ?>
                        <?php if($l['browser'] == 'Firefox') echo '<i class="fa-brands fa-firefox-browser text-orange-500"></i>'; ?>
                        <?php if($l['browser'] == 'Safari') echo '<i class="fa-brands fa-safari text-blue-400"></i>'; ?>
                        <?= htmlspecialchars($l['browser'] ?? 'Unknown') ?>
                    </div>
                </td>
                <td class="px-5 py-4 text-xs text-slate-500 font-mono"><?= $l['ip_address'] ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($logs) === 0): ?>
            <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500 text-sm">No employee logins found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-8">
    <?php for ($i = max(1, $page-3); $i <= min($totalPages, $page+3); $i++): ?>
        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" class="<?= $i===$page ? 'btn-primary shadow-lg shadow-emerald-500/20 text-white' : 'btn-secondary text-slate-400' ?> px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
