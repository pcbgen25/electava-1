<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

// Revenue by domain (mock based on purchase orders)
$ordersByDomain = $pdo->query("SELECT d.name, COUNT(o.id) as orders, COALESCE(SUM(o.total),0) as revenue FROM domains d LEFT JOIN orders o ON 1=1 GROUP BY d.id, d.name")->fetchAll();

// Task completion rate
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn() ?: 1;
$completed = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('completed','approved')")->fetchColumn();
$completionRate = round(($completed / $totalTasks) * 100);

// User activity (last 7 days)
$activityDays = $pdo->query("SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY dt")->fetchAll();

// Top performers
$topUsers = $pdo->query("SELECT u.full_name, u.username, COUNT(t.id) as completed FROM users u JOIN tasks t ON t.assigned_to = u.id AND t.status IN ('completed','approved') GROUP BY u.id ORDER BY completed DESC LIMIT 5")->fetchAll();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="glass-card p-5 rounded-2xl text-center">
        <div class="text-3xl font-bold text-white"><?= $completionRate ?>%</div>
        <div class="text-xs text-slate-500 mt-1">Task Completion Rate</div>
        <div class="w-full bg-slate-800 rounded-full h-2 mt-3"><div class="bg-emerald-500 h-2 rounded-full" style="width:<?= $completionRate ?>%"></div></div>
    </div>
    <div class="glass-card p-5 rounded-2xl text-center">
        <div class="text-3xl font-bold text-white"><?= $totalTasks ?></div>
        <div class="text-xs text-slate-500 mt-1">Total Tasks Created</div>
    </div>
    <div class="glass-card p-5 rounded-2xl text-center">
        <div class="text-3xl font-bold text-emerald-400"><?= $completed ?></div>
        <div class="text-xs text-slate-500 mt-1">Tasks Completed</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Activity (Last 7 Days)</h3>
        <canvas id="activityChart" height="150"></canvas>
    </div>
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Top Performers</h3>
        <div class="space-y-3">
            <?php foreach ($topUsers as $i => $tu): ?>
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-[10px] font-bold text-white"><?= $i+1 ?></div>
                <div class="flex-1"><div class="text-sm text-white"><?= htmlspecialchars($tu['full_name']?:$tu['username']) ?></div></div>
                <span class="text-sm font-bold text-emerald-400"><?= $tu['completed'] ?> <span class="text-xs text-slate-500 font-normal">tasks</span></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topUsers)): ?><p class="text-sm text-slate-600 text-center py-4">No data yet</p><?php endif; ?>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('activityChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($d) => "'".date('D', strtotime($d['dt']))."'", $activityDays)) ?>],
        datasets: [{
            label: 'Actions',
            data: [<?= implode(',', array_column($activityDays, 'cnt')) ?>],
            backgroundColor: 'rgba(16,185,129,0.3)',
            borderColor: '#10b981',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#64748b' }, border: { display: false } },
            x: { grid: { display: false }, ticks: { color: '#64748b' }, border: { display: false } }
        }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
