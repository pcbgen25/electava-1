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
        header("Location: /core_admin/employee_profile.php?id=$empId&tab=details&mode=view&saved=1");
        exit;

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
$totalLogins = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=? AND status='success'");
$totalLogins->execute([$empId]); $totalLogins = $totalLogins->fetchColumn();

$monthLogins = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=? AND status='success' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$monthLogins->execute([$empId]); $monthLogins = $monthLogins->fetchColumn();

$totalWorkMins = $pdo->prepare("SELECT COALESCE(SUM(session_duration_mins),0) FROM login_logs WHERE user_id=? AND status='success' AND session_duration_mins IS NOT NULL");
$totalWorkMins->execute([$empId]); $totalWorkMins = (int)$totalWorkMins->fetchColumn();

$activeDays = $pdo->prepare("SELECT COUNT(DISTINCT DATE(created_at)) FROM login_logs WHERE user_id=? AND status='success'");
$activeDays->execute([$empId]); $activeDays = (int)$activeDays->fetchColumn();

// Day-wise aggregated view: one row per day
$loginLogs = $pdo->prepare("
    SELECT
        DATE(created_at)                                                         AS work_date,
        MIN(created_at)                                                          AS first_login,
        MAX(COALESCE(logout_at, created_at))                                     AS last_logout,
        COUNT(*)                                                                 AS sessions,
        GREATEST(COUNT(*) - 1, 0)                                               AS breaks_taken,
        COALESCE(SUM(session_duration_mins), 0)                                 AS work_mins,
        GREATEST(
            TIMESTAMPDIFF(MINUTE, MIN(created_at),
                          MAX(COALESCE(logout_at, created_at)))
            - COALESCE(SUM(session_duration_mins), 0),
            0
        )                                                                        AS break_mins,
        TIMESTAMPDIFF(MINUTE, MIN(created_at),
                      MAX(COALESCE(logout_at, created_at)))                      AS span_mins,
        GROUP_CONCAT(DISTINCT COALESCE(ip_address,'?') ORDER BY created_at SEPARATOR ', ') AS ips,
        MIN(status)                                                              AS day_status
    FROM login_logs
    WHERE user_id = ? AND status = 'success'
    GROUP BY DATE(created_at)
    ORDER BY work_date DESC
    LIMIT 60
");
$loginLogs->execute([$empId]); $loginLogs = $loginLogs->fetchAll();

// Helper: format minutes → "Xh Ym"
function fmtMins(int $mins): string {
    if ($mins <= 0) return '—';
    $h = floor($mins / 60); $m = $mins % 60;
    return ($h > 0 ? "{$h}h " : '') . "{$m}m";
}

// ─── TASK & PROJECT STATS ──────────────────────────────────────────────────────
$myTasks = $pdo->prepare("SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to=? ORDER BY t.created_at DESC");
$myTasks->execute([$empId]); $myTasks = $myTasks->fetchAll();

$myProjects = $pdo->prepare("SELECT * FROM projects WHERE assigned_to=? ORDER BY created_at DESC");
$myProjects->execute([$empId]); $myProjects = $myProjects->fetchAll();

$allProjects = $pdo->query("SELECT * FROM projects WHERE status='active' ORDER BY name")->fetchAll();

$completedTasks = count(array_filter($myTasks, fn($t) => in_array($t['status'], ['completed','approved'])));

$activeTab  = $_GET['tab']  ?? 'details';
$activeMode = $_GET['mode'] ?? 'view'; // 'view' or 'edit'
$savedOk    = isset($_GET['saved']);

// ─── EXCEL EXPORT ─────────────────────────────────────────────────────────────
if ($activeTab === 'logins' && isset($_GET['export']) && $_GET['export'] === 'attendance') {
    $filename = 'attendance_' . preg_replace('/[^a-z0-9]/i', '_', $emp['username']) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fputs($out, "\xEF\xBB\xBF");

    // Employee summary header
    fputcsv($out, ['Attendance Report']);
    fputcsv($out, ['Employee', $emp['full_name']]);
    fputcsv($out, ['ID', '#' . $emp['id']]);
    fputcsv($out, ['Email', $emp['email']]);
    fputcsv($out, ['Job Title', $emp['job_title'] ?: 'N/A']);
    fputcsv($out, ['Role', ucwords(str_replace('_', ' ', $emp['role']))]);
    fputcsv($out, ['Domain', $emp['domain_name'] ?: 'N/A']);
    fputcsv($out, ['Export Date', date('d M Y, H:i')]);
    fputcsv($out, []);

    // Column headers
    fputcsv($out, ['Date', 'Day', 'First Login', 'Last Logout', 'Work Time (hrs)', 'Break Time (hrs)', 'Sessions', 'Breaks Taken', 'IP Address(es)', 'Day Type']);

    foreach ($loginLogs as $day) {
        $wdate   = new DateTime($day['work_date']);
        $firstIn = (new DateTime($day['first_login']))->format('H:i');
        $lastOut = $day['last_logout'] ? (new DateTime($day['last_logout']))->format('H:i') : '';
        $workHrs = $day['work_mins'] > 0 ? round($day['work_mins'] / 60, 2) : 0;
        $brkHrs  = $day['break_mins'] > 0 ? round($day['break_mins'] / 60, 2) : 0;
        $dayType = in_array($wdate->format('N'), [6,7]) ? 'Weekend' : 'Weekday';
        fputcsv($out, [
            $wdate->format('d/m/Y'),
            $wdate->format('l'),
            $firstIn,
            $lastOut,
            $workHrs,
            $brkHrs,
            (int)$day['sessions'],
            (int)$day['breaks_taken'],
            $day['ips'] ?? '',
            $dayType,
        ]);
    }

    // Summary row
    fputcsv($out, []);
    fputcsv($out, ['SUMMARY']);
    fputcsv($out, ['Total Sessions', $totalLogins]);
    fputcsv($out, ['Active Days', $activeDays]);
    fputcsv($out, ['Total Work Time (hrs)', round($totalWorkMins / 60, 2)]);
    fputcsv($out, ['This Month Sessions', $monthLogins]);

    fclose($out);
    exit;
}
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

<?php if ($savedOk): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i> Employee details saved successfully.
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left: Details Card -->
    <div class="lg:col-span-2 glass-card rounded-2xl p-6 border border-slate-700/50">

        <?php if ($activeMode === 'edit'): ?>
        <!-- ── EDIT MODE ── -->
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-emerald-400"></i>Edit Details
            </h3>
            <a href="?id=<?= $empId ?>&tab=details&mode=view" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white bg-slate-800/60 px-3 py-1.5 rounded-lg border border-slate-700/40 transition">
                <i class="fa-solid fa-xmark text-[10px]"></i>Cancel
            </a>
        </div>
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

            <!-- Action buttons row -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-800/50">
                <div class="flex items-center gap-2">
                    <!-- Delete (small) -->
                    <?php if ($emp['id'] !== $_SESSION['user_id']): ?>
                    <button type="button"
                        onclick="if(confirm('Delete this employee permanently?')) { document.getElementById('deleteForm').submit(); }"
                        class="inline-flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 bg-red-500/10 px-3 py-1.5 rounded-lg border border-red-500/20 hover:border-red-500/40 transition">
                        <i class="fa-solid fa-trash text-[10px]"></i>Delete
                    </button>
                    <?php endif; ?>
                    <!-- Reset Password (small) -->
                    <button type="button"
                        onclick="if(confirm('Reset password for this employee?')) { document.getElementById('resetForm').submit(); }"
                        class="inline-flex items-center gap-1.5 text-xs text-amber-400 hover:text-amber-300 bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-500/20 hover:border-amber-500/40 transition">
                        <i class="fa-solid fa-key text-[10px]"></i>Reset Password
                    </button>
                </div>
                <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm text-white font-medium shadow-lg shadow-emerald-600/20">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Save Changes
                </button>
            </div>
        </form>

        <!-- Hidden forms for delete/reset -->
        <form id="deleteForm" method="POST" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="delete">
        </form>
        <form id="resetForm" method="POST" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="reset_password">
        </form>

        <?php else: ?>
        <!-- ── VIEW MODE ── -->
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fa-solid fa-user text-emerald-400"></i>Employee Details
            </h3>
            <a href="?id=<?= $empId ?>&tab=details&mode=edit"
               class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 hover:border-emerald-500/40 transition">
                <i class="fa-solid fa-pen text-[10px]"></i>Edit
            </a>
        </div>
        <div class="grid grid-cols-2 gap-x-8 gap-y-4">
            <?php
            $fields = [
                'Full Name'    => $emp['full_name'],
                'Username'     => $emp['username'],
                'Email'        => $emp['email'],
                'Phone'        => $emp['phone'] ?: '—',
                'Job Title'    => $emp['job_title'] ?: '—',
                'Role'         => ucwords(str_replace('_',' ',$emp['role'])),
                'Primary Domain' => $emp['domain_name'] ?: '—',
                'Status'       => ucfirst($emp['status']),
            ];
            foreach ($fields as $label => $val):
            ?>
            <div class="border-b border-slate-800/40 pb-3">
                <div class="text-[10px] uppercase tracking-widest text-slate-500 mb-1"><?= $label ?></div>
                <div class="text-sm text-white font-medium"><?= htmlspecialchars((string)$val) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($emp['notes']): ?>
            <div class="col-span-2 border-b border-slate-800/40 pb-3">
                <div class="text-[10px] uppercase tracking-widest text-slate-500 mb-1">Notes</div>
                <div class="text-sm text-slate-300"><?= nl2br(htmlspecialchars($emp['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Account Info -->
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50 h-fit">
        <h4 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">Account Info</h4>
        <div class="space-y-3 text-xs">
            <div class="flex justify-between items-center py-1.5 border-b border-slate-800/40">
                <span class="text-slate-500">Employee ID</span>
                <span class="text-white font-mono">#<?= $emp['id'] ?></span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-800/40">
                <span class="text-slate-500">Last Login</span>
                <span class="text-slate-300"><?= $emp['last_login_at'] ? date('M j, Y H:i', strtotime($emp['last_login_at'])) : 'Never' ?></span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-800/40">
                <span class="text-slate-500">Force PWD Change</span>
                <span class="<?= $emp['force_password_change'] ? 'text-amber-400' : 'text-slate-400' ?>"><?= $emp['force_password_change'] ? 'Yes' : 'No' ?></span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-800/40">
                <span class="text-slate-500">Total Logins</span>
                <span class="text-white"><?= $totalLogins ?></span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-800/40">
                <span class="text-slate-500">Total Work</span>
                <span class="text-emerald-400 font-medium"><?= fmtMins($totalWorkMins) ?></span>
            </div>
            <div class="flex justify-between items-center py-1.5">
                <span class="text-slate-500">Joined</span>
                <span class="text-slate-300"><?= date('M j, Y', strtotime($emp['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════════════ -->
<!-- TAB 2 — LOGIN TRACKING -->
<!-- ════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'logins'): ?>

<!-- Stats Row — 5 cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-2xl font-bold text-white"><?= $totalLogins ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Total Sessions</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-2xl font-bold text-emerald-400"><?= $monthLogins ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">This Month</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-2xl font-bold text-blue-400"><?= $activeDays ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Active Days</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-lg font-bold text-purple-300"><?= fmtMins($totalWorkMins) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Total Work Time</div>
    </div>
    <div class="glass-card p-4 rounded-2xl text-center border border-slate-700/50">
        <div class="text-lg font-bold text-amber-300"><?= $emp['last_login_at'] ? date('M j, H:i', strtotime($emp['last_login_at'])) : '—' ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Last Login</div>
    </div>
</div>

<!-- Day-wise Login Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50">
    <div class="px-5 py-4 border-b border-slate-800/50 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
            <i class="fa-solid fa-calendar-days text-emerald-400"></i>Day-wise Attendance
            <span class="text-slate-500 font-normal text-xs">(Last 60 days · one row per day)</span>
        </h3>
        <a href="?id=<?= $empId ?>&tab=logins&export=attendance"
           class="inline-flex items-center gap-2 text-xs text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 hover:border-emerald-500/40 transition font-medium"
           title="Download attendance as Excel-compatible CSV">
            <i class="fa-solid fa-file-excel text-sm"></i>Export to Excel
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-slate-500 uppercase bg-slate-900/60 border-b border-slate-800/50">
                <tr>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">First Login</th>
                    <th class="px-5 py-3">Last Logout</th>
                    <th class="px-5 py-3 text-emerald-400">Work Time</th>
                    <th class="px-5 py-3 text-amber-400">Break Time</th>
                    <th class="px-5 py-3">Sessions</th>
                    <th class="px-5 py-3">Breaks Taken</th>
                    <th class="px-5 py-3">IP(s)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                <?php if (empty($loginLogs)): ?>
                <tr><td colspan="8" class="px-5 py-12 text-center text-slate-500 text-sm">No attendance records found yet.</td></tr>
                <?php else: ?>
                <?php foreach ($loginLogs as $day):
                    $wdate    = new DateTime($day['work_date']);
                    $firstIn  = (new DateTime($day['first_login']))->format('H:i');
                    $lastOut  = $day['last_logout'] ? (new DateTime($day['last_logout']))->format('H:i') : '—';
                    $workMins = (int)$day['work_mins'];
                    $brkMins  = (int)$day['break_mins'];
                    $sessions = (int)$day['sessions'];
                    $breaks   = (int)$day['breaks_taken'];
                    // Colour-code the work time bar
                    $workPct  = $day['span_mins'] > 0 ? min(100, round(($workMins / max($day['span_mins'], 1)) * 100)) : 0;
                    $isToday  = ($wdate->format('Y-m-d') === date('Y-m-d'));
                    $isWeekend= in_array($wdate->format('N'), [6,7]);
                ?>
                <tr class="hover:bg-slate-800/20 transition <?= $isToday ? 'bg-emerald-500/5 border-l-2 border-emerald-500/40' : '' ?>">
                    <td class="px-5 py-3.5">
                        <div class="font-semibold text-white text-xs"><?= $wdate->format('D, M j') ?></div>
                        <div class="text-[10px] text-slate-500 mt-0.5"><?= $wdate->format('Y') ?><?= $isToday ? ' · <span class="text-emerald-400">Today</span>' : '' ?><?= $isWeekend ? ' · <span class="text-amber-500">Weekend</span>' : '' ?></div>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-white"><?= $firstIn ?></td>
                    <td class="px-5 py-3.5 font-mono text-xs <?= $day['last_logout'] ? 'text-slate-300' : 'text-slate-600' ?>"><?= $lastOut ?></td>
                    <td class="px-5 py-3.5">
                        <div class="font-bold text-xs text-emerald-400"><?= fmtMins($workMins) ?></div>
                        <?php if ($day['span_mins'] > 0): ?>
                        <div class="w-16 h-1 bg-slate-700 rounded-full mt-1.5 overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" style="width:<?= $workPct ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-xs <?= $brkMins > 0 ? 'text-amber-400 font-medium' : 'text-slate-600' ?>"><?= fmtMins($brkMins) ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="w-6 h-6 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold inline-flex items-center justify-center"><?= $sessions ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <?php if ($breaks > 0): ?>
                        <span class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-bold inline-flex items-center justify-center"><?= $breaks ?></span>
                        <?php else: ?>
                        <span class="text-slate-600 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-[10px] text-slate-600 font-mono max-w-[120px] truncate" title="<?= htmlspecialchars($day['ips'] ?? '') ?>">
                        <?= htmlspecialchars($day['ips'] ?? '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Legend -->
<div class="mt-3 flex items-center gap-5 text-[10px] text-slate-600 px-1">
    <span><span class="text-emerald-400 font-bold">Work Time</span> = Sum of all login→logout durations in the day</span>
    <span><span class="text-amber-400 font-bold">Break Time</span> = Total span minus work time (gaps between sessions)</span>
    <span><span class="text-blue-400 font-bold">Sessions</span> = Number of login events in the day</span>
    <span><span class="text-amber-400 font-bold">Breaks</span> = Sessions − 1</span>
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
