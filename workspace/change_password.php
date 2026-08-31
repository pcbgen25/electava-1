<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/includes/header.php';

$uid = (int) $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    try {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            throw new RuntimeException('Current password is not correct.');
        }

        if (strlen($newPassword) < 8) {
            throw new RuntimeException('New password must be at least 8 characters.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New password and confirmation do not match.');
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            throw new RuntimeException('New password must be different from your current password.');
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 0 WHERE id = ?");
        $update->execute([$passwordHash, $uid]);

        logAudit($pdo, 'change_password', 'employee', $uid, 'User changed their own workspace password');
        $msg = 'Password changed successfully. Use the new password the next time you sign in.';
    } catch (Throwable $error) {
        $msg = $error->getMessage();
        $msgType = 'error';
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-white tracking-tight">Change Password</h2>
        <p class="text-sm text-slate-500 mt-1">Update the password for your workspace employee ID.</p>
    </div>

    <?php if ($msg !== ''): ?>
    <div class="<?= $msgType === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?> border px-4 py-3 rounded-xl mb-6 text-sm">
        <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?> mr-2"></i><?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <section class="glass-card p-6 rounded-2xl">
        <div class="flex items-start gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-key text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Workspace Password</h3>
                <p class="text-sm text-slate-500 mt-1">Enter your current password, then choose a new password with at least 8 characters.</p>
            </div>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Current Password</label>
                <input type="password" name="current_password" autocomplete="current-password" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">New Password</label>
                    <input type="password" name="new_password" autocomplete="new-password" minlength="8" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" required>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Confirm New Password</label>
                    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" required>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="<?= htmlspecialchars(getDashboardUrl($_SESSION['role'])) ?>" class="btn-secondary px-4 py-2.5 rounded-xl text-sm text-slate-300">Cancel</a>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm text-white font-medium">
                    <i class="fa-solid fa-key mr-1.5"></i>Update Password
                </button>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
