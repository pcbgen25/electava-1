<?php
$pageTitle = 'Employee Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];

// Real stats
$openTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('pending','in_progress')");
$openTasks->execute([$uid]); $openCount = $openTasks->fetchColumn();

$highPriority = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('pending','in_progress') AND priority IN ('high','critical')");
$highPriority->execute([$uid]); $highCount = $highPriority->fetchColumn();

$completedWeek = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('completed','approved') AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$completedWeek->execute([$uid]); $weekDone = $completedWeek->fetchColumn();

$pendingApproval = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'submitted'");
$pendingApproval->execute([$uid]); $approvalCount = $pendingApproval->fetchColumn();

$overdue = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status NOT IN ('completed','approved')");
$overdue->execute([$uid]); $overdueCount = $overdue->fetchColumn();

// Recent tasks
$recentTasks = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? AND t.status IN ('pending','in_progress') ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC LIMIT 8");
$recentTasks->execute([$uid]); $tasks = $recentTasks->fetchAll();

// Recent notifications
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$uid]); $recentNotifs = $notifs->fetchAll();

// Total completed
$totalCompleted = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('completed','approved')");
$totalCompleted->execute([$uid]); $allDone = $totalCompleted->fetchColumn();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></h2>
    <p class="text-sm text-slate-500 mt-1">Here's your task overview for today.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="glass-card stat-glow p-5 rounded-2xl relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-xl"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-list-check text-emerald-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $openCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Open Tasks</div>
        <?php if ($highCount > 0): ?>
        <div class="text-[10px] text-amber-400 mt-1"><i class="fa-solid fa-arrow-up mr-1"></i><?= $highCount ?> High Priority</div>
        <?php endif; ?>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-fire text-blue-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $weekDone ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Done This Week</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-hourglass-half text-purple-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $approvalCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Awaiting Approval</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center"><i class="fa-solid fa-exclamation-triangle text-red-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-<?= $overdueCount > 0 ? 'red' : 'white' ?>-400"><?= $overdueCount ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Overdue</div>
    </div>
    <div class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-trophy text-cyan-400"></i></div>
        </div>
        <div class="text-2xl font-bold text-white"><?= $allDone ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Total Completed</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <?php if (hasDomainAccess(1)): ?>
    <div class="glass-card p-5 rounded-2xl border border-slate-700/50 hover:border-emerald-500/30 transition">
        <h3 class="text-base font-semibold text-white mb-3"><i class="fa-solid fa-microchip text-emerald-400 mr-2"></i>Marketplace Components</h3>
        <p class="text-sm text-slate-400 mb-4">Manage electronic components, update inventory datasheets, and verify parts for the Electava Marketplace.</p>
        <a href="components.php" class="btn-primary inline-flex items-center px-4 py-2 rounded-lg text-sm text-white font-medium">Open Components Portal</a>
    </div>
    <?php endif; ?>

    <?php if (hasDomainAccess(2)): ?>
    <div class="glass-card p-5 rounded-2xl border border-slate-700/50 hover:border-blue-500/30 transition">
        <h3 class="text-base font-semibold text-white mb-3"><i class="fa-solid fa-cogs text-blue-400 mr-2"></i>PCB Services Operations</h3>
        <p class="text-sm text-slate-400 mb-4">Review PCB fabrication requests, update assembly statuses, and attach assigned project deliverables.</p>
        <a href="services.php" class="inline-flex items-center px-4 py-2 rounded-lg text-sm bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 font-medium transition border border-blue-500/40">Open Service Requests</a>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- My Tasks -->
    <div class="lg:col-span-2 glass-card p-5 rounded-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-white">Active Tasks</h3>
            <a href="tasks.php" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="space-y-2">
            <?php foreach ($tasks as $t): ?>
            <div class="flex items-center gap-3 p-3 bg-slate-800/20 rounded-xl border border-slate-700/30 hover:border-emerald-500/20 transition group">
                <div class="w-8 h-8 rounded-lg <?= $t['status'] === 'in_progress' ? 'bg-blue-500/10' : 'bg-amber-500/10' ?> flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= $t['status'] === 'in_progress' ? 'fa-spinner fa-spin-pulse text-blue-400' : 'fa-clock text-amber-400' ?> text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-white truncate"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="text-[10px] text-slate-600 flex items-center gap-2 mt-0.5">
                        <?= statusBadge($t['status']) ?>
                        <?php if ($t['project_name']): ?><span><?= htmlspecialchars($t['project_name']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <?= priorityBadge($t['priority']) ?>
                    <?php if ($t['due_date']): ?>
                    <div class="text-[10px] mt-1 <?= strtotime($t['due_date']) < time() ? 'text-red-400' : 'text-slate-600' ?>">
                        <?= date('M j', strtotime($t['due_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <div class="text-center py-8">
                <i class="fa-solid fa-check-circle text-emerald-500/30 text-3xl mb-3"></i>
                <p class="text-sm text-slate-500">All caught up! No active tasks.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications -->
    <div class="glass-card p-5 rounded-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-white">Notifications</h3>
            <a href="/notifications.php" class="text-xs text-emerald-400 hover:text-emerald-300">View All</a>
        </div>
        <div class="space-y-2">
            <?php foreach ($recentNotifs as $n): ?>
            <div class="p-3 bg-slate-800/20 rounded-xl border border-slate-700/30 <?= !$n['is_read'] ? 'border-l-2 border-l-emerald-500' : '' ?>">
                <div class="text-sm text-white font-medium"><?= htmlspecialchars($n['title']) ?></div>
                <div class="text-xs text-slate-500 mt-0.5 line-clamp-2"><?= htmlspecialchars($n['message'] ?? '') ?></div>
                <div class="text-[10px] text-slate-600 mt-1"><?= timeAgo($n['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentNotifs)): ?>
            <p class="text-sm text-slate-600 text-center py-6">No notifications yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
