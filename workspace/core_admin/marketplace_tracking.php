<?php
$pageTitle = 'Marketplace Visitors Tracking';
require_once __DIR__ . '/../includes/header.php';
requireRole(['core_admin', 'admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if (!empty($_GET['page_visited'])) { $where .= " AND page_visited LIKE ?"; $params[] = '%'.$_GET['page_visited'].'%'; }
if (!empty($_GET['device'])) { $where .= " AND device_type LIKE ?"; $params[] = '%'.$_GET['device'].'%'; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_tracking $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM marketplace_tracking $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Marketplace User Tracking</h2>
    <p class="text-sm text-slate-500 mt-1">Live traffic components and user action tracking from the public frontend.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Page Visited</label>
        <input type="text" name="page_visited" value="<?= htmlspecialchars($_GET['page_visited']??'') ?>" class="input-field px-3 py-2 rounded-lg text-xs w-48" placeholder="/contact, /products...">
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Device Info</label>
        <select name="device" class="input-field px-3 py-2 rounded-lg text-xs w-36">
            <option value="">All Devices</option>
            <option value="Desktop" <?= ($_GET['device']??'') === 'Desktop' ? 'selected' : '' ?>>PC / Desktop</option>
            <option value="Mobile" <?= ($_GET['device']??'') === 'Mobile' ? 'selected' : '' ?>>Mobile Phone</option>
        </select>
    </div>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white shadow-lg shadow-emerald-500/20">Filter</button>
    <a href="marketplace_tracking.php" class="btn-secondary px-4 py-2 rounded-lg text-xs text-slate-400">Clear</a>
    <span class="text-xs text-slate-600 ml-auto font-medium"><?= number_format($total) ?> tracking events</span>
</form>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Time</th>
                <th class="px-5 py-4 font-semibold">Session ID</th>
                <th class="px-5 py-4 font-semibold">Page Path</th>
                <th class="px-5 py-4 font-semibold">Device</th>
                <th class="px-5 py-4 font-semibold">Browser</th>
                <th class="px-5 py-4 font-semibold">IP Address</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($logs as $l): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4 text-xs text-slate-400 whitespace-nowrap"><?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?></td>
                <td class="px-5 py-4 text-xs text-slate-500 font-mono"><?= htmlspecialchars($l['session_id']) ?></td>
                <td class="px-5 py-4">
                    <span class="text-emerald-400 font-mono bg-emerald-900/20 px-2 py-0.5 rounded border border-emerald-500/20 text-xs">
                        <?= htmlspecialchars($l['page_visited']) ?>
                    </span>
                </td>
                <td class="px-5 py-4">
                    <?php if($l['device_type'] === 'Mobile'): ?>
                        <span class="text-xs text-blue-400 flex items-center gap-2"><i class="fa-solid fa-mobile-screen"></i> Mobile</span>
                    <?php elseif($l['device_type'] === 'Desktop'): ?>
                        <span class="text-xs text-emerald-400 flex items-center gap-2"><i class="fa-solid fa-desktop"></i> PC</span>
                    <?php else: ?>
                        <span class="text-xs text-slate-400 flex items-center gap-2"><?= htmlspecialchars($l['device_type']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4 text-xs text-slate-300"><?= htmlspecialchars($l['browser']) ?></td>
                <td class="px-5 py-4 text-xs text-slate-500 font-mono"><?= htmlspecialchars($l['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($logs) === 0): ?>
            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500 text-sm">No marketplace tracking data yet.</td></tr>
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
