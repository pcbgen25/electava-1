<?php
$pageTitle = 'Team Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');
$domainId = $_SESSION['domain_id'];

$team = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status IN ('pending','in_progress')) as active_tasks, (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status IN ('completed','approved')) as completed_tasks FROM users u WHERE u.domain_id = ? AND u.role = 'employee' ORDER BY u.full_name");
$team->execute([$domainId]); $team = $team->fetchAll();
?>
<div class="mb-6"><p class="text-sm text-slate-500"><?= count($team) ?> team members in your domain</p></div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($team as $m):
        $totalT = $m['active_tasks'] + $m['completed_tasks'];
        $rate = $totalT > 0 ? round(($m['completed_tasks'] / $totalT) * 100) : 0;
    ?>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-sm font-bold text-white"><?= strtoupper(substr($m['full_name']?:$m['username'],0,1)) ?></div>
            <div>
                <div class="font-semibold text-white text-sm"><?= htmlspecialchars($m['full_name']?:$m['username']) ?></div>
                <div class="text-xs text-slate-500"><?= htmlspecialchars($m['email']) ?></div>
            </div>
            <?= $m['status'] === 'active' ? '<span class="ml-auto text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">Active</span>' : '<span class="ml-auto text-[10px] text-red-400 bg-red-500/10 px-2 py-0.5 rounded-full">Inactive</span>' ?>
        </div>
        <div class="grid grid-cols-3 gap-3 text-center mb-3">
            <div class="bg-slate-800/40 rounded-lg p-2"><div class="text-lg font-bold text-amber-400"><?= $m['active_tasks'] ?></div><div class="text-[10px] text-slate-600">Active</div></div>
            <div class="bg-slate-800/40 rounded-lg p-2"><div class="text-lg font-bold text-emerald-400"><?= $m['completed_tasks'] ?></div><div class="text-[10px] text-slate-600">Done</div></div>
            <div class="bg-slate-800/40 rounded-lg p-2"><div class="text-lg font-bold text-blue-400"><?= $rate ?>%</div><div class="text-[10px] text-slate-600">Rate</div></div>
        </div>
        <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden"><div class="h-full bg-emerald-500/50 rounded-full transition-all" style="width:<?= $rate ?>%"></div></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($team)): ?>
    <div class="col-span-full glass-card p-8 rounded-2xl text-center"><i class="fa-solid fa-users text-3xl text-slate-700 mb-3"></i><p class="text-slate-500">No team members in your domain yet</p></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
