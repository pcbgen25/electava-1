<?php
$pageTitle = 'My Tasks';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];
$msg = '';

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $taskId = (int)($_POST['task_id'] ?? 0);
    
    if ($_POST['action'] === 'start') {
        $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ? AND assigned_to = ? AND status = 'pending'")->execute([$taskId, $uid]);
        logAudit($pdo, 'start_task', 'task', $taskId, 'Employee started work');
        $msg = 'Task started! Good luck.';
    } elseif ($_POST['action'] === 'submit') {
        $notes = trim($_POST['submission_notes'] ?? '');
        $pdo->prepare("UPDATE tasks SET status = 'submitted', submission_notes = ? WHERE id = ? AND assigned_to = ? AND status = 'in_progress'")->execute([$notes, $taskId, $uid]);
        logAudit($pdo, 'submit_task', 'task', $taskId, 'Submitted for approval');
        // Notify creator
        $creator = $pdo->prepare("SELECT created_by FROM tasks WHERE id = ?"); $creator->execute([$taskId]);
        $creatorId = $creator->fetchColumn();
        if ($creatorId) notify($pdo, $creatorId, 'Task Submitted', $_SESSION['full_name'] . ' submitted a task for review.', 'approval', '/admin/approvals.php');
        $msg = 'Task submitted for approval!';
    }
}

// Filters
$filter = $_GET['status'] ?? '';
$sql = "SELECT t.*, p.name as project_name, c.full_name as creator_name 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users c ON t.created_by = c.id 
        WHERE t.assigned_to = ?";
$params = [$uid];
if ($filter) { $sql .= " AND t.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $tasks = $stmt->fetchAll();

// Stats
$totalTasks = count($tasks);
$pendingCount = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$inProgressCount = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$submittedCount = count(array_filter($tasks, fn($t) => $t['status'] === 'submitted'));
$completedCount = count(array_filter($tasks, fn($t) => in_array($t['status'], ['completed', 'approved'])));
$overdue = count(array_filter($tasks, fn($t) => $t['due_date'] && strtotime($t['due_date']) < time() && !in_array($t['status'], ['completed', 'approved'])));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">My Tasks</h2>
    <p class="text-sm text-slate-500 mt-1">View and manage tasks assigned to you. Start work, then submit when complete.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-slate-500/10 flex items-center justify-center"><i class="fa-solid fa-list text-slate-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalTasks ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Total Tasks</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-clock text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $pendingCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Pending</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-spinner text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $inProgressCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">In Progress</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $completedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Completed</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center"><i class="fa-solid fa-exclamation text-red-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $overdue ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Overdue</div>
    </div>
</div>

<!-- Filters -->
<div class="flex items-center gap-2 mb-5">
    <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['pending','in_progress','submitted','approved','completed'] as $s): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
</div>

<!-- Tasks Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Task</th>
                <th class="px-5 py-4 font-semibold">Project</th>
                <th class="px-5 py-4 font-semibold">Priority</th>
                <th class="px-5 py-4 font-semibold">Status</th>
                <th class="px-5 py-4 font-semibold">Due Date</th>
                <th class="px-5 py-4 font-semibold text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($tasks as $t): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4 max-w-xs">
                    <div class="font-medium text-white"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="text-xs text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($t['description'] ?? '') ?></div>
                    <?php if ($t['creator_name']): ?><div class="text-[10px] text-slate-600 mt-1">From: <?= htmlspecialchars($t['creator_name']) ?></div><?php endif; ?>
                </td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= htmlspecialchars($t['project_name'] ?? '—') ?></td>
                <td class="px-5 py-4"><?= priorityBadge($t['priority']) ?></td>
                <td class="px-5 py-4"><?= statusBadge($t['status']) ?></td>
                <td class="px-5 py-4 text-xs <?= $t['due_date'] && strtotime($t['due_date']) < time() && !in_array($t['status'], ['completed','approved']) ? 'text-red-400 font-medium' : 'text-slate-400' ?>">
                    <?= $t['due_date'] ? date('M j, Y', strtotime($t['due_date'])) : '—' ?>
                    <?php if ($t['due_date'] && strtotime($t['due_date']) < time() && !in_array($t['status'], ['completed','approved'])): ?>
                    <span class="block text-[10px] text-red-400/70">Overdue</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-4 text-right">
                    <?php if ($t['status'] === 'pending'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                        <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                            <i class="fa-solid fa-play mr-1"></i>Start
                        </button>
                    </form>
                    <?php elseif ($t['status'] === 'in_progress'): ?>
                    <button onclick="openSubmitModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['title']), ENT_QUOTES) ?>')" class="text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-lg hover:bg-blue-600/40 transition font-medium">
                        <i class="fa-solid fa-paper-plane mr-1"></i>Submit
                    </button>
                    <?php elseif ($t['status'] === 'submitted'): ?>
                    <span class="text-xs text-purple-400"><i class="fa-solid fa-hourglass-half mr-1"></i>Awaiting Review</span>
                    <?php elseif ($t['status'] === 'approved' || $t['status'] === 'completed'): ?>
                    <span class="text-xs text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>Done</span>
                    <?php elseif ($t['status'] === 'rejected'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                        <button class="text-xs bg-amber-600/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg hover:bg-amber-600/40 transition font-medium">
                            <i class="fa-solid fa-redo mr-1"></i>Retry
                        </button>
                    </form>
                    <?php if ($t['rejection_reason']): ?>
                    <div class="text-[10px] text-red-400 mt-1 max-w-[150px] truncate" title="<?= htmlspecialchars($t['rejection_reason']) ?>">
                        <?= htmlspecialchars($t['rejection_reason']) ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">No tasks found. Tasks assigned to you will appear here.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Submit Modal -->
<div id="submitModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white"><i class="fa-solid fa-paper-plane text-blue-400 mr-2"></i>Submit Task</h3>
            <button onclick="document.getElementById('submitModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <p class="text-sm text-slate-400 mb-4">Task: <span id="submitTaskName" class="text-white font-medium"></span></p>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Submission Notes</label>
                <textarea name="submission_notes" rows="4" placeholder="Describe what you completed, any issues, or notes for the reviewer..." class="input-field w-full px-3 py-2 rounded-xl text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('submitModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-paper-plane mr-1.5"></i>Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSubmitModal(taskId, taskName) {
    document.getElementById('submitTaskId').value = taskId;
    document.getElementById('submitTaskName').textContent = taskName;
    document.getElementById('submitModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
