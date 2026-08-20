<?php
$pageTitle = 'Careers Management';
require_once __DIR__ . '/../includes/header.php';
requireRole(['core_admin', 'admin']);

// Handle Delete
if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM careers WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    header("Location: careers.php?msg=deleted");
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM careers");
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM careers ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$careers = $stmt->fetchAll();
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h2 class="text-2xl font-bold text-white tracking-tight">Careers Management</h2>
        <p class="text-sm text-slate-500 mt-1">Manage open positions displayed on the public marketplace.</p>
    </div>
    <button onclick="alert('In a full version, this opens a form to add a new career.')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white shadow-lg shadow-emerald-500/20">
        <i class="fa-solid fa-plus mr-2"></i> Add Position
    </button>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 text-sm">
    <i class="fa-solid fa-check-circle mr-2"></i> Career position removed successfully.
</div>
<?php endif; ?>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Role Title</th>
                <th class="px-5 py-4 font-semibold">Team</th>
                <th class="px-5 py-4 font-semibold">Location / Type</th>
                <th class="px-5 py-4 font-semibold">Status</th>
                <th class="px-5 py-4 font-semibold text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($careers as $c): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4">
                    <div class="text-white text-sm font-medium"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="text-[10px] text-slate-500 truncate max-w-xs mt-1"><?= htmlspecialchars($c['summary']) ?></div>
                </td>
                <td class="px-5 py-4">
                    <span class="px-2 py-1 bg-slate-800 text-emerald-400 rounded text-[10px] tracking-widest uppercase border border-slate-700"><?= htmlspecialchars($c['team']) ?></span>
                </td>
                <td class="px-5 py-4">
                    <div class="text-slate-300 text-xs"><i class="fa-solid fa-location-dot mr-1 text-slate-500"></i> <?= htmlspecialchars($c['location']) ?></div>
                    <div class="text-slate-500 text-[10px] mt-1 tracking-wider uppercase"><?= htmlspecialchars($c['type']) ?></div>
                </td>
                <td class="px-5 py-4">
                    <?= statusBadge($c['status']) ?>
                </td>
                <td class="px-5 py-4 text-right">
                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this position?');">
                        <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="text-slate-500 hover:text-red-400 transition" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($careers) === 0): ?>
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500 text-sm">No career positions found.</td></tr>
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
