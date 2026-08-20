<?php
$pageTitle = 'Core Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

// Stats
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$pendingTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('pending','in_progress')")->fetchColumn();
$completedTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('completed','approved')")->fetchColumn();
$projectCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn();
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$trackingEvents = $pdo->query("SELECT COUNT(*) FROM marketplace_tracking")->fetchColumn();
$serviceTokens = $pdo->query("SELECT COUNT(*) FROM service_tokens")->fetchColumn();
$pendingTokens = $pdo->query("SELECT COUNT(*) FROM service_tokens WHERE status = 'pending'")->fetchColumn();
$marketplaceUsers = $pdo->query("SELECT COUNT(DISTINCT user_email) FROM service_tokens WHERE user_email IS NOT NULL AND user_email != ''")->fetchColumn();
$domainCount = $pdo->query("SELECT COUNT(*) FROM domains")->fetchColumn();
$loginCount = $pdo->query("SELECT COUNT(*) FROM login_logs")->fetchColumn();
$todayLogins = $pdo->query("SELECT COUNT(*) FROM login_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$auditCount = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$uniqueVisitors = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM marketplace_tracking")->fetchColumn();

// Role breakdown
$roleCounts = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
$roleMap = [];
foreach ($roleCounts as $r) $roleMap[$r['role']] = $r['cnt'];

// Recent logins
$recentLogins = $pdo->query("SELECT l.*, u.username, u.full_name FROM login_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 8")->fetchAll();

// Recent audit
$recentAudit = $pdo->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

// Task stats by status
$taskStats = $pdo->query("SELECT status, COUNT(*) as cnt FROM tasks GROUP BY status")->fetchAll();
$taskMap = [];
foreach ($taskStats as $t) $taskMap[$t['status']] = $t['cnt'];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Overview</h2>
    <p class="text-sm text-slate-500 mt-1">System-wide statistics at a glance. Click any card to navigate.</p>
</div>

<!-- Row 1: Core Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <a href="/core_admin/employees.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20 transition"><i class="fa-solid fa-users text-emerald-400"></i></div>
            <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full"><?= $activeUsers ?> active</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $userCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-emerald-400/70 transition">Total Employees <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/users.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition"><i class="fa-solid fa-user-group text-purple-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $marketplaceUsers ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-purple-400/70 transition">Marketplace Users <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/projects.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition"><i class="fa-solid fa-diagram-project text-blue-400"></i></div>
            <span class="text-xs text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded-full"><?= $projectCount ?> active</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $totalProjects ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-blue-400/70 transition">Total Projects <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/domains.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition"><i class="fa-solid fa-network-wired text-cyan-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $domainCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-cyan-400/70 transition">Domains <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
</div>

<!-- Row 2: Operations -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <a href="/core_admin/service_tokens.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center group-hover:bg-amber-500/20 transition"><i class="fa-solid fa-ticket text-amber-400"></i></div>
            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full"><?= $pendingTokens ?> pending</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= number_format($serviceTokens) ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-amber-400/70 transition">Service Tokens <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/templates.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center group-hover:bg-indigo-500/20 transition"><i class="fa-solid fa-list-check text-indigo-400"></i></div>
            <span class="text-xs text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded-full"><?= $completedTasks ?> done</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $totalTasks ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-indigo-400/70 transition">Total Tasks <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/marketplace_tracking.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-teal-500/10 flex items-center justify-center group-hover:bg-teal-500/20 transition"><i class="fa-solid fa-globe text-teal-400"></i></div>
            <span class="text-xs text-teal-400 bg-teal-500/10 px-2 py-0.5 rounded-full"><?= number_format($uniqueVisitors) ?> visitors</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= number_format($trackingEvents) ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-teal-400/70 transition">Marketplace Views <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/reports.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center group-hover:bg-rose-500/20 transition"><i class="fa-solid fa-chart-pie text-rose-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $pendingTasks ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-rose-400/70 transition">Active Tasks <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
</div>

