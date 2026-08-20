<?php
$pageTitle = 'Domain Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create') {
        $pdo->prepare("INSERT INTO domains (name, description, approval_required) VALUES (?,?,?)")->execute([$_POST['name'], $_POST['description'], isset($_POST['approval_required'])?1:0]);
        logAudit($pdo, 'create_domain', 'domain', $pdo->lastInsertId(), 'Created domain: '.$_POST['name']);
        $msg = 'Domain created.';
    } elseif ($_POST['action'] === 'update') {
        $pdo->prepare("UPDATE domains SET name=?, description=?, approval_required=?, is_active=? WHERE id=?")->execute([$_POST['name'], $_POST['description'], isset($_POST['approval_required'])?1:0, isset($_POST['is_active'])?1:0, $_POST['domain_id']]);
        $msg = 'Domain updated.';
    }
}

$domains = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM users WHERE domain_id = d.id) as user_count, (SELECT COUNT(*) FROM projects WHERE domain_id = d.id) as project_count FROM domains d ORDER BY d.id")->fetchAll();
$rules = $pdo->query("SELECT ar.*, d.name as domain_name FROM approval_rules ar LEFT JOIN domains d ON ar.domain_id = d.id ORDER BY ar.domain_id")->fetchAll();
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500"><?= count($domains) ?> domains configured</p>
    <button onclick="document.getElementById('domainModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-plus mr-1.5"></i>Add Domain</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
    <?php foreach ($domains as $d): ?>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h4 class="font-semibold text-white"><?= htmlspecialchars($d['name']) ?></h4>
                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($d['description'] ?? '') ?></p>
            </div>
            <?php if ($d['is_active']): ?><span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">Active</span><?php else: ?><span class="text-xs text-slate-500 bg-slate-700/50 px-2 py-0.5 rounded-full">Disabled</span><?php endif; ?>
        </div>
        <div class="flex items-center gap-6 text-xs text-slate-400 mb-4">
            <span><i class="fa-solid fa-users mr-1 text-emerald-500/50"></i><?= $d['user_count'] ?> users</span>
            <span><i class="fa-solid fa-diagram-project mr-1 text-blue-500/50"></i><?= $d['project_count'] ?> projects</span>
            <?php if ($d['approval_required']): ?><span class="text-amber-400"><i class="fa-solid fa-shield-check mr-1"></i>Approval req.</span><?php endif; ?>
        </div>
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="action" value="update"><input type="hidden" name="domain_id" value="<?= $d['id'] ?>">
            <input type="text" name="name" value="<?= htmlspecialchars($d['name']) ?>" class="input-field flex-1 px-3 py-1.5 rounded text-xs">
            <input type="text" name="description" value="<?= htmlspecialchars($d['description']??'') ?>" class="input-field flex-1 px-3 py-1.5 rounded text-xs" placeholder="Description">
            <label class="flex items-center gap-1 text-xs text-slate-400"><input type="checkbox" name="approval_required" <?= $d['approval_required']?'checked':'' ?> class="accent-emerald-500">Approval</label>
            <label class="flex items-center gap-1 text-xs text-slate-400"><input type="checkbox" name="is_active" <?= $d['is_active']?'checked':'' ?> class="accent-emerald-500">Active</label>
            <button class="btn-secondary px-3 py-1.5 rounded text-xs text-slate-300">Save</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<h3 class="text-sm font-semibold text-white mb-4">Approval Rules</h3>
<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr><th class="px-5 py-3">Domain</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Approver</th><th class="px-5 py-3">Multi-Level</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($rules as $r): ?>
            <tr class="table-row">
                <td class="px-5 py-3 text-slate-300"><?= htmlspecialchars($r['domain_name']??'Global') ?></td>
                <td class="px-5 py-3 text-white font-medium"><?= ucwords(str_replace('_',' ',$r['action_type'])) ?></td>
                <td class="px-5 py-3 text-slate-400 capitalize"><?= str_replace('_',' ',$r['approver_role']) ?></td>
                <td class="px-5 py-3"><?= $r['multi_level'] ? '<span class="text-amber-400 text-xs">Yes</span>' : '<span class="text-slate-600 text-xs">No</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="domainModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-md shadow-2xl border border-slate-700/50">
        <h3 class="text-lg font-semibold text-white mb-4">Add Domain</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div><label class="block text-xs text-slate-400 mb-1.5">Name</label><input type="text" name="name" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Description</label><textarea name="description" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea></div>
            <label class="flex items-center gap-2 text-sm text-slate-400"><input type="checkbox" name="approval_required" class="accent-emerald-500">Require approval for actions</label>
            <div class="flex justify-end gap-3"><button type="button" onclick="document.getElementById('domainModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button><button class="btn-primary px-5 py-2 rounded-lg text-sm text-white">Create</button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
