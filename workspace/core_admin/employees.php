<?php
$pageTitle = 'Employee Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('core_admin');

$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $fullName = trim($_POST['full_name']);
        $role = $_POST['role'];
        $domainId = $_POST['domain_id'] ?: null;
        $allowedDomains = isset($_POST['allowed_domains']) ? json_encode($_POST['allowed_domains']) : null;
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = trim($_POST['phone'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, full_name, phone, job_title, notes, role, domain_id, allowed_domains, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        $stmt->execute([$email, $username, $password, $fullName, $phone, $jobTitle, $notes, $role, $domainId, $allowedDomains, clone $_SESSION['user_id'] ?? null]);
        $empId = $pdo->lastInsertId();
        logAudit($pdo, 'create_employee', 'employee', $empId, "Created employee: $username ($role)");
        notify($pdo, $empId, 'Welcome to Electava Workspace', 'Your account has been created.', 'info');
        $msg = 'Employee created successfully.';
    } elseif ($_POST['action'] === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?")->execute([$uid]);
        logAudit($pdo, 'toggle_employee', 'employee', $uid, 'Toggled employee active status');
        $msg = 'Employee status updated.';
    } elseif ($_POST['action'] === 'reset_password') {
        $uid = (int)$_POST['user_id'];
        $newPass = password_hash('Electava@2025', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?")->execute([$newPass, $uid]);
        logAudit($pdo, 'reset_password', 'employee', $uid, 'Password reset to default');
        $msg = 'Password reset to Electava@2025.';
    } elseif ($_POST['action'] === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            logAudit($pdo, 'delete_employee', 'employee', $uid, 'Deleted employee');
            $msg = 'Employee deleted.';
        }
    } elseif ($_POST['action'] === 'edit') {
        $uid = (int)$_POST['user_id'];
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $role = $_POST['role'];
        $domainId = $_POST['domain_id'] ?: null;
        $allowedDomains = isset($_POST['allowed_domains']) ? json_encode($_POST['allowed_domains']) : null;
        $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, job_title = ?, notes = ?, role = ?, domain_id = ?, allowed_domains = ? WHERE id = ?")->execute([$fullName, $email, $phone, $jobTitle, $notes, $role, $domainId, $allowedDomains, $uid]);
        logAudit($pdo, 'edit_employee', 'employee', $uid, "Edited employee details: $fullName ($role)");
        $msg = 'Employee updated successfully.';
    }
}

// Fetch employees with extra stats
$filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$sql = "SELECT u.*, d.name as domain_name FROM users u LEFT JOIN domains d ON u.domain_id = d.id WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND u.role = ?"; $params[] = $filter; }
if ($search) { $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Pre-fetch stats per employee
$empStats = [];
foreach ($users as $u) {
    $id = $u['id'];
    // Login count
    $lc = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id = ?"); $lc->execute([$id]);
    $empStats[$id]['logins'] = $lc->fetchColumn();
    // Last login
    $ll = $pdo->prepare("SELECT created_at FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"); $ll->execute([$id]);
    $empStats[$id]['last_login'] = $ll->fetchColumn() ?: null;
    // Task count
    $tc = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?"); $tc->execute([$id]);
    $empStats[$id]['tasks'] = $tc->fetchColumn();
    // Completed tasks
    $ct = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('completed','approved')"); $ct->execute([$id]);
    $empStats[$id]['completed_tasks'] = $ct->fetchColumn();
    // If admin role, count subordinates
    if ($u['role'] === 'admin' && $u['domain_id']) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM users WHERE domain_id = ? AND role = 'employee' AND id != ?"); $sc->execute([$u['domain_id'], $id]);
        $empStats[$id]['subordinates'] = $sc->fetchColumn();
        $vc = $pdo->prepare("SELECT COUNT(*) FROM vendors"); $vc->execute();
        $empStats[$id]['vendors'] = $vc->fetchColumn();
    }
    // Audit count
    $ac = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ?"); $ac->execute([$id]);
    $empStats[$id]['audit_actions'] = $ac->fetchColumn();
}

$domains = $pdo->query("SELECT * FROM domains ORDER BY name")->fetchAll();
$domainsJson = json_encode($domains);
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search employees..." class="input-field pl-9 pr-4 py-2 rounded-lg text-sm w-56">
            </div>
            <select name="role" class="input-field px-3 py-2 rounded-lg text-sm" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="core_admin" <?= $filter==='core_admin'?'selected':'' ?>>Core Admin</option>
                <option value="admin" <?= $filter==='admin'?'selected':'' ?>>Admin</option>

                <option value="employee" <?= $filter==='employee'?'selected':'' ?>>Employee</option>
                <option value="vendor" <?= $filter==='vendor'?'selected':'' ?>>Vendor</option>
            </select>
        </form>
    </div>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium text-white">
        <i class="fa-solid fa-plus mr-1.5"></i>Create Employee
    </button>
