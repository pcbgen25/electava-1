<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if (!empty($_GET['user'])) { $where .= " AND u.username LIKE ?"; $params[] = '%'.$_GET['user'].'%'; }
if (!empty($_GET['action'])) { $where .= " AND a.action LIKE ?"; $params[] = '%'.$_GET['action'].'%'; }
if (!empty($_GET['date_from'])) { $where .= " AND a.created_at >= ?"; $params[] = $_GET['date_from'].' 00:00:00'; }
if (!empty($_GET['date_to'])) { $where .= " AND a.created_at <= ?"; $params[] = $_GET['date_to'].' 23:59:59'; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT a.*, u.username, u.full_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex flex-wrap gap-3 items-end">
    <div><label class="block text-[10px] text-slate-500 mb-1">User</label><input type="text" name="user" value="<?= htmlspecialchars($_GET['user']??'') ?>" class="input-field px-3 py-1.5 rounded-lg text-xs w-36" placeholder="Username"></div>
    <div><label class="block text-[10px] text-slate-500 mb-1">Action</label><input type="text" name="action" value="<?= htmlspecialchars($_GET['action']??'') ?>" class="input-field px-3 py-1.5 rounded-lg text-xs w-36" placeholder="e.g. login"></div>
    <div><label class="block text-[10px] text-slate-500 mb-1">From</label><input type="date" name="date_from" value="<?= $_GET['date_from']??'' ?>" class="input-field px-3 py-1.5 rounded-lg text-xs"></div>
    <div><label class="block text-[10px] text-slate-500 mb-1">To</label><input type="date" name="date_to" value="<?= $_GET['date_to']??'' ?>" class="input-field px-3 py-1.5 rounded-lg text-xs"></div>
    <button class="btn-primary px-4 py-1.5 rounded-lg text-xs text-white">Filter</button>
    <a href="logs.php" class="btn-secondary px-3 py-1.5 rounded-lg text-xs text-slate-400">Clear</a>
    <span class="text-xs text-slate-600 ml-auto"><?= number_format($total) ?> records</span>
</form>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr><th class="px-5 py-3">Time</th><th class="px-5 py-3">Employee</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Entity</th><th class="px-5 py-3">Details</th><th class="px-5 py-3">IP</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($logs as $l): ?>
            <tr class="table-row">
                <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap"><?= date('M j, H:i:s', strtotime($l['created_at'])) ?></td>
                <td class="px-5 py-3 text-white text-xs"><?= htmlspecialchars($l['full_name']??$l['username']??'System') ?></td>
                <td class="px-5 py-3"><span class="text-xs font-medium text-emerald-400"><?= htmlspecialchars($l['action']) ?></span></td>
                <td class="px-5 py-3 text-xs text-slate-400"><?= $l['entity_type'] ? $l['entity_type'].'#'.$l['entity_id'] : '—' ?></td>
                <td class="px-5 py-3 text-xs text-slate-500 max-w-xs truncate"><?= htmlspecialchars($l['details']??'') ?></td>
                <td class="px-5 py-3 text-xs text-slate-600 font-mono"><?= $l['ip_address'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-6">
    <?php for ($i = max(1, $page-3); $i <= min($totalPages, $page+3); $i++): ?>
        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" class="<?= $i===$page ? 'btn-primary' : 'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $i===$page ? 'text-white' : 'text-slate-400' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
