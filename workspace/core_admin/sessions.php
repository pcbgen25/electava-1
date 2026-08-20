<?php
$pageTitle = 'Login Activity';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM login_logs")->fetchColumn();
$totalPages = ceil($total / $perPage);

$logs = $pdo->query("SELECT l.*, u.username, u.full_name, u.role FROM login_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();

// Stats
$todayLogins = $pdo->query("SELECT COUNT(*) FROM login_logs WHERE DATE(created_at) = CURDATE() AND status = 'success'")->fetchColumn();
$failedToday = $pdo->query("SELECT COUNT(*) FROM login_logs WHERE DATE(created_at) = CURDATE() AND status = 'failed'")->fetchColumn();
$uniqueIPs = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM login_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="glass-card p-4 rounded-xl"><div class="text-2xl font-bold text-white"><?= $todayLogins ?></div><div class="text-xs text-slate-500">Successful logins today</div></div>
    <div class="glass-card p-4 rounded-xl"><div class="text-2xl font-bold text-red-400"><?= $failedToday ?></div><div class="text-xs text-slate-500">Failed attempts today</div></div>
    <div class="glass-card p-4 rounded-xl"><div class="text-2xl font-bold text-blue-400"><?= $uniqueIPs ?></div><div class="text-xs text-slate-500">Unique IPs today</div></div>
</div>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr><th class="px-5 py-3">Time</th><th class="px-5 py-3">User</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Device</th><th class="px-5 py-3">Browser</th><th class="px-5 py-3">IP Address</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($logs as $l): ?>
            <tr class="table-row">
                <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap"><?= date('M j, H:i:s', strtotime($l['created_at'])) ?></td>
                <td class="px-5 py-3 text-white text-xs font-medium"><?= htmlspecialchars($l['full_name']??$l['username']??'—') ?></td>
                <td class="px-5 py-3 text-xs text-slate-400 capitalize"><?= str_replace('_',' ',$l['role']??'—') ?></td>
                <td class="px-5 py-3"><?= statusBadge($l['status']) ?></td>
                <td class="px-5 py-3 text-xs text-slate-400"><i class="fa-solid <?= $l['device_type']==='Mobile'?'fa-mobile-screen':'fa-desktop' ?> mr-1 text-slate-600"></i><?= $l['device_type'] ?></td>
                <td class="px-5 py-3 text-xs text-slate-400"><?= $l['browser'] ?></td>
                <td class="px-5 py-3 text-xs text-slate-600 font-mono"><?= $l['ip_address'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-6">
    <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= $i===$page?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $i===$page?'text-white':'text-slate-400' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