</div>

<!-- Employees Table -->
<div class="glass-card rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-slate-500 uppercase bg-slate-900/50 border-b border-slate-800/50">
                <tr>
                    <th class="px-5 py-3">Employee</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Domain</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Logins</th>
                    <th class="px-5 py-3">Tasks</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                <?php foreach ($users as $u):
                    $stats = $empStats[$u['id']];
                ?>
                <tr class="table-row cursor-pointer" onclick="openEmployeeDetail(<?= htmlspecialchars(json_encode($u)) ?>, <?= htmlspecialchars(json_encode($stats)) ?>)">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-xs font-bold text-white shadow-lg">
                                <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-medium text-white"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></div>
                                <div class="text-xs text-slate-500">
                                    <?= htmlspecialchars($u['email']) ?>
                                    <?= $u['job_title'] ? ' &bull; ' . htmlspecialchars($u['job_title']) : '' ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5"><?= statusBadge($u['role'] === 'admin' ? 'active' : $u['role']) ?><span class="text-xs text-slate-400 ml-1 capitalize"><?= str_replace('_',' ',$u['role']) ?></span></td>
                    <td class="px-5 py-3.5 text-slate-400 text-xs"><?= htmlspecialchars($u['domain_name'] ?? '—') ?></td>
                    <td class="px-5 py-3.5"><?= $u['status'] === 'active' ? '<span class="text-emerald-400 text-xs"><i class="fa-solid fa-circle text-[6px] mr-1"></i>Active</span>' : '<span class="text-red-400 text-xs"><i class="fa-solid fa-circle text-[6px] mr-1"></i>Disabled</span>' ?></td>
                    <td class="px-5 py-3.5 text-xs text-slate-400"><?= $stats['logins'] ?></td>
                    <td class="px-5 py-3.5 text-xs text-slate-400"><?= $stats['completed_tasks'] ?>/<?= $stats['tasks'] ?></td>
                    <td class="px-5 py-3.5 text-right" onclick="event.stopPropagation()">
                        <button onclick="openEmployeeDetail(<?= htmlspecialchars(json_encode($u)) ?>, <?= htmlspecialchars(json_encode($stats)) ?>)" class="text-xs text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 hover:border-emerald-500/40 transition">
                            <i class="fa-solid fa-eye mr-1"></i>View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Employee Modal -->
