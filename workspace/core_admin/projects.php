<?php
$pageTitle = 'Projects';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("INSERT INTO projects (name, description, domain_id, assigned_to, priority, start_date, end_date, budget, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['domain_id']?:null, $_POST['assigned_to']?:null, $_POST['priority'], $_POST['start_date']?:null, $_POST['end_date']?:null, $_POST['budget']?:null, $_SESSION['user_id']]);
        logAudit($pdo, 'create_project', 'project', $pdo->lastInsertId(), "Created project: ".$_POST['name']);
        if ($_POST['assigned_to']) notify($pdo, $_POST['assigned_to'], 'New Project Assigned', 'You have been assigned to project: '.$_POST['name'], 'task', '/sub-core/');
        $msg = 'Project created.';
    } elseif ($_POST['action'] === 'update_status') {
        $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?")->execute([$_POST['status'], $_POST['project_id']]);
        logAudit($pdo, 'update_project', 'project', $_POST['project_id'], 'Status changed to '.$_POST['status']);
        $msg = 'Project status updated.';
    }
}

$projects = $pdo->query("SELECT p.*, d.name as domain_name, u.full_name as assignee_name, c.full_name as creator_name FROM projects p LEFT JOIN domains d ON p.domain_id = d.id LEFT JOIN users u ON p.assigned_to = u.id LEFT JOIN users c ON p.created_by = c.id ORDER BY p.created_at DESC")->fetchAll();
$subCores = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
$domains = $pdo->query("SELECT * FROM domains ORDER BY name")->fetchAll();
?>

<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500"><?= count($projects) ?> projects total</p>
    <button onclick="document.getElementById('createProjectModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium text-white"><i class="fa-solid fa-plus mr-1.5"></i>New Project</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($projects as $p): ?>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
                <h4 class="font-semibold text-white text-sm"><?= htmlspecialchars($p['name']) ?></h4>
                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($p['domain_name'] ?? 'No domain') ?></p>
            </div>
            <?= statusBadge($p['status']) ?>
        </div>
        <p class="text-xs text-slate-400 mb-4 line-clamp-2"><?= htmlspecialchars($p['description'] ?? '') ?></p>
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2 text-slate-500">
                <i class="fa-solid fa-user text-[10px]"></i>
                <span><?= htmlspecialchars($p['assignee_name'] ?? 'Unassigned') ?></span>
            </div>
            <?= priorityBadge($p['priority']) ?>
        </div>
        <?php if ($p['start_date'] || $p['end_date']): ?>
        <div class="mt-3 pt-3 border-t border-slate-800/40 text-[11px] text-slate-600">
            <i class="fa-regular fa-calendar mr-1"></i>
            <?= $p['start_date'] ? date('M j', strtotime($p['start_date'])) : '?' ?> — <?= $p['end_date'] ? date('M j, Y', strtotime($p['end_date'])) : 'Ongoing' ?>
        </div>
        <?php endif; ?>
        <div class="mt-3 pt-3 border-t border-slate-800/40 flex gap-2">
            <form method="POST" class="flex gap-1">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                <select name="status" class="input-field px-2 py-1 rounded text-xs" onchange="this.form.submit()">
                    <?php foreach(['active','on_hold','completed','archived'] as $s): ?><option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($projects)): ?>
    <div class="col-span-full glass-card p-12 rounded-2xl text-center"><i class="fa-solid fa-diagram-project text-3xl text-slate-700 mb-3"></i><p class="text-slate-500 text-sm">No projects yet. Create your first one!</p></div>
    <?php endif; ?>
</div>

<!-- Create Project Modal -->
<div id="createProjectModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white">Create Project</h3>
            <button onclick="document.getElementById('createProjectModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div><label class="block text-xs text-slate-400 mb-1.5">Project Name</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="3" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Assign to Sub-Core</label>
                    <select name="assigned_to" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($subCores as $sc): ?><option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['full_name']?:$sc['username']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Domain</label>
                    <select name="domain_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">—</option><?php foreach($domains as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Priority</label><select name="priority" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Start</label><input type="date" name="start_date" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">End</label><input type="date" name="end_date" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Budget (₹)</label><input type="number" name="budget" step="0.01" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createProjectModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">Create Project</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
