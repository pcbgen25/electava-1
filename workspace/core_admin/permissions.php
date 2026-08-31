<?php
$pageTitle = 'Module Permissions';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if (<?php
$pageTitle = 'Module Permissions';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle') {
    $uid = (int)$_POST['user_id'];
    $mid = (int)$_POST['module_id'];
    $enabled = (int)$_POST['enabled'];
    $pdo->prepare("INSERT INTO module_permissions (user_id, module_id, is_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_enabled = ?")->execute([$uid, $mid, $enabled, $enabled]);
    logAudit($pdo, 'toggle_permission', 'module_permission', $uid, "Module $mid set to ".($enabled?'enabled':'disabled'));
    $msg = 'Permission updated.';
}

$users = $pdo->query("SELECT id, username, full_name, role FROM users WHERE status = 'active' ORDER BY role, full_name")->fetchAll();
$modules = $pdo->query("SELECT * FROM modules ORDER BY id")->fetchAll();
$perms = $pdo->query("SELECT * FROM module_permissions")->fetchAll();
$permMap = [];
foreach ($perms as $p) $permMap[$p['user_id']][$p['module_id']] = $p['is_enabled'];
$filterRole = $_GET['role'] ?? '';
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="?role=" class="<?= !$filterRole?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filterRole?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['core_admin','admin','employee','vendor'] as $r): ?>
    <a href="?role=<?= $r ?>" class="<?= $filterRole===$r?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filterRole===$r?'text-white':'text-slate-400' ?> capitalize"><?= str_replace('_',' ',$r) ?></a>
    <?php endforeach; ?>
</div>

<div class="glass-card rounded-2xl overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr>
                <th class="px-4 py-3 sticky left-0 bg-slate-900/90 z-10">User</th>
                <?php foreach ($modules as $m): ?>
                <th class="px-3 py-3 text-center whitespace-nowrap"><i class="fa-solid <?= $m['icon'] ?> mr-1 text-slate-600"></i><?= $m['display_name'] ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($users as $u):
                if ($filterRole && $u['role'] !== $filterRole) continue;
            ?>
            <tr class="table-row">
                <td class="px-4 py-3 sticky left-0 bg-[#0a0f1a]/95 z-10">
                    <div class="font-medium text-white text-xs"><?= htmlspecialchars($u['full_name']?:$u['username']) ?></div>
                    <div class="text-[10px] text-slate-600 capitalize"><?= str_replace('_',' ',$u['role']) ?></div>
                </td>
                <?php foreach ($modules as $m):
                    $enabled = $permMap[$u['id']][$m['id']] ?? 1;
                ?>
                <td class="px-3 py-3 text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= $enabled ? 0 : 1 ?>">
                        <button class="w-8 h-8 rounded-lg <?= $enabled ? 'bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25' : 'bg-slate-800/50 text-slate-600 hover:bg-slate-800' ?> transition text-xs">
                            <i class="fa-solid <?= $enabled ? 'fa-check' : 'fa-times' ?>"></i>
                        </button>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_METHOD'] === 'POST' && <?php
$pageTitle = 'Module Permissions';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle') {
    $uid = (int)$_POST['user_id'];
    $mid = (int)$_POST['module_id'];
    $enabled = (int)$_POST['enabled'];
    $pdo->prepare("INSERT INTO module_permissions (user_id, module_id, is_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_enabled = ?")->execute([$uid, $mid, $enabled, $enabled]);
    logAudit($pdo, 'toggle_permission', 'module_permission', $uid, "Module $mid set to ".($enabled?'enabled':'disabled'));
    $msg = 'Permission updated.';
}

$users = $pdo->query("SELECT id, username, full_name, role FROM users WHERE status = 'active' ORDER BY role, full_name")->fetchAll();
$modules = $pdo->query("SELECT * FROM modules ORDER BY id")->fetchAll();
$perms = $pdo->query("SELECT * FROM module_permissions")->fetchAll();
$permMap = [];
foreach ($perms as $p) $permMap[$p['user_id']][$p['module_id']] = $p['is_enabled'];
$filterRole = $_GET['role'] ?? '';
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="?role=" class="<?= !$filterRole?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filterRole?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['core_admin','admin','employee','vendor'] as $r): ?>
    <a href="?role=<?= $r ?>" class="<?= $filterRole===$r?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filterRole===$r?'text-white':'text-slate-400' ?> capitalize"><?= str_replace('_',' ',$r) ?></a>
    <?php endforeach; ?>