<div id="createModal" class="hidden fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4">
    <div class="glass-card rounded-2xl p-6 w-full max-w-lg shadow-2xl border border-slate-700/50">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-white">Create New Employee</h3>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-500 hover:text-white text-lg"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Full Name</label><input type="text" name="full_name" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Username</label><input type="text" name="username" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-400 mb-1.5">Email</label><input type="email" name="email" required class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-400 mb-1.5">Phone</label><input type="text" name="phone" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-400 mb-1.5">Password</label><input type="password" name="password" required value="Electava@2025" class="input-field w-full px-3 py-2 rounded-lg text-sm"></div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Role</label>
                    <select name="role" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <option value="admin">Admin</option>

                        <option value="employee">Employee</option>
                        <option value="vendor">Vendor</option>
                        <option value="core_admin">Core Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Primary Domain</label>
                    <select name="domain_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <option value="">— None —</option>
                        <?php foreach ($domains as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Additional Domains</label>
                <select name="allowed_domains[]" multiple class="input-field w-full px-3 py-2 rounded-lg text-sm" size="3">
                    <?php foreach ($domains as $d): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                </select>
                <div class="text-[10px] text-slate-500 mt-1">Hold Ctrl (or Cmd) to select multiple domains. This is mainly useful for employee roles.</div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Job Title</label>
                    <input type="text" name="job_title" placeholder="e.g. Senior PCB Designer" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Additional Notes</label>
                    <textarea name="notes" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Cancel</button>
                <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-sm text-white font-medium"><i class="fa-solid fa-plus mr-1.5"></i>Create Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Employee Detail Modal (JavaScript) ===== -->
<script>
const allDomains = <?= $domainsJson ?>;

function openEmployeeDetail(emp, stats) {
    // Remove any existing modal
    const existing = document.getElementById('empDetailModal');
    if (existing) existing.remove();

    const isAdmin = emp.role === 'admin';
    const isSelf = emp.id == <?= $_SESSION['user_id'] ?>;
    const lastLogin = stats.last_login ? new Date(stats.last_login).toLocaleString() : 'Never';

    // Build domain options
    let domainOpts = '<option value="">— None —</option>';
    allDomains.forEach(d => {
        domainOpts += `<option value="${d.id}" ${emp.domain_id == d.id ? 'selected' : ''}>${d.name}</option>`;
    });

    // Admin subordinate section
    let adminSection = '';
    if (isAdmin) {
        adminSection = `
        <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="bg-blue-500/10 border border-blue-500/20 p-4 rounded-xl text-center">
                <div class="text-2xl font-bold text-blue-400">${stats.subordinates || 0}</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Employees Under</div>
            </div>
            <div class="bg-purple-500/10 border border-purple-500/20 p-4 rounded-xl text-center">
                <div class="text-2xl font-bold text-purple-400">${stats.vendors || 0}</div>
                <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-1">Total Vendors</div>
            </div>
        </div>`;
    }

    const html = `
    <div id="empDetailModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4" onclick="if(event.target===this) this.remove()">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scrollbar" style="background: linear-gradient(135deg, rgba(30,41,59,0.97), rgba(15,23,42,0.99)); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); border-radius: 1.25rem; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
            
            <!-- Header -->
            <div class="p-6 border-b border-slate-800/60">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-xl font-bold text-white shadow-xl shadow-emerald-500/20">
                            ${(emp.full_name || emp.username).charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">${emp.full_name || emp.username} ${emp.job_title ? '<span class="text-sm font-normal text-slate-400 ml-2 border-l border-slate-700 pl-2">' + emp.job_title + '</span>' : ''}</h2>
                            <p class="text-sm text-slate-400">@${emp.username} · ${emp.email} ${emp.phone ? '· ' + emp.phone : ''}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold tracking-widest uppercase ${emp.status === 'active' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : 'bg-red-500/15 text-red-400 border border-red-500/25'}">${emp.status === 'active' ? 'Active' : 'Disabled'}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold tracking-widest uppercase bg-slate-700/50 text-slate-300 border border-slate-600/30">${emp.role.replace('_',' ')}</span>
                            </div>
                        </div>
                    </div>
                    <button onclick="document.getElementById('empDetailModal').remove()" class="text-slate-400 hover:text-white transition p-1"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="p-6 border-b border-slate-800/60">
                <h3 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">Activity & Tracking</h3>
                <div class="grid grid-cols-4 gap-3">
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-emerald-400">${stats.logins}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Total Logins</div>
                    </div>
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-amber-400">${stats.tasks}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Total Tasks</div>
                    </div>
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-cyan-400">${stats.completed_tasks}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Completed</div>
                    </div>
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-slate-300">${stats.audit_actions}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Audit Actions</div>
                    </div>
                </div>
                <div class="mt-3 bg-slate-800/30 p-3 rounded-lg flex items-center gap-3">
                    <i class="fa-solid fa-clock text-slate-500 text-sm"></i>
                    <div>
                        <span class="text-[10px] text-slate-500 uppercase tracking-widest">Last Login</span>
                        <span class="text-sm text-slate-300 ml-2">${lastLogin}</span>
                    </div>
                </div>
                ${adminSection}
            </div>

            <!-- Details Info -->
            <div class="p-6 border-b border-slate-800/60">
                <h3 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">Employee Information</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-slate-800/30 p-3 rounded-lg">
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Employee ID</div>
                        <div class="text-slate-200 font-mono">#${emp.id}</div>
                    </div>
                    <div class="bg-slate-800/30 p-3 rounded-lg">
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Domain</div>
                        <div class="text-slate-200">${emp.domain_name || '— Unassigned —'}</div>
                    </div>
                    <div class="bg-slate-800/30 p-3 rounded-lg">
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Created</div>
                        <div class="text-slate-200">${new Date(emp.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'})}</div>
                    </div>
                    <div class="bg-slate-800/30 p-3 rounded-lg">
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Task Completion</div>
                        <div class="text-slate-200">${stats.tasks > 0 ? Math.round((stats.completed_tasks / stats.tasks) * 100) : 0}%</div>
                    </div>
                </div>
                ${emp.notes ? `
                <div class="mt-4 bg-slate-800/30 p-4 rounded-xl border border-slate-700/50 text-sm">
                    <div class="text-[10px] text-slate-500 uppercase tracking-widest mb-2 font-semibold">Additional Notes</div>
                    <div class="text-slate-300 whitespace-pre-wrap leading-relaxed">${emp.notes}</div>
                </div>` : ''}
            </div>

            <!-- Edit Form (Collapsible) -->
            <div class="p-6 border-b border-slate-800/60">
                <button onclick="document.getElementById('editSection').classList.toggle('hidden')" class="flex items-center gap-2 text-sm text-emerald-400 hover:text-emerald-300 transition font-medium w-full">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Employee Details
                    <i class="fa-solid fa-chevron-down ml-auto text-xs"></i>
                </button>
                <div id="editSection" class="hidden mt-4">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" value="${emp.id}">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Full Name</label>
                                <input type="text" name="full_name" value="${emp.full_name || ''}" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Email</label>
                                <input type="email" name="email" value="${emp.email}" required class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Phone</label>
                                <input type="text" name="phone" value="${emp.phone || ''}" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Role</label>
                                <select name="role" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                    <option value="core_admin" ${emp.role==='core_admin'?'selected':''}>Core Admin</option>
                                    <option value="admin" ${emp.role==='admin'?'selected':''}>Admin</option>

                                    <option value="employee" ${emp.role==='employee'?'selected':''}>Employee</option>
                                    <option value="vendor" ${emp.role==='vendor'?'selected':''}>Vendor</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Primary Domain</label>
                                <select name="domain_id" class="input-field w-full px-3 py-2 rounded-lg text-sm">${domainOpts}</select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Additional Domains</label>
                            <select name="allowed_domains[]" multiple class="input-field w-full px-3 py-2 rounded-lg text-sm" size="3">
                                ${allDomains.map(d => {
                                    let allowed = [];
                                    try { allowed = emp.allowed_domains ? JSON.parse(emp.allowed_domains) : []; } catch(e){}
                                    return '<option value="' + d.id + '" ' + (allowed.includes(d.id.toString()) || allowed.includes(d.id) ? 'selected' : '') + '>' + d.name + '</option>';
                                }).join('')}
                            </select>
                            <div class="text-[10px] text-slate-500 mt-1">Hold Ctrl (or Cmd) to select multiple.</div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div class="col-span-2">
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Job Title</label>
                                <input type="text" name="job_title" value="${emp.job_title || ''}" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] text-slate-500 mb-1 uppercase tracking-widest">Notes</label>
                                <textarea name="notes" rows="2" class="input-field w-full px-3 py-2 rounded-lg text-sm">${emp.notes || ''}</textarea>
                            </div>
                        </div>
                        <div class="flex justify-end pt-1">
                            <button type="submit" class="btn-primary px-5 py-2 rounded-lg text-xs text-white font-medium shadow-lg shadow-emerald-600/20"><i class="fa-solid fa-save mr-1.5"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="p-6">
                <h3 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">Quick Actions</h3>
                <div class="grid grid-cols-3 gap-3">
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="${emp.id}">
                        <button type="submit" class="w-full bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500/20 hover:border-blue-500/40 py-3 rounded-xl text-xs transition flex flex-col items-center gap-1.5 font-medium">
                            <i class="fa-solid fa-key text-base"></i> Reset Password
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="${emp.id}">
                        <button type="submit" class="w-full ${emp.status === 'active' ? 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20 hover:border-amber-500/40' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20 hover:border-emerald-500/40'} border py-3 rounded-xl text-xs transition flex flex-col items-center gap-1.5 font-medium">
                            <i class="fa-solid ${emp.status === 'active' ? 'fa-ban' : 'fa-check-circle'} text-base"></i> ${emp.status === 'active' ? 'Disable Account' : 'Enable Account'}
                        </button>
                    </form>
                    ${!isSelf ? `
                    <form method="POST" onsubmit="return confirm('Permanently delete this employee? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="${emp.id}">
                        <button type="submit" class="w-full bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 hover:border-red-500/40 py-3 rounded-xl text-xs transition flex flex-col items-center gap-1.5 font-medium">
                            <i class="fa-solid fa-trash text-base"></i> Delete Employee
                        </button>
                    </form>` : `
                    <div class="w-full bg-slate-800/30 border border-slate-700/30 text-slate-500 py-3 rounded-xl text-xs flex flex-col items-center gap-1.5 font-medium cursor-not-allowed">
                        <i class="fa-solid fa-lock text-base"></i> Cannot Delete Self
                    </div>`}
                </div>
            </div>

        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
