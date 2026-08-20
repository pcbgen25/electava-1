<?php
$pageTitle = 'My Submissions';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];

// Fetch submitted / approved / rejected tasks
$stmt = $pdo->prepare("
    SELECT t.*, p.name as project_name, c.full_name as creator_name,
           ta.comments as approval_comments, ta.action as approval_action, ta.approved_at,
           a.full_name as approver_name
    FROM tasks t 
    LEFT JOIN projects p ON t.project_id = p.id 
    LEFT JOIN users c ON t.created_by = c.id
    LEFT JOIN task_approvals ta ON ta.task_id = t.id
    LEFT JOIN users a ON ta.approved_by = a.id
    WHERE t.assigned_to = ? AND t.status IN ('submitted','approved','rejected','completed')
    ORDER BY t.updated_at DESC
");
$stmt->execute([$uid]);
$submissions = $stmt->fetchAll();

$submittedCount = count(array_filter($submissions, fn($s) => $s['status'] === 'submitted'));
$approvedCount = count(array_filter($submissions, fn($s) => in_array($s['status'], ['approved', 'completed'])));
$rejectedCount = count(array_filter($submissions, fn($s) => $s['status'] === 'rejected'));
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">My Submissions</h2>
    <p class="text-sm text-slate-500 mt-1">Track all your submitted tasks and their approval status.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-hourglass-half text-purple-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $submittedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending Review</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-check-double text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $approvedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Approved</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center"><i class="fa-solid fa-times-circle text-red-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $rejectedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Rejected</div>
    </div>
</div>

<!-- Submissions List -->
<div class="space-y-4">
    <?php foreach ($submissions as $s): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50 hover:border-<?= $s['status'] === 'approved' || $s['status'] === 'completed' ? 'emerald' : ($s['status'] === 'rejected' ? 'red' : 'purple') ?>-500/20 transition">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-base font-semibold text-white"><?= htmlspecialchars($s['title']) ?></h3>
                    <?= statusBadge($s['status']) ?>
                    <?= priorityBadge($s['priority']) ?>
                </div>
                <?php if ($s['description']): ?>
                <p class="text-sm text-slate-400 mb-2 max-w-2xl"><?= htmlspecialchars($s['description']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-4 text-xs text-slate-500">
                    <?php if ($s['project_name']): ?>
                    <span><i class="fa-solid fa-diagram-project mr-1"></i><?= htmlspecialchars($s['project_name']) ?></span>
                    <?php endif; ?>
                    <span><i class="fa-solid fa-user mr-1"></i>Assigned by <?= htmlspecialchars($s['creator_name'] ?? 'System') ?></span>
                    <?php if ($s['due_date']): ?>
                    <span><i class="fa-regular fa-calendar mr-1"></i>Due: <?= date('M j, Y', strtotime($s['due_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right text-xs text-slate-600 shrink-0 ml-4">
                <?= timeAgo($s['updated_at']) ?>
            </div>
        </div>

        <?php if ($s['submission_notes']): ?>
        <div class="mt-3 bg-slate-800/40 rounded-xl p-3 border border-slate-700/30">
            <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Your Submission Notes</div>
            <p class="text-sm text-slate-300"><?= nl2br(htmlspecialchars($s['submission_notes'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($s['approval_action']): ?>
        <div class="mt-3 bg-<?= $s['approval_action'] === 'approved' ? 'emerald' : 'red' ?>-500/5 rounded-xl p-3 border border-<?= $s['approval_action'] === 'approved' ? 'emerald' : 'red' ?>-500/15">
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-<?= $s['approval_action'] === 'approved' ? 'check-circle text-emerald-400' : 'times-circle text-red-400' ?> text-sm"></i>
                <span class="text-xs font-medium text-<?= $s['approval_action'] === 'approved' ? 'emerald' : 'red' ?>-400">
                    <?= ucfirst($s['approval_action']) ?> by <?= htmlspecialchars($s['approver_name'] ?? 'Manager') ?>
                </span>
                <span class="text-[10px] text-slate-600"><?= $s['approved_at'] ? timeAgo($s['approved_at']) : '' ?></span>
            </div>
            <?php if ($s['approval_comments']): ?>
            <p class="text-sm text-slate-300 ml-5"><?= nl2br(htmlspecialchars($s['approval_comments'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($s['status'] === 'rejected'): ?>
        <div class="mt-3 flex justify-end">
            <a href="tasks.php?status=rejected" class="text-xs bg-amber-600/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg hover:bg-amber-600/40 transition font-medium">
                <i class="fa-solid fa-redo mr-1"></i>Rework Task
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($submissions)): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4">
            <i class="fa-solid fa-paper-plane text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Submissions Yet</h3>
        <p class="text-sm text-slate-600 max-w-sm mx-auto">When you submit completed tasks for review, they will appear here with their approval status.</p>
        <a href="tasks.php" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium mt-4 inline-block">
            <i class="fa-solid fa-list-check mr-1.5"></i>View My Tasks
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
