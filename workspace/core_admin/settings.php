<?php
$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if (<?php
$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $sKey = substr($key, 8);
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $sKey]);
        }
    }
    logAudit($pdo, 'update_settings', 'system', null, 'System settings updated');
    $msg = 'Settings saved.';
}

$settings = $pdo->query("SELECT * FROM system_settings ORDER BY setting_group, setting_key")->fetchAll();
$groups = [];
foreach ($settings as $s) $groups[$s['setting_group']][] = $s;
$groupLabels = ['general' => ['General', 'fa-globe'], 'security' => ['Security', 'fa-shield-halved'], 'email' => ['Email / SMTP', 'fa-envelope']];
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<form method="POST" class="space-y-6">
    <?php foreach ($groups as $group => $items): ?>
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid <?= $groupLabels[$group][1] ?? 'fa-cog' ?> text-emerald-400/50"></i>
            <?= $groupLabels[$group][0] ?? ucfirst($group) ?>
        </h3>
        <div class="space-y-4">
            <?php foreach ($items as $s): ?>
            <div class="flex items-center gap-4">
                <label class="w-48 text-xs text-slate-400 shrink-0"><?= ucwords(str_replace('_', ' ', $s['setting_key'])) ?></label>
                <?php if ($s['setting_key'] === 'maintenance_mode'): ?>
                    <select name="setting_<?= $s['setting_key'] ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                        <option value="0" <?= $s['setting_value']==='0'?'selected':'' ?>>Disabled</option>
                        <option value="1" <?= $s['setting_value']==='1'?'selected':'' ?>>Enabled</option>
                    </select>
                <?php elseif (strpos($s['setting_key'], 'pass') !== false): ?>
                    <input type="password" name="setting_<?= $s['setting_key'] ?>" value="<?= htmlspecialchars($s['setting_value']??'') ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                <?php else: ?>
                    <input type="text" name="setting_<?= $s['setting_key'] ?>" value="<?= htmlspecialchars($s['setting_value']??'') ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="flex justify-end"><button type="submit" class="btn-primary px-6 py-2.5 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-save mr-2"></i>Save Settings</button></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $sKey = substr($key, 8);
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $sKey]);
        }
    }
    logAudit($pdo, 'update_settings', 'system', null, 'System settings updated');
    $msg = 'Settings saved.';
}

$settings = $pdo->query("SELECT * FROM system_settings ORDER BY setting_group, setting_key")->fetchAll();
$groups = [];
foreach ($settings as $s) $groups[$s['setting_group']][] = $s;
$groupLabels = ['general' => ['General', 'fa-globe'], 'security' => ['Security', 'fa-shield-halved'], 'email' => ['Email / SMTP', 'fa-envelope']];
?>
<?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm"><i class="fa-solid fa-check-circle mr-1"></i><?= $msg ?></div><?php endif; ?>

<form method="POST" class="space-y-6">
    <?php foreach ($groups as $group => $items): ?>
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid <?= $groupLabels[$group][1] ?? 'fa-cog' ?> text-emerald-400/50"></i>
            <?= $groupLabels[$group][0] ?? ucfirst($group) ?>
        </h3>
        <div class="space-y-4">
            <?php foreach ($items as $s): ?>
            <div class="flex items-center gap-4">
                <label class="w-48 text-xs text-slate-400 shrink-0"><?= ucwords(str_replace('_', ' ', $s['setting_key'])) ?></label>
                <?php if ($s['setting_key'] === 'maintenance_mode'): ?>
                    <select name="setting_<?= $s['setting_key'] ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                        <option value="0" <?= $s['setting_value']==='0'?'selected':'' ?>>Disabled</option>
                        <option value="1" <?= $s['setting_value']==='1'?'selected':'' ?>>Enabled</option>
                    </select>
                <?php elseif (strpos($s['setting_key'], 'pass') !== false): ?>
                    <input type="password" name="setting_<?= $s['setting_key'] ?>" value="<?= htmlspecialchars($s['setting_value']??'') ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                <?php else: ?>
                    <input type="text" name="setting_<?= $s['setting_key'] ?>" value="<?= htmlspecialchars($s['setting_value']??'') ?>" class="input-field flex-1 px-3 py-2 rounded-lg text-sm">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="flex justify-end"><button type="submit" class="btn-primary px-6 py-2.5 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-save mr-2"></i>Save Settings</button></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
