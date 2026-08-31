<?php
$pageTitle = 'Task Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$uid = $_SESSION['user_id'];
$domainId = $_SESSION['domain_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, due_date, priority, type, project_id, estimated_hours) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['assigned_to']?:null, $uid, $_POST['due_date']?:null, $_POST['priority'], $_POST['type']?:'general', $_POST['project_id']?:null, $_POST['estimated_hours']?:null]);
        $taskId = $pdo->lastInsertId();
        logAudit($pdo, 'create_task', 'task', $taskId, 'Created: '.$_POST['title']);
        if ($_POST['assigned_to']) notify($pdo, $_POST['assigned_to'], 'New Task Assigned', $_POST['title'], 'task', '/employee/tasks.php');
        $msg = 'Task created.';
    } elseif ($_POST['action'] === 'reassign') {
        $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ? AND created_by = ?")->execute([$_POST['assigned_to'], $_POST['task_id'], $uid]);
        if ($_POST['assigned_to']) notify($pdo, $_POST['assigned_to'], 'Task Reassigned', 'A task has been reassigned to you.', 'task', '/employee/tasks.php');
        $msg = 'Task reassigned.';
    }
}

$filter = $_GET['status'] ?? '';
$sql = "SELECT t.*, u.full_name as assignee_name, p.name as project_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN projects p ON t.project_id = p.id WHERE t.created_by = ?";
$params = [$uid];
if ($filter) { $sql .= " AND t.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $tasks = $stmt->fetchAll();

$employees = $pdo->prepare("SELECT id, full_name, username FROM users WHERE domain_id = ? AND role = 'employee' AND status = 'active' ORDER BY full_name");
$employees->execute([$domainId]); $employees = $employees->fetchAll();
$projects = $pdo->prepare("SELECT id, name FROM projects WHERE domain_id = ? AND status = 'active'"); $projects->execute([$domainId]); $projects = $projects->fetchAll();
$templates = $pdo->prepare("SELECT * FROM task_templates WHERE domain_id = ? OR domain_id IS NULL ORDER BY name"); $templates->execute([$domainId]); $templates = $templates->fetchAll();
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-2">
        <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
        <?php foreach(['pending','in_progress','submitted','completed'] as $s): ?>
        <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
        <?php endforeach; ?>
    </div>
    <button onclick="document.getElementById('taskModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-plus mr-1.5"></i>New Task</button>
</div>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Assigned To</th><th class="px-5 py-3">Project</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Due</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($tasks as $t): ?>
            <tr class="table-row">
                <td class="px-5 py-3.5"><div class="font-medium text-white text-sm"><?= htmlspecialchars($t['title']) ?></div><div class="text-xs text-slate-600 mt-0.5 max-w-xs truncate"><?= htmlspecialchars($t['description']??'') ?></div></td>
                <td class="px-5 py-3.5 text-xs text-slate-300"><?= htmlspecialchars($t['assignee_name']??'Unassigned') ?></td>
                <td class="px-5 py-3.5 text-xs text-slate-500"><?= htmlspecialchars($t['project_name']??'—') ?></td>
                <td class="px-5 py-3.5"><?= priorityBadge($t['priority']) ?></td>
                <td class="px-5 py-3.5"><?= statusBadge($t['status']) ?></td>
                <td class="px-5 py-3.5 text-xs <?= $t['due_date'] && strtotime($t['due_date']) < time() ? 'text-red-400' : 'text-slate-500' ?>"><?= $t['due_date'] ? date('M j', strtotime($t['due_date'])) : '—' ?></td>
                <td class="px-5 py-3.5 text-right">
                    <?php if ($t['status'] === 'submitted'): ?>
                    <a href="approvals.php?task_id=<?= $t['id'] ?>" class="text-xs text-emerald-400 hover:text-emerald-300">Review</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?><tr><td colspan="7" class="text-center py-8 text-slate-600">No tasks found</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Create Task Modal -->
<div id="taskModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-white">Create Task</h3><button onclick="document.getElementById('taskModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <!-- Template quick fill -->
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Use Template (optional)</label>
                <select id="tplSelect" class="input-field w-full px-3 py-2 rounded-lg text-sm" onchange="fillTemplate(this)">
                    <option value="">— No template —</option>
                    <?php foreach ($templates as $tp): ?><option data-name="<?= htmlspecialchars($tp['name']) ?>" data-desc="<?= htmlspecialchars($tp['description']??'') ?>" data-type="<?= $tp['type'] ?>" data-priority="<?= $tp['default_priority'] ?>" data-hours="<?= $tp['estimated_hours'] ?>"><?= htmlspecialchars($tp['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Title</label><input type="text" name="title" id="taskTitle" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" id="taskDesc" rows="3" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Assign To</label><select name="assigned_to" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($employees as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']?:$e['username']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Project</label><select name="project_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($projects as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Priority</label><select name="priority" id="taskPriority" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Due Date</label><input type="date" name="due_date" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Est. Hours</label><input type="number" name="estimated_hours" id="taskHours" step="0.5" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <input type="hidden" name="type" id="taskType" value="general">
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="document.getElementById('taskModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button><button class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Create Task</button></div>
        </form>
    </div>
</div>
<script>
function fillTemplate(sel) {
    const opt = sel.selectedOptions[0];
    if (!opt.value) return;
    document.getElementById('taskTitle').value = opt.dataset.name || '';
    document.getElementById('taskDesc').value = opt.dataset.desc || '';
    document.getElementById('taskType').value = opt.dataset.type || 'general';
    document.getElementById('taskPriority').value = opt.dataset.priority || 'medium';
    document.getElementById('taskHours').value = opt.dataset.hours || '';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
