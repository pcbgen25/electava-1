<?php
$pageTitle = 'Manager Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$domainId = $_SESSION['domain_id'];
$uid = $_SESSION['user_id'];

$domainName = $pdo->prepare("SELECT name FROM domains WHERE id = ?"); $domainName->execute([$domainId]); $domainName = $domainName->fetchColumn() ?: 'My Domain';

$teamCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE domain_id = ? AND role = 'employee' AND status = 'active'"); $teamCount->execute([$domainId]); $teamCount = $teamCount->fetchColumn();
$pendingTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by = ? AND status IN ('pending','in_progress')"); $pendingTasks->execute([$uid]); $pendingTasks = $pendingTasks->fetchColumn();
$submittedTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by = ? AND status = 'submitted'"); $submittedTasks->execute([$uid]); $submittedTasks = $submittedTasks->fetchColumn();
$completedTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by = ? AND status IN ('completed','approved')"); $completedTasks->execute([$uid]); $completedTasks = $completedTasks->fetchColumn();

$totalForRate = $pendingTasks + $submittedTasks + $completedTasks;
$efficiency = $totalForRate > 0 ? round(($completedTasks / $totalForRate) * 100) : 0;

// Recent tasks
$recentTasks = $pdo->prepare("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.created_by = ? ORDER BY t.created_at DESC LIMIT 8");
$recentTasks->execute([$uid]); $recentTasks = $recentTasks->fetchAll();

// Task completion chart data
$taskByStatus = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM tasks WHERE created_by = ? GROUP BY status"); $taskByStatus->execute([$uid]); $taskByStatus = $taskByStatus->fetchAll();
$statusMap = []; foreach($taskByStatus as $t) $statusMap[$t['status']] = $t['cnt'];
?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($domainName) ?> <span class="text-emerald-400 font-normal text-base">Overview</span></h2>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2"><div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-clock text-amber-400 text-sm"></i></div></div>
        <div class="text-2xl font-bold text-white"><?= $pendingTasks ?></div>
        <div class="text-xs text-slate-500">Open Tasks</div>
    </div>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2"><div class="w-9 h-9 rounded-lg bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-paper-plane text-purple-400 text-sm"></i></div></div>
        <div class="text-2xl font-bold text-white"><?= $submittedTasks ?></div>
        <div class="text-xs text-slate-500">Awaiting Approval</div>
    </div>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2"><div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-users text-emerald-400 text-sm"></i></div></div>
        <div class="text-2xl font-bold text-white"><?= $teamCount ?></div>
        <div class="text-xs text-slate-500">Team Members</div>
    </div>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2"><div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-chart-line text-blue-400 text-sm"></i></div></div>
        <div class="text-2xl font-bold text-white"><?= $efficiency ?>%</div>
        <div class="text-xs text-slate-500">Completion Rate</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Task Breakdown</h3>
        <canvas id="statusChart" height="200"></canvas>
    </div>
    <div class="lg:col-span-2 glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Recent Tasks</h3>
            <a href="tasks.php" class="text-xs text-emerald-400 hover:text-emerald-300">View All</a>
        </div>
        <div class="space-y-2">
            <?php foreach ($recentTasks as $t): ?>
            <div class="flex items-center gap-3 py-2.5 border-b border-slate-800/40 last:border-0">
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-white font-medium truncate"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($t['assignee_name']??'Unassigned') ?> · <?= timeAgo($t['created_at']) ?></div>
                </div>
                <?= statusBadge($t['status']) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Pending','In Progress','Submitted','Completed'],
        datasets: [{ data: [<?= $statusMap['pending']??0 ?>,<?= $statusMap['in_progress']??0 ?>,<?= $statusMap['submitted']??0 ?>,<?= ($statusMap['completed']??0)+($statusMap['approved']??0) ?>],
            backgroundColor: ['#f59e0b30','#3b82f630','#8b5cf630','#10b98130'], borderColor: ['#f59e0b','#3b82f6','#8b5cf6','#10b981'], borderWidth: 1.5 }]
    },
    options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10 }, padding: 10, usePointStyle: true } } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