</div>

<div class="glass-card rounded-2xl overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr>
                <th class="px-4 py-3 sticky left-0 bg-slate-900/90 z-10">User</th>
                <?php foreach ($modules as $m): ?>
                <th class="px-3 py-3 text-center whitespace-nowrap"><i class="fa-solid <?= $m['icon'] ?> mr-1 text-slate-600"></i><?= $m['display_name'] ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($users as $u):
                if ($filterRole && $u['role'] !== $filterRole) continue;
            ?>
            <tr class="table-row">
                <td class="px-4 py-3 sticky left-0 bg-[#0a0f1a]/95 z-10">
                    <div class="font-medium text-white text-xs"><?= htmlspecialchars($u['full_name']?:$u['username']) ?></div>
                    <div class="text-[10px] text-slate-600 capitalize"><?= str_replace('_',' ',$u['role']) ?></div>
                </td>
                <?php foreach ($modules as $m):
                    $enabled = $permMap[$u['id']][$m['id']] ?? 1;
                ?>
                <td class="px-3 py-3 text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= $enabled ? 0 : 1 ?>">
                        <button class="w-8 h-8 rounded-lg <?= $enabled ? 'bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25' : 'bg-slate-800/50 text-slate-600 hover:bg-slate-800' ?> transition text-xs">
                            <i class="fa-solid <?= $enabled ? 'fa-check' : 'fa-times' ?>"></i>
                        </button>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
POST['action'] === 'toggle') {
    requireCsrf();
    $uid = (int)$_POST['user_id'];
    $mid = (int)$_POST['module_id'];
    $enabled = (int)$_POST['enabled'];
    $pdo->prepare("INSERT INTO module_permissions (user_id, module_id, is_enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_enabled = ?")->execute([$uid, $mid, $enabled, $enabled]);
    logAudit($pdo, 'toggle_permission', 'module_permission', $uid, "Module $mid set to ".($enabled?'enabled':'disabled'));
    $msg = 'Permission updated.';
}

$users = $pdo->query("SELECT id, username, full_name, role FROM users WHERE status = 'active' ORDER BY role, full_name")->fetchAll();
$modules = $pdo->query("SELECT * FROM modules ORDER BY id")->fetchAll();
$perms = $pdo->query("SELECT * FROM module_permissions")->fetchAll();
$permMap = [];
foreach ($perms as $p) $permMap[$p['user_id']][$p['module_id']] = $p['is_enabled'];
$filterRole = $_GET['role'] ?? '';
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="?role=" class="<?= !$filterRole?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filterRole?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['core_admin','admin','employee','vendor'] as $r): ?>
    <a href="?role=<?= $r ?>" class="<?= $filterRole===$r?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filterRole===$r?'text-white':'text-slate-400' ?> capitalize"><?= str_replace('_',' ',$r) ?></a>
    <?php endforeach; ?>
</div>

<div class="glass-card rounded-2xl overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
            <tr>
                <th class="px-4 py-3 sticky left-0 bg-slate-900/90 z-10">User</th>
                <?php foreach ($modules as $m): ?>
                <th class="px-3 py-3 text-center whitespace-nowrap"><i class="fa-solid <?= $m['icon'] ?> mr-1 text-slate-600"></i><?= $m['display_name'] ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($users as $u):
                if ($filterRole && $u['role'] !== $filterRole) continue;
            ?>
            <tr class="table-row">
                <td class="px-4 py-3 sticky left-0 bg-[#0a0f1a]/95 z-10">
                    <div class="font-medium text-white text-xs"><?= htmlspecialchars($u['full_name']?:$u['username']) ?></div>
                    <div class="text-[10px] text-slate-600 capitalize"><?= str_replace('_',' ',$u['role']) ?></div>
                </td>
                <?php foreach ($modules as $m):
                    $enabled = $permMap[$u['id']][$m['id']] ?? 1;
                ?>
                <td class="px-3 py-3 text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= $enabled ? 0 : 1 ?>">
                        <button class="w-8 h-8 rounded-lg <?= $enabled ? 'bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25' : 'bg-slate-800/50 text-slate-600 hover:bg-slate-800' ?> transition text-xs">
                            <i class="fa-solid <?= $enabled ? 'fa-check' : 'fa-times' ?>"></i>
                        </button>
                    </form>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
