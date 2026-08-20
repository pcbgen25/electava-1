<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = (int) $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'update_profile') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));

            if ($fullName === '') {
                throw new RuntimeException('Full name is required.');
            }

            $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$fullName, $phone !== '' ? $phone : null, $uid]);

            $_SESSION['full_name'] = $fullName;
            logAudit($pdo, 'update_employee_profile', 'employee', $uid, 'Employee updated their profile details');

            $msg = 'Profile details updated successfully.';
        } elseif (($_POST['action'] ?? '') === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            $userStmt = $pdo->prepare("SELECT password FROM employees WHERE id = ? LIMIT 1");
            $userStmt->execute([$uid]);
            $userRow = $userStmt->fetch();

            if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
                throw new RuntimeException('Current password is not correct.');
            }
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE employees SET password = ?, force_password_change = 0 WHERE id = ?");
            $stmt->execute([$passwordHash, $uid]);

            logAudit($pdo, 'change_employee_password', 'employee', $uid, 'Employee changed their own password');
            $msg = 'Password changed successfully.';
        }
    } catch (Throwable $error) {
        $msg = $error->getMessage();
        $msgType = 'error';
    }
}

$profileStmt = $pdo->prepare("
    SELECT e.*, d.name AS primary_domain_name
    FROM employees e
    LEFT JOIN domains d ON d.id = e.domain_id
    WHERE e.id = ?
    LIMIT 1
");
$profileStmt->execute([$uid]);
$employee = $profileStmt->fetch();

$allowedDomainIds = json_decode($employee['allowed_domains'] ?? '[]', true);
if (!is_array($allowedDomainIds)) {
    $allowedDomainIds = [];
}

$extraDomainNames = [];
if (!empty($allowedDomainIds)) {
    $placeholders = implode(',', array_fill(0, count($allowedDomainIds), '?'));
    $domainStmt = $pdo->prepare("SELECT name FROM domains WHERE id IN ($placeholders) ORDER BY name");
    $domainStmt->execute($allowedDomainIds);
    $extraDomainNames = array_map(static fn($row) => $row['name'], $domainStmt->fetchAll());
}

$loginCountStmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id = ? AND status = 'success'");
$loginCountStmt->execute([$uid]);
$totalLogins = (int) $loginCountStmt->fetchColumn();

$taskCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
$taskCountStmt->execute([$uid]);
$assignedTasks = (int) $taskCountStmt->fetchColumn();

$completedTasksStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('completed', 'approved')");
$completedTasksStmt->execute([$uid]);
$completedTasks = (int) $completedTasksStmt->fetchColumn();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">My Profile</h2>
    <p class="text-sm text-slate-500 mt-1">View your employee account details and update the personal fields allowed for self-service.</p>
</div>

<?php if ($msg !== ''): ?>
<div class="<?= $msgType === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?> border px-4 py-3 rounded-xl mb-6 text-sm">
    <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-check-circle' ?> mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="glass-card rounded-2xl p-4 mb-6 border border-amber-500/20 bg-amber-500/5">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-user-lock text-amber-300"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-white">Core Admin controlled fields</h3>
            <p class="text-sm text-slate-400 mt-1">Role, domain access, account status, username, and work assignment settings remain under Core Admin control. This page is mainly for your general profile view and personal updates.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Role</div>
        <div class="text-lg font-semibold text-white mt-2 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $employee['role'] ?? 'employee')) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Primary Domain</div>
        <div class="text-lg font-semibold text-white mt-2"><?= htmlspecialchars($employee['primary_domain_name'] ?? 'Not assigned') ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Successful Logins</div>
        <div class="text-2xl font-bold text-white mt-2"><?= number_format($totalLogins) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Completed Tasks</div>
        <div class="text-2xl font-bold text-white mt-2"><?= number_format($completedTasks) ?></div>
        <div class="text-xs text-slate-500 mt-1">from <?= number_format($assignedTasks) ?> assigned</div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr),380px] gap-6">
    <div class="space-y-6">
        <section class="glass-card p-5 rounded-2xl">
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-white">General Details</h3>
                <p class="text-sm text-slate-500 mt-1">Update the personal profile details you want shown in the workspace.</p>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Full Name</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($employee['full_name'] ?? '') ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="+91 00000 00000">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">
                        <i class="fa-solid fa-save mr-1.5"></i>Save Profile
                    </button>
                </div>
            </form>
        </section>

        <section class="glass-card p-5 rounded-2xl">
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-white">Change Password</h3>
                <p class="text-sm text-slate-500 mt-1">Keep your employee account secure by updating your password here.</p>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Current Password</label>
                        <input type="password" name="current_password" class="input-field w-full px-3 py-2 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">New Password</label>
                        <input type="password" name="new_password" class="input-field w-full px-3 py-2 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Confirm Password</label>
                        <input type="password" name="confirm_password" class="input-field w-full px-3 py-2 rounded-lg text-sm" required>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium">
                        <i class="fa-solid fa-key mr-1.5"></i>Update Password
                    </button>
                </div>
            </form>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="glass-card p-5 rounded-2xl">
            <h3 class="text-sm font-semibold text-white mb-4">Account View</h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Username</span>
                    <span class="text-white text-right"><?= htmlspecialchars($employee['username'] ?? '') ?></span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Email</span>
                    <span class="text-white text-right"><?= htmlspecialchars($employee['email'] ?? '') ?></span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Job Title</span>
                    <span class="text-white text-right"><?= htmlspecialchars($employee['job_title'] ?? 'Not set') ?></span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Status</span>
                    <span class="<?= !empty($employee['is_active']) ? 'text-emerald-400' : 'text-red-400' ?> text-right"><?= !empty($employee['is_active']) ? 'Active' : 'Inactive' ?></span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Joined</span>
                    <span class="text-white text-right"><?= !empty($employee['created_at']) ? date('d M Y', strtotime($employee['created_at'])) : '--' ?></span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Last Login</span>
                    <span class="text-white text-right"><?= !empty($employee['last_login_at']) ? date('d M Y h:i A', strtotime($employee['last_login_at'])) : 'Not recorded' ?></span>
                </div>
            </div>
        </section>

        <section class="glass-card p-5 rounded-2xl">
            <h3 class="text-sm font-semibold text-white mb-4">Access Details</h3>
            <div class="space-y-3 text-sm">
                <div>
                    <div class="text-slate-500 mb-1">Primary Domain</div>
                    <div class="text-white"><?= htmlspecialchars($employee['primary_domain_name'] ?? 'Not assigned') ?></div>
                </div>
                <div>
                    <div class="text-slate-500 mb-1">Additional Domains</div>
                    <div class="text-white">
                        <?= !empty($extraDomainNames) ? htmlspecialchars(implode(', ', $extraDomainNames)) : 'No extra domains assigned' ?>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
