<?php
$pageTitle = 'Employee Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$empId = (int)($_GET['id'] ?? 0);
if (!$empId) { header('Location: /core_admin/employees.php'); exit; }

$msg = ''; $msgType = 'success';

// ─── POST HANDLERS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();

    if ($_POST['action'] === 'edit') {
        $pdo->prepare("UPDATE users SET full_name=?, email=?, username=?, phone=?, job_title=?, notes=?, role=?, domain_id=?, status=?, allowed_domains=? WHERE id=?")
            ->execute([
                trim($_POST['full_name']),
                trim($_POST['email']),
                trim($_POST['username']),
                trim($_POST['phone'] ?? ''),
                trim($_POST['job_title'] ?? ''),
                trim($_POST['notes'] ?? ''),
                $_POST['role'],
                $_POST['domain_id'] ?: null,
                $_POST['status'],
                isset($_POST['allowed_domains']) ? json_encode($_POST['allowed_domains']) : null,
                $empId
            ]);
        logAudit($pdo, 'edit_employee', 'employee', $empId, 'Updated employee details');
        $msg = 'Employee details updated successfully.';

    } elseif ($_POST['action'] === 'reset_password') {
        $tempPass = bin2hex(random_bytes(10));
        $hash = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash=?, force_password_change=1 WHERE id=?")->execute([$hash, $empId]);
        logAudit($pdo, 'reset_password', 'employee', $empId, 'Password reset');
        $msg = 'Password reset. Temporary password: <strong class="font-mono text-emerald-300">' . htmlspecialchars($tempPass) . '</strong> — Employee must change on next login.';

    } elseif ($_POST['action'] === 'save_permissions') {
        $allowedDomains = isset($_POST['allowed_domains']) ? json_encode(array_map('intval', $_POST['allowed_domains'])) : null;
        $pdo->prepare("UPDATE users SET allowed_domains=? WHERE id=?")->execute([$allowedDomains, $empId]);
        logAudit($pdo, 'update_permissions', 'employee', $empId, 'Updated domain permissions');
        $msg = 'Permissions updated.';

    } elseif ($_POST['action'] === 'assign_task') {
        $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, due_date, priority) VALUES (?,?,?,?,?,?)")
            ->execute([
                trim($_POST['title']),
                trim($_POST['description'] ?? ''),
                $empId,
                $_SESSION['user_id'],
                $_POST['due_date'] ?: null,
                $_POST['priority'] ?? 'medium'
            ]);
        $taskId = $pdo->lastInsertId();
        logAudit($pdo, 'assign_task', 'task', $taskId, "Assigned task to employee #$empId");
        notify($pdo, $empId, 'New Task Assigned', trim($_POST['title']), 'task');
        $msg = 'Task assigned successfully.';

    } elseif ($_POST['action'] === 'assign_project') {
        $projId = (int)$_POST['project_id'];
        $pdo->prepare("UPDATE projects SET assigned_to=? WHERE id=?")->execute([$empId, $projId]);
        logAudit($pdo, 'assign_project', 'project', $projId, "Assigned project to employee #$empId");
        $msg = 'Project assigned successfully.';

    } elseif ($_POST['action'] === 'delete') {
        if ($empId !== $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$empId]);
            logAudit($pdo, 'delete_employee', 'employee', $empId, 'Employee deleted');
            header('Location: /core_admin/employees.php?msg=deleted');
            exit;
        }
        $msg = 'You cannot delete yourself.'; $msgType = 'error';
    }
}

// ─── LOAD EMPLOYEE ─────────────────────────────────────────────────────────────
$emp = $pdo->prepare("SELECT u.*, d.name as domain_name, c.full_name as created_by_name FROM users u LEFT JOIN domains d ON u.domain_id = d.id LEFT JOIN users c ON u.created_by = c.id WHERE u.id = ?");
$emp->execute([$empId]);
$emp = $emp->fetch();
if (!$emp) { header('Location: /core_admin/employees.php'); exit; }