<!-- Row 3: Monitoring -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <a href="/core_admin/login_logs.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/10 flex items-center justify-center group-hover:bg-sky-500/20 transition"><i class="fa-solid fa-right-to-bracket text-sky-400"></i></div>
            <span class="text-xs text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded-full"><?= $todayLogins ?> today</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= number_format($loginCount) ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-sky-400/70 transition">Login Events <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/logs.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center group-hover:bg-orange-500/20 transition"><i class="fa-solid fa-shield-halved text-orange-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= number_format($auditCount) ?></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-orange-400/70 transition">Audit Logs <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/permissions.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-fuchsia-500/10 flex items-center justify-center group-hover:bg-fuchsia-500/20 transition"><i class="fa-solid fa-key text-fuchsia-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><i class="fa-solid fa-lock text-sm text-slate-500"></i></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-fuchsia-400/70 transition">Permissions <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
    <a href="/core_admin/settings.php" class="glass-card stat-glow p-5 rounded-2xl cursor-pointer group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-slate-500/10 flex items-center justify-center group-hover:bg-slate-500/20 transition"><i class="fa-solid fa-gear text-slate-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><i class="fa-solid fa-sliders text-sm text-slate-500"></i></div>
        <div class="text-xs text-slate-500 mt-0.5 group-hover:text-slate-300/70 transition">Settings <i class="fa-solid fa-arrow-right text-[8px] ml-1 opacity-0 group-hover:opacity-100 transition"></i></div>
    </a>
</div>

<!-- Role Breakdown + Task Chart -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Employees by Role</h3>
        <div class="space-y-3">
            <?php
            $roleLabels = ['core_admin'=>['Core Admin','emerald'], 'admin'=>['Admin','blue'], 'employee'=>['Employee','amber'], 'vendor'=>['Vendor','purple']];
            foreach ($roleLabels as $rk => [$rl, $rc]):
                $cnt = $roleMap[$rk] ?? 0;
                $pct = $userCount > 0 ? round(($cnt / $userCount) * 100) : 0;
            ?>
            <div>
                <div class="flex justify-between text-xs mb-1"><span class="text-slate-400"><?= $rl ?></span><span class="text-<?= $rc ?>-400"><?= $cnt ?></span></div>
                <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden"><div class="h-full bg-<?= $rc ?>-500/60 rounded-full" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="lg:col-span-2 glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Task Distribution</h3>
        <canvas id="taskChart" height="120"></canvas>
    </div>
</div>

<!-- Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Recent Logins -->
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Recent Login Activity</h3>
            <a href="/core_admin/login_logs.php" class="text-xs text-emerald-400 hover:text-emerald-300">View All</a>
        </div>
        <div class="space-y-2">
            <?php foreach ($recentLogins as $l): ?>
            <div class="flex items-center gap-3 py-2 border-b border-slate-800/50 last:border-0">
                <div class="w-2 h-2 rounded-full <?= $l['status'] === 'success' ? 'bg-emerald-500' : 'bg-red-500' ?> shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <span class="text-sm text-white"><?= htmlspecialchars($l['full_name'] ?? $l['username'] ?? 'Unknown') ?></span>
                    <span class="text-xs text-slate-600 ml-2"><?= $l['device_type'] ?> · <?= $l['browser'] ?></span>
                </div>
                <span class="text-[11px] text-slate-600 shrink-0"><?= timeAgo($l['created_at']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLogins)): ?><p class="text-sm text-slate-600 text-center py-4">No login activity yet</p><?php endif; ?>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Audit Trail</h3>
            <a href="/core_admin/logs.php" class="text-xs text-emerald-400 hover:text-emerald-300">View All</a>
        </div>
        <div class="space-y-2">
            <?php foreach ($recentAudit as $a): ?>
            <div class="flex items-start gap-3 py-2 border-b border-slate-800/50 last:border-0">
                <div class="w-7 h-7 rounded bg-slate-800/80 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-shield-halved text-slate-500 text-[10px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="text-sm text-white"><?= htmlspecialchars($a['username'] ?? 'System') ?></span>
                    <span class="text-xs text-slate-500"> — <?= htmlspecialchars($a['action']) ?></span>
                    <?php if ($a['entity_type']): ?><span class="text-xs text-slate-600"> on <?= $a['entity_type'] ?>#<?= $a['entity_id'] ?></span><?php endif; ?>
                </div>
                <span class="text-[11px] text-slate-600 shrink-0"><?= timeAgo($a['created_at']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentAudit)): ?><p class="text-sm text-slate-600 text-center py-4">No audit activity yet</p><?php endif; ?>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('taskChart').getContext('2d');
new Chart(ctx, {
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
        plugins: { legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 11 }, padding: 12, usePointStyle: true, pointStyleWidth: 8 } } }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
