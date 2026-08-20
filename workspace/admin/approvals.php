<?php
$pageTitle = 'Approvals';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int)$_POST['task_id'];
    $action = $_POST['approval_action'];
    $comments = $_POST['comments'] ?? '';

    if ($action === 'approve') {
        $pdo->prepare("INSERT INTO task_approvals (task_id, approved_by, action, comments) VALUES (?,?,'approved',?)")->execute([$taskId, $uid, $comments]);
        $pdo->prepare("UPDATE tasks SET status = 'approved' WHERE id = ?")->execute([$taskId]);
        $task = $pdo->prepare("SELECT assigned_to, title FROM tasks WHERE id = ?"); $task->execute([$taskId]); $task = $task->fetch();
        if ($task['assigned_to']) notify($pdo, $task['assigned_to'], 'Task Approved!', $task['title'].' has been approved.', 'approval');
        logAudit($pdo, 'approve_task', 'task', $taskId, 'Approved with comments: '.$comments);
        $msg = 'Task approved.';
    } elseif ($action === 'reject') {
        $pdo->prepare("INSERT INTO task_approvals (task_id, approved_by, action, comments) VALUES (?,?,'rejected',?)")->execute([$taskId, $uid, $comments]);
        $pdo->prepare("UPDATE tasks SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$comments, $taskId]);
        $task = $pdo->prepare("SELECT assigned_to, title FROM tasks WHERE id = ?"); $task->execute([$taskId]); $task = $task->fetch();
        if ($task['assigned_to']) notify($pdo, $task['assigned_to'], 'Task Rejected', $task['title'].' — '.$comments, 'warning');
        logAudit($pdo, 'reject_task', 'task', $taskId, 'Rejected: '.$comments);
        $msg = 'Task rejected.';
    }
}

$submitted = $pdo->prepare("SELECT t.*, u.full_name as assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.created_by = ? AND t.status = 'submitted' ORDER BY t.created_at DESC");
$submitted->execute([$uid]); $submitted = $submitted->fetchAll();

$history = $pdo->prepare("SELECT ta.*, t.title, u.full_name as approver_name FROM task_approvals ta JOIN tasks t ON ta.task_id = t.id JOIN users u ON ta.approved_by = u.id WHERE ta.approved_by = ? ORDER BY ta.approved_at DESC LIMIT 20");
$history->execute([$uid]); $history = $history->fetchAll();
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="mb-8">
    <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-clock text-amber-400/50"></i>Pending Review <span class="text-xs text-slate-600 font-normal">(<?= count($submitted) ?>)</span></h3>
    <?php if (empty($submitted)): ?>
    <div class="glass-card p-8 rounded-2xl text-center"><i class="fa-solid fa-clipboard-check text-3xl text-slate-700 mb-3"></i><p class="text-slate-500 text-sm">No submissions pending review</p></div>
    <?php endif; ?>
    <div class="space-y-3">
        <?php foreach ($submitted as $t): ?>
        <div class="glass-card p-5 rounded-2xl">
            <div class="flex items-start justify-between mb-3">
                <div><h4 class="font-semibold text-white"><?= htmlspecialchars($t['title']) ?></h4><p class="text-xs text-slate-500 mt-0.5">By <?= htmlspecialchars($t['assignee_name']??'Unknown') ?> · <?= timeAgo($t['created_at']) ?></p></div>
                <?= priorityBadge($t['priority']) ?>
            </div>
            <?php if ($t['description']): ?><p class="text-sm text-slate-400 mb-3"><?= nl2br(htmlspecialchars($t['description'])) ?></p><?php endif; ?>
            <?php if ($t['submission_notes']): ?><div class="bg-slate-800/40 p-3 rounded-lg mb-3 text-xs"><span class="text-slate-500 font-medium">Submission Notes:</span><p class="text-slate-300 mt-1"><?= nl2br(htmlspecialchars($t['submission_notes'])) ?></p></div><?php endif; ?>
            <form method="POST" class="flex items-end gap-3">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <div class="flex-1"><label class="block text-[10px] text-slate-600 mb-1">Comments</label><input type="text" name="comments" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Optional feedback..."></div>
                <button name="approval_action" value="approve" class="btn-primary px-4 py-2 rounded-lg text-sm text-white"><i class="fa-solid fa-check mr-1"></i>Approve</button>
                <button name="approval_action" value="reject" class="btn-danger px-4 py-2 rounded-lg text-sm"><i class="fa-solid fa-times mr-1"></i>Reject</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<h3 class="text-sm font-semibold text-white mb-4">Approval History</h3>
<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50"><tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Decision</th><th class="px-5 py-3">Comments</th><th class="px-5 py-3">Date</th></tr></thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($history as $h): ?>
            <tr class="table-row">
                <td class="px-5 py-3 text-white text-sm"><?= htmlspecialchars($h['title']) ?></td>
                <td class="px-5 py-3"><?= statusBadge($h['action']) ?></td>
                <td class="px-5 py-3 text-xs text-slate-400 max-w-xs truncate"><?= htmlspecialchars($h['comments']??'—') ?></td>
                <td class="px-5 py-3 text-xs text-slate-500"><?= timeAgo($h['approved_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