$domains  = $pdo->query("SELECT * FROM domains ORDER BY name")->fetchAll();
$allowedDomainsArr = json_decode($emp['allowed_domains'] ?? '[]', true) ?? [];

// ─── LOGIN STATS ───────────────────────────────────────────────────────────────
$totalLogins   = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=? AND status='success'");
$totalLogins->execute([$empId]); $totalLogins = $totalLogins->fetchColumn();

$monthLogins   = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=? AND status='success' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$monthLogins->execute([$empId]); $monthLogins = $monthLogins->fetchColumn();

$loginLogs = $pdo->prepare("SELECT * FROM login_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$loginLogs->execute([$empId]); $loginLogs = $loginLogs->fetchAll();

// ─── TASK & PROJECT STATS ──────────────────────────────────────────────────────
$myTasks = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to=? ORDER BY t.created_at DESC");
$myTasks->execute([$empId]); $myTasks = $myTasks->fetchAll();

$myProjects = $pdo->prepare("SELECT * FROM projects WHERE assigned_to=? ORDER BY created_at DESC");
$myProjects->execute([$empId]); $myProjects = $myProjects->fetchAll();

$allProjects = $pdo->query("SELECT * FROM projects WHERE status='active' ORDER BY name")->fetchAll();

$completedTasks = count(array_filter($myTasks, fn($t) => in_array($t['status'], ['completed','approved'])));

$activeTab = $_GET['tab'] ?? 'details';
?>

<?php if ($msg): ?>
<div class="bg-<?= $msgType === 'error' ? 'red' : 'emerald' ?>-500/10 border border-<?= $msgType === 'error' ? 'red' : 'emerald' ?>-500/20 text-<?= $msgType === 'error' ? 'red' : 'emerald' ?>-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-<?= $msgType === 'error' ? 'triangle-exclamation' : 'check-circle' ?>"></i><?= $msg ?>
</div>
<?php endif; ?>

<!-- Back Link -->
<a href="/core_admin/employees.php" class="inline-flex items-center gap-2 text-slate-400 hover:text-white text-sm mb-5 transition">
    <i class="fa-solid fa-arrow-left text-xs"></i>Back to Employees
</a>

<!-- ═══ PROFILE HEADER ═══════════════════════════════════════════════════════════ -->
<div class="glass-card rounded-2xl p-6 mb-6 border border-slate-700/50">
    <div class="flex flex-wrap items-start gap-5">
        <!-- Avatar -->
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center text-2xl font-bold text-white shadow-xl shadow-emerald-500/20 flex-shrink-0">
            <?= strtoupper(substr($emp['full_name'] ?: $emp['username'], 0, 1)) ?>
        </div>
        <!-- Identity -->
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-3 mb-1">
                <h1 class="text-2xl font-bold text-white tracking-tight"><?= htmlspecialchars($emp['full_name'] ?: $emp['username']) ?></h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $emp['status']==='active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' ?>">
                    <i class="fa-solid fa-circle text-[6px] mr-1"></i><?= ucfirst($emp['status']) ?>
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-300 border border-blue-500/20 capitalize">
                    <?= str_replace('_',' ', $emp['role']) ?>
                </span>
            </div>
            <div class="text-sm text-slate-400 mb-2"><?= htmlspecialchars($emp['job_title'] ?: 'No job title set') ?></div>
            <div class="flex flex-wrap gap-4 text-xs text-slate-500">
                <span><i class="fa-solid fa-envelope mr-1"></i><?= htmlspecialchars($emp['email']) ?></span>
                <?php if ($emp['phone']): ?><span><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($emp['phone']) ?></span><?php endif; ?>
                <?php if ($emp['domain_name']): ?><span><i class="fa-solid fa-network-wired mr-1"></i><?= htmlspecialchars($emp['domain_name']) ?></span><?php endif; ?>
                <span><i class="fa-solid fa-calendar mr-1"></i>Joined <?= date('M j, Y', strtotime($emp['created_at'])) ?></span>
                <?php if ($emp['created_by_name']): ?><span><i class="fa-solid fa-user-plus mr-1"></i>Created by <?= htmlspecialchars($emp['created_by_name']) ?></span><?php endif; ?>
            </div>
        </div>
        <!-- Quick Stats -->
        <div class="flex gap-4 text-center">
            <div class="bg-slate-800/60 px-4 py-2.5 rounded-xl">
                <div class="text-lg font-bold text-white"><?= $totalLogins ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Logins</div>
            </div>
            <div class="bg-slate-800/60 px-4 py-2.5 rounded-xl">
                <div class="text-lg font-bold text-emerald-400"><?= $completedTasks ?>/<?= count($myTasks) ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Tasks Done</div>
            </div>
            <div class="bg-slate-800/60 px-4 py-2.5 rounded-xl">
                <div class="text-lg font-bold text-blue-400"><?= count($myProjects) ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Projects</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TABS ═══════════════════════════════════════════════════════════════════ -->
<div class="flex gap-1 mb-6 bg-slate-900/50 rounded-xl p-1 border border-slate-800/50 w-fit">
    <?php foreach (['details'=>'Details','logins'=>'Login Tracking','permissions'=>'Permissions','tasks'=>'Assign Task / Project'] as $t => $label): ?>
    <a href="?id=<?= $empId ?>&tab=<?= $t ?>"
       class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $activeTab===$t ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/20' : 'text-slate-400 hover:text-white' ?>">
        <i class="fa-solid fa-<?= ['details'=>'user-pen','logins'=>'clock-rotate-left','permissions'=>'key','tasks'=>'list-check'][$t] ?> mr-1.5 text-xs"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 1 — DETAILS -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'details'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Edit Form -->
    <div class="lg:col-span-2 glass-card rounded-2xl p-6 border border-slate-700/50">
        <h3 class="text-sm font-semibold text-white mb-5 flex items-center gap-2">
            <i class="fa-solid fa-user-pen text-emerald-400"></i>Edit Employee Details
        </h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="edit">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($emp['full_name']) ?>" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($emp['username']) ?>" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($emp['email']) ?>" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Job Title</label>
                    <input type="text" name="job_title" value="<?= htmlspecialchars($emp['job_title'] ?? '') ?>" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Status</label>
                    <select name="status" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                        <option value="active" <?= $emp['status']==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $emp['status']==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Role</label>
                    <select name="role" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                        <option value="core_admin" <?= $emp['role']==='core_admin'?'selected':'' ?>>Core Admin</option>
                        <option value="admin" <?= $emp['role']==='admin'?'selected':'' ?>>Admin</option>
                        <option value="employee" <?= $emp['role']==='employee'?'selected':'' ?>>Employee</option>
                        <option value="vendor" <?= $emp['role']==='vendor'?'selected':'' ?>>Vendor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Primary Domain</label>
                    <select name="domain_id" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                        <option value="">— None —</option>
                        <?php foreach ($domains as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $emp['domain_id']==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Notes</label>
                <textarea name="notes" rows="3" class="input-field w-full px-3 py-2.5 rounded-xl text-sm"><?= htmlspecialchars($emp['notes'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm text-white font-medium shadow-lg shadow-emerald-600/20">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Danger Zone -->
    <div class="space-y-4">
        <!-- Reset Password -->
        <div class="glass-card rounded-2xl p-5 border border-amber-500/20">
            <h4 class="text-sm font-semibold text-amber-400 mb-2 flex items-center gap-2"><i class="fa-solid fa-key"></i>Reset Password</h4>
            <p class="text-xs text-slate-500 mb-4">Generate a random temporary password. Employee must change it on next login.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="reset_password">
                <button class="btn-secondary w-full py-2 rounded-lg text-xs text-amber-300 border border-amber-500/20 hover:border-amber-500/40 transition">
                    <i class="fa-solid fa-rotate mr-1.5"></i>Generate Temp Password
                </button>
            </form>
        </div>

        <!-- Employee Info -->
        <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
            <h4 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-3">Account Info</h4>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between"><span class="text-slate-500">Employee ID</span><span class="text-white font-mono">#<?= $emp['id'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Username</span><span class="text-white font-mono"><?= htmlspecialchars($emp['username']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Last Login</span><span class="text-slate-300"><?= $emp['last_login_at'] ? date('M j, Y H:i', strtotime($emp['last_login_at'])) : 'Never' ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Force PWD Change</span><span class="<?= $emp['force_password_change'] ? 'text-amber-400' : 'text-slate-400' ?>"><?= $emp['force_password_change'] ? 'Yes' : 'No' ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Total Logins</span><span class="text-white"><?= $totalLogins ?></span></div>
            </div>
        </div>

        <!-- Delete -->
        <?php if ($emp['id'] !== $_SESSION['user_id']): ?>
        <div class="glass-card rounded-2xl p-5 border border-red-500/20">
            <h4 class="text-sm font-semibold text-red-400 mb-2 flex items-center gap-2"><i class="fa-solid fa-trash"></i>Delete Employee</h4>
            <p class="text-xs text-slate-500 mb-4">This is permanent and cannot be undone. All their data will be removed.</p>
            <form method="POST" onsubmit="return confirm('Are you absolutely sure? This deletes the employee and all their data permanently.')">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <button class="w-full py-2 rounded-lg text-xs text-red-300 border border-red-500/20 hover:bg-red-500/10 transition">
                    <i class="fa-solid fa-trash mr-1.5"></i>Delete Employee
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 2 — LOGIN TRACKING -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'logins'): ?>
<!-- Stats Row -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-2xl font-bold text-white"><?= $totalLogins ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Total Logins</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-2xl font-bold text-emerald-400"><?= $monthLogins ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">This Month</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-lg font-bold text-blue-300"><?= $emp['last_login_at'] ? date('M j, Y H:i', strtotime($emp['last_login_at'])) : 'Never' ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Last Login</div>
    </div>
</div>

<!-- Login Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50">
    <div class="px-5 py-4 border-b border-slate-800/50 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white flex items-center gap-2"><i class="fa-solid fa-clock-rotate-left text-emerald-400"></i>Login History <span class="text-slate-500 font-normal text-xs">(Last 100 sessions)</span></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Login Time</th>
                    <th class="px-5 py-3">Logout Time</th>
                    <th class="px-5 py-3">Duration</th>
                    <th class="px-5 py-3">IP Address</th>
                    <th class="px-5 py-3">Device</th>
                    <th class="px-5 py-3">Browser</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                <?php if (empty($loginLogs)): ?>
                <tr><td colspan="8" class="px-5 py-10 text-center text-slate-500 text-sm">No login records found.</td></tr>
                <?php else: ?>
                <?php foreach ($loginLogs as $log): ?>
                <?php
                    $loginDt  = new DateTime($log['created_at']);
                    $logoutDt = $log['logout_at'] ? new DateTime($log['logout_at']) : null;
                    $durMins  = $log['session_duration_mins'];
                    $durText  = '—';
                    if ($durMins !== null) {
                        $h = floor($durMins/60); $m = $durMins%60;
                        $durText = ($h > 0 ? "{$h}h " : '') . "{$m}m";
                    }
                ?>
                <tr class="table-row hover:bg-slate-800/20 transition">
                    <td class="px-5 py-3 text-xs text-slate-400 font-medium"><?= $loginDt->format('M j, Y') ?></td>
                    <td class="px-5 py-3 text-xs text-white font-mono"><?= $loginDt->format('H:i:s') ?></td>
                    <td class="px-5 py-3 text-xs <?= $logoutDt ? 'text-slate-300 font-mono' : 'text-slate-600' ?>"><?= $logoutDt ? $logoutDt->format('H:i:s') : '—' ?></td>
                    <td class="px-5 py-3 text-xs <?= $durMins !== null ? 'text-emerald-400 font-medium' : 'text-slate-600' ?>"><?= $durText ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars(ucfirst($log['device_type'] ?? '—')) ?></td>
                    <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars($log['browser'] ?? '—') ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $log['status']==='success' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20' ?>">
                            <?= strtoupper($log['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 3 — PERMISSIONS -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'permissions'): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Role-based Access Matrix -->
    <div class="glass-card rounded-2xl p-6 border border-slate-700/50">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-400"></i>Role Access — <span class="text-emerald-400 capitalize"><?= str_replace('_',' ', $emp['role']) ?></span></h3>
        <?php
        $roleAccess = [
            'core_admin' => ['Dashboard','Employees','Projects','Domains','Task Templates','Audit Logs','Employee Logins','Marketplace Tracking','Users','Careers','Reports','Permissions','Settings'],
            'admin'      => ['Dashboard','Tasks','Approvals','Team','Vendors','Reports'],
            'employee'   => ['Dashboard','My Tasks','Submissions','Components (if domain)','My Requests (if domain)','Service Queue (if domain)','Service Tokens (if domain)'],
            'vendor'     => ['Dashboard','Products','Purchase Orders','Inventory','Company Profile'],
        ];
        $pages = $roleAccess[$emp['role']] ?? [];
        ?>
        <div class="space-y-2">
            <?php foreach ($pages as $page): ?>
            <div class="flex items-center gap-3 p-2.5 rounded-lg bg-slate-800/30">
                <i class="fa-solid fa-check-circle text-emerald-400 text-xs w-4"></i>
                <span class="text-xs text-slate-300"><?= htmlspecialchars($page) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-600 mt-4">Role permissions are controlled by the <strong>role</strong> field. Change the role in the Details tab to modify core access.</p>
    </div>

    <!-- Domain Permissions -->
    <div class="glass-card rounded-2xl p-6 border border-slate-700/50">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-network-wired text-blue-400"></i>Domain Access</h3>
        <p class="text-xs text-slate-500 mb-4">Primary domain is set in Details. Grant additional domain access below.</p>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="save_permissions">
            <?php foreach ($domains as $d): ?>
            <?php $isPrimary = ((int)$emp['domain_id'] === (int)$d['id']); ?>
            <label class="flex items-center gap-3 p-3 rounded-xl border <?= $isPrimary ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-slate-700/40 bg-slate-800/20 hover:bg-slate-800/40' ?> cursor-pointer transition">
                <input type="checkbox" name="allowed_domains[]" value="<?= $d['id'] ?>"
                    <?= $isPrimary ? 'checked disabled' : '' ?>
                    <?= (!$isPrimary && in_array((int)$d['id'], $allowedDomainsArr)) ? 'checked' : '' ?>
                    class="w-4 h-4 accent-emerald-500">
                <div>
                    <div class="text-xs font-medium text-white"><?= htmlspecialchars($d['name']) ?></div>
                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($d['description'] ?? '') ?></div>
                </div>
                <?php if ($isPrimary): ?>
                <span class="ml-auto text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">Primary</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm text-white font-medium">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Permissions
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 4 — ASSIGN TASK / PROJECT -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'tasks'): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- LEFT — TASKS -->
    <div class="space-y-5">
        <!-- Assign New Task Form -->
        <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-plus-circle text-emerald-400"></i>Assign New Task</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="assign_task">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Task Title <span class="text-red-400">*</span></label>
                    <input type="text" name="title" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="e.g. Review PCB schematic">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Description</label>
                    <textarea name="description" rows="3" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Task details and instructions..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Due Date</label>
                        <input type="date" name="due_date" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Priority</label>
                        <select name="priority" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm text-white font-medium">
                    <i class="fa-solid fa-plus mr-1.5"></i>Create & Assign Task
                </button>
            </form>
        </div>

        <!-- Current Tasks List -->
        <div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50">
            <div class="px-5 py-3.5 border-b border-slate-800/50">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-blue-400"></i>Assigned Tasks
                    <span class="text-xs text-slate-500">(<?= count($myTasks) ?> total, <?= $completedTasks ?> done)</span>
                </h3>
            </div>
            <div class="divide-y divide-slate-800/40 max-h-80 overflow-y-auto">
                <?php if (empty($myTasks)): ?>
                <div class="px-5 py-8 text-center text-slate-500 text-sm">No tasks assigned yet.</div>
                <?php else: ?>
                <?php foreach ($myTasks as $task):
                    $sc = ['pending'=>'amber','in_progress'=>'blue','submitted'=>'cyan','approved'=>'emerald','completed'=>'emerald','rejected'=>'red'][$task['status']] ?? 'slate';
                ?>
                <div class="px-5 py-3 hover:bg-slate-800/20 transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-white font-medium truncate"><?= htmlspecialchars($task['title']) ?></div>
                            <?php if ($task['project_name']): ?><div class="text-[10px] text-slate-500 mt-0.5"><i class="fa-solid fa-diagram-project mr-1"></i><?= htmlspecialchars($task['project_name']) ?></div><?php endif; ?>
                            <?php if ($task['due_date']): ?><div class="text-[10px] text-slate-600 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i>Due <?= date('M j, Y', strtotime($task['due_date'])) ?></div><?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <?= priorityBadge($task['priority']) ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-<?= $sc ?>-500/10 text-<?= $sc ?>-400 border-<?= $sc ?>-500/20">
                                <?= str_replace('_',' ', strtoupper($task['status'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT — PROJECTS -->
    <div class="space-y-5">
        <!-- Assign to Project Form -->
        <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-diagram-project text-purple-400"></i>Assign to Project</h3>
            <?php if (empty($allProjects)): ?>
            <p class="text-xs text-slate-500 py-4 text-center">No active projects available. <a href="/core_admin/projects.php" class="text-emerald-400 hover:underline">Create a project first.</a></p>
            <?php else: ?>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="assign_project">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Select Project <span class="text-red-400">*</span></label>
                    <select name="project_id" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                        <option value="">— Choose a project —</option>
                        <?php foreach ($allProjects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= in_array($p['id'], array_column($myProjects,'id'))?'selected':'' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= ucfirst($p['status']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm text-white font-medium">
                    <i class="fa-solid fa-link mr-1.5"></i>Assign to Project
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Current Projects List -->
        <div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50">
            <div class="px-5 py-3.5 border-b border-slate-800/50">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fa-solid fa-folder-open text-purple-400"></i>Assigned Projects
                    <span class="text-xs text-slate-500">(<?= count($myProjects) ?> total)</span>
                </h3>
            </div>
            <div class="divide-y divide-slate-800/40 max-h-80 overflow-y-auto">
                <?php if (empty($myProjects)): ?>
                <div class="px-5 py-8 text-center text-slate-500 text-sm">No projects assigned yet.</div>
                <?php else: ?>
                <?php foreach ($myProjects as $proj):
                    $psc = ['active'=>'emerald','on_hold'=>'amber','completed'=>'blue','archived'=>'slate'][$proj['status']] ?? 'slate';
                ?>
                <div class="px-5 py-3.5 hover:bg-slate-800/20 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-white font-medium truncate"><?= htmlspecialchars($proj['name']) ?></div>
                            <?php if ($proj['end_date']): ?><div class="text-[10px] text-slate-500 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i>Due <?= date('M j, Y', strtotime($proj['end_date'])) ?></div><?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <?= priorityBadge($proj['priority']) ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-<?= $psc ?>-500/10 text-<?= $psc ?>-400 border-<?= $psc ?>-500/20">
                                <?= strtoupper($proj['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
