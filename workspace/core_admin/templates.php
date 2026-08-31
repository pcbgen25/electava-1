<?php
$pageTitle = 'Task Templates';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if ($_POST['action'] === 'create') {
        $pdo->prepare("INSERT INTO task_templates (name, description, type, default_priority, estimated_hours, domain_id) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['name'], $_POST['description'], $_POST['type'], $_POST['default_priority'], $_POST['estimated_hours']?:null, $_POST['domain_id']?:null]);
        logAudit($pdo, 'create_template', 'task_template', $pdo->lastInsertId(), 'Created template: '.$_POST['name']);
        $msg = 'Template created.';
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM task_templates WHERE id = ?")->execute([$_POST['template_id']]);
        $msg = 'Template deleted.';
    }
}
$templates = $pdo->query("SELECT t.*, d.name as domain_name FROM task_templates t LEFT JOIN domains d ON t.domain_id = d.id ORDER BY t.domain_id, t.name")->fetchAll();
$domains = $pdo->query("SELECT * FROM domains ORDER BY name")->fetchAll();
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500"><?= count($templates) ?> templates</p>
    <button onclick="document.getElementById('tplModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-plus mr-1.5"></i>New Template</button>
</div>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr><th class="px-5 py-3">Template Name</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Domain</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Est. Hours</th><th class="px-5 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($templates as $t): ?>
            <tr class="table-row">
                <td class="px-5 py-3.5"><div class="font-medium text-white text-sm"><?= htmlspecialchars($t['name']) ?></div><div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($t['description']??'') ?></div></td>
                <td class="px-5 py-3.5 text-xs text-slate-400 font-mono"><?= $t['type'] ?></td>
                <td class="px-5 py-3.5 text-xs text-slate-400"><?= htmlspecialchars($t['domain_name']??'Global') ?></td>
                <td class="px-5 py-3.5"><?= priorityBadge($t['default_priority']) ?></td>
                <td class="px-5 py-3.5 text-xs text-slate-400"><?= $t['estimated_hours'] ? $t['estimated_hours'].'h' : '—' ?></td>
                <td class="px-5 py-3.5 text-right">
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this template?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="template_id" value="<?= $t['id'] ?>"><button class="text-slate-500 hover:text-red-400 text-xs"><i class="fa-solid fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="tplModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-semibold text-white">Create Task Template</h3><button onclick="document.getElementById('tplModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fa-solid fa-times"></i></button></div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div><label class="block text-xs text-slate-400 mb-1.5">Name</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="e.g. Create Component Listing"></div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Type Key</label><input type="text" name="type" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="component_create"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Domain</label><select name="domain_id" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="">Global</option><?php foreach($domains as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Default Priority</label><select name="default_priority" class="input-field w-full px-3 py-2 rounded-lg text-sm"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Est. Hours</label><input type="number" name="estimated_hours" step="0.5" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="document.getElementById('tplModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button><button class="btn-primary px-5 py-2 rounded-lg text-sm text-white">Create</button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
