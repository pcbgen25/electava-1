<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$uid = $_SESSION['user_id'];
$domainId = $_SESSION['domain_id'];

// === Gather report data ===

// Tasks stats
$tasksByStatus = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM tasks WHERE created_by = ? GROUP BY status");
$tasksByStatus->execute([$uid]); $taskStatusData = $tasksByStatus->fetchAll();
$taskMap = [];
foreach ($taskStatusData as $t) $taskMap[$t['status']] = $t['cnt'];
$totalTasks = array_sum(array_column($taskStatusData, 'cnt'));

// Tasks by priority
$tasksByPriority = $pdo->prepare("SELECT priority, COUNT(*) as cnt FROM tasks WHERE created_by = ? GROUP BY priority");
$tasksByPriority->execute([$uid]); $priorityData = $tasksByPriority->fetchAll();
$priorityMap = [];
foreach ($priorityData as $p) $priorityMap[$p['priority']] = $p['cnt'];

// Employee performance (tasks completed by each)
$employeePerf = $pdo->prepare("
    SELECT e.full_name, e.username, 
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status IN ('completed','approved') THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
           SUM(CASE WHEN t.due_date < NOW() AND t.status NOT IN ('completed','approved') THEN 1 ELSE 0 END) as overdue
    FROM users e 
    LEFT JOIN tasks t ON t.assigned_to = e.id
    WHERE e.domain_id = ? AND e.role = 'employee' AND e.status = 'active'
    GROUP BY e.id ORDER BY completed DESC
");
$employeePerf->execute([$domainId]); $perfData = $employeePerf->fetchAll();

// Weekly task completion trend (last 8 weeks)
$weeklyTasks = $pdo->prepare("
    SELECT YEARWEEK(completed_at) as yw, COUNT(*) as cnt 
    FROM tasks 
    WHERE created_by = ? AND status IN ('completed','approved') AND completed_at IS NOT NULL AND completed_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
    GROUP BY yw ORDER BY yw ASC
");
$weeklyTasks->execute([$uid]); $weeklyData = $weeklyTasks->fetchAll();

// Recent approvals
$approvals = $pdo->prepare("
    SELECT ta.*, t.title as task_title, e.full_name as assignee_name 
    FROM task_approvals ta 
    JOIN tasks t ON ta.task_id = t.id 
    LEFT JOIN users e ON t.assigned_to = e.id 
    WHERE ta.approved_by = ? 
    ORDER BY ta.approved_at DESC LIMIT 10
");
$approvals->execute([$uid]); $recentApprovals = $approvals->fetchAll();

// Service request summary
$srStats = $pdo->query("SELECT status, COUNT(*) as cnt FROM service_requests GROUP BY status")->fetchAll();
$srMap = [];
foreach ($srStats as $sr) $srMap[$sr['status']] = $sr['cnt'];
$totalSR = array_sum(array_column($srStats, 'cnt'));

// Completion rate
$completionRate = $totalTasks > 0 ? round((($taskMap['completed'] ?? 0) + ($taskMap['approved'] ?? 0)) / $totalTasks * 100) : 0;
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Reports & Analytics</h2>
    <p class="text-sm text-slate-500 mt-1">Overview of tasks, team performance, and project metrics for your domain.</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-list-check text-blue-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $totalTasks ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Total Tasks Created</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-chart-line text-emerald-400"></i></div>
            <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full"><?= $completionRate ?>%</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= ($taskMap['completed'] ?? 0) + ($taskMap['approved'] ?? 0) ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Tasks Completed</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-users text-purple-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= count($perfData) ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Team Members</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-cogs text-cyan-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $totalSR ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Service Requests</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <!-- Task Status Distribution -->
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Task Status Distribution</h3>
        <canvas id="taskStatusChart" height="200"></canvas>
    </div>
    <!-- Weekly Completion Trend -->
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Weekly Completion Trend</h3>
        <canvas id="weeklyChart" height="200"></canvas>
    </div>
</div>

<!-- Team Performance -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl mb-6">
    <div class="p-5 border-b border-slate-800/50">
        <h3 class="text-sm font-semibold text-white">Team Performance</h3>
    </div>
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-3 font-semibold">Employee</th>
                <th class="px-5 py-3 font-semibold">Total</th>
                <th class="px-5 py-3 font-semibold">Completed</th>
                <th class="px-5 py-3 font-semibold">In Progress</th>
                <th class="px-5 py-3 font-semibold">Overdue</th>
                <th class="px-5 py-3 font-semibold">Completion Rate</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($perfData as $emp): 
                $rate = $emp['total_tasks'] > 0 ? round($emp['completed'] / $emp['total_tasks'] * 100) : 0;
            ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-600 to-indigo-700 flex items-center justify-center text-xs font-bold text-white">
                            <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-white"><?= htmlspecialchars($emp['full_name']) ?></div>
                            <div class="text-[10px] text-slate-600">@<?= htmlspecialchars($emp['username']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-sm text-slate-300"><?= $emp['total_tasks'] ?></td>
                <td class="px-5 py-3.5 text-sm text-emerald-400 font-medium"><?= $emp['completed'] ?></td>
                <td class="px-5 py-3.5 text-sm text-blue-400"><?= $emp['in_progress'] ?></td>
                <td class="px-5 py-3.5 text-sm <?= $emp['overdue'] > 0 ? 'text-red-400 font-medium' : 'text-slate-500' ?>"><?= $emp['overdue'] ?></td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500/60 rounded-full" style="width:<?= $rate ?>%"></div>
                        </div>
                        <span class="text-xs text-slate-400 w-10 text-right"><?= $rate ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($perfData)): ?>
            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500 text-sm">No employees in your domain yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent Approvals -->
<div class="glass-card rounded-2xl p-5">
    <h3 class="text-sm font-semibold text-white mb-4">Recent Approval Activity</h3>
    <div class="space-y-2">
        <?php foreach ($recentApprovals as $a): ?>
        <div class="flex items-center gap-3 py-2 border-b border-slate-800/40 last:border-0">
            <div class="w-7 h-7 rounded-lg <?= $a['action'] === 'approved' ? 'bg-emerald-500/10' : 'bg-red-500/10' ?> flex items-center justify-center shrink-0">
                <i class="fa-solid fa-<?= $a['action'] === 'approved' ? 'check text-emerald-400' : 'times text-red-400' ?> text-[10px]"></i>
            </div>
            <div class="flex-1 min-w-0">
                <span class="text-sm text-white"><?= htmlspecialchars($a['task_title'] ?? '') ?></span>
                <span class="text-xs text-slate-500 ml-2"><?= htmlspecialchars($a['assignee_name'] ?? '') ?></span>
            </div>
            <span class="text-[11px] text-slate-600 shrink-0"><?= timeAgo($a['approved_at']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recentApprovals)): ?>
        <p class="text-sm text-slate-600 text-center py-4">No approvals yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Task Status Chart
const ctx1 = document.getElementById('taskStatusChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'In Progress', 'Submitted', 'Approved', 'Rejected', 'Completed'],
        datasets: [{
            data: [<?= $taskMap['pending']??0 ?>, <?= $taskMap['in_progress']??0 ?>, <?= $taskMap['submitted']??0 ?>, <?= $taskMap['approved']??0 ?>, <?= $taskMap['rejected']??0 ?>, <?= $taskMap['completed']??0 ?>],
            backgroundColor: ['#f59e0b40','#3b82f640','#8b5cf640','#10b98140','#ef444440','#06b6d440'],
            borderColor: ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#ef4444','#06b6d4'],
            borderWidth: 1.5
        }]
    },
    options: {
        responsive: true, cutout: '65%',
        plugins: { legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 11 }, padding: 10, usePointStyle: true, pointStyleWidth: 8 } } }
    }
});

// Weekly Trend Chart
const ctx2 = document.getElementById('weeklyChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: [<?php foreach($weeklyData as $w) echo "'W" . substr($w['yw'], -2) . "',"; ?>],
        datasets: [{
            label: 'Completed',
            data: [<?php foreach($weeklyData as $w) echo $w['cnt'] . ','; ?>],
            backgroundColor: '#10b98140',
            borderColor: '#10b981',
            borderWidth: 1.5,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, ticks: { color: '#64748b', stepSize: 1 }, grid: { color: '#1e293b' } },
            x: { ticks: { color: '#64748b' }, grid: { display: false } }
        },
        plugins: { legend: { labels: { color: '#94a3b8' } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
