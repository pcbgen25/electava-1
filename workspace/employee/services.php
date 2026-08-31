<?php
$pageTitle = 'Service Requests';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];
$msg = '';

// Handle status update
if (<?php
$pageTitle = 'Service Requests';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];
$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $srId = (int)$_POST['request_id'];
        $newStatus = $_POST['new_status'];
        $notes = trim($_POST['internal_notes'] ?? '');
        $pdo->prepare("UPDATE service_requests SET status = ?, internal_notes = CONCAT(COALESCE(internal_notes,''), '\n---\n', ?) WHERE id = ? AND assigned_to = ?")->execute([$newStatus, date('Y-m-d H:i') . ' - ' . $notes, $srId, $uid]);
        logAudit($pdo, 'update_service_request', 'service_request', $srId, "Status → $newStatus");
        $msg = 'Service request updated.';
    } elseif ($_POST['action'] === 'add_quote') {
        $srId = (int)$_POST['request_id'];
        $quote = (float)$_POST['quoted_price'];
        $pdo->prepare("UPDATE service_requests SET quoted_price = ?, status = 'quoted' WHERE id = ? AND assigned_to = ?")->execute([$quote, $srId, $uid]);
        logAudit($pdo, 'quote_service_request', 'service_request', $srId, "Quote: ₹$quote");
        $msg = 'Quote submitted for approval.';
    }
}

// Fetch service requests assigned to this employee
$filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM service_requests WHERE assigned_to = ?";
$params = [$uid];
if ($filter) { $sql .= " AND status = ?"; $params[] = $filter; }
$sql .= " ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $requests = $stmt->fetchAll();

$totalReqs = count($requests);
$newCount = count(array_filter($requests, fn($r) => $r['status'] === 'new'));
$progressCount = count(array_filter($requests, fn($r) => in_array($r['status'], ['reviewing', 'design_in_progress', 'manufacturing', 'testing'])));
$completedCount = count(array_filter($requests, fn($r) => $r['status'] === 'completed'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Requests</h2>
    <p class="text-sm text-slate-500 mt-1">Manage PCB service requests assigned to you — update status, add quotes, and track progress.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-cogs text-cyan-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalReqs ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">My Requests</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-inbox text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $newCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">New</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-spinner text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $progressCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">In Progress</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $completedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Completed</div>
    </div>
</div>

<!-- Filters -->
<div class="flex items-center gap-2 mb-5">
    <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['new','reviewing','quoted','design_in_progress','manufacturing','testing','completed'] as $s): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
</div>

<!-- Requests List -->
<div class="space-y-4">
    <?php foreach ($requests as $r): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-base font-semibold text-white"><?= htmlspecialchars($r['title']) ?></h3>
                    <?= statusBadge($r['status']) ?>
                    <?= priorityBadge($r['priority']) ?>
                </div>
                <div class="text-xs text-slate-500">
                    <span class="text-emerald-400 font-mono">#SR-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span> ·
                    <?= ucwords(str_replace('_', ' ', $r['service_type'])) ?> ·
                    <?= htmlspecialchars($r['customer_name']) ?> (<?= htmlspecialchars($r['customer_email']) ?>)
                </div>
            </div>
            <span class="text-xs text-slate-600 shrink-0"><?= timeAgo($r['created_at']) ?></span>
        </div>

        <?php if ($r['description']): ?>
        <p class="text-sm text-slate-400 mb-3"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Layers</span>
                <span class="text-white font-medium"><?= $r['layers'] ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Board Size</span>
                <span class="text-white font-medium"><?= htmlspecialchars($r['board_size'] ?? '—') ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quantity</span>
                <span class="text-white font-medium"><?= number_format($r['quantity']) ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quoted Price</span>
                <span class="text-emerald-400 font-medium"><?= $r['quoted_price'] ? '₹' . number_format($r['quoted_price'], 2) : '—' ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-800/50">
            <?php if (!in_array($r['status'], ['completed', 'cancelled'])): ?>
            <!-- Update Status -->
            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <select name="new_status" class="input-field px-2 py-1.5 rounded-lg text-xs">
                    <option value="reviewing" <?= $r['status']==='reviewing'?'selected':'' ?>>Reviewing</option>
                    <option value="design_in_progress" <?= $r['status']==='design_in_progress'?'selected':'' ?>>Design In Progress</option>
                    <option value="manufacturing" <?= $r['status']==='manufacturing'?'selected':'' ?>>Manufacturing</option>
                    <option value="testing" <?= $r['status']==='testing'?'selected':'' ?>>Testing</option>
                    <option value="completed">Completed</option>
                </select>
                <input type="text" name="internal_notes" placeholder="Notes..." class="input-field px-2 py-1.5 rounded-lg text-xs w-40">
                <button class="text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-lg hover:bg-blue-600/40 transition font-medium">
                    <i class="fa-solid fa-sync mr-1"></i>Update
                </button>
            </form>

            <?php if (!$r['quoted_price']): ?>
            <!-- Add Quote -->
            <form method="POST" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="action" value="add_quote">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <input type="number" name="quoted_price" step="0.01" placeholder="₹ Quote" required class="input-field px-2 py-1.5 rounded-lg text-xs w-28">
                <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                    <i class="fa-solid fa-tag mr-1"></i>Quote
                </button>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-xs text-slate-500"><i class="fa-solid fa-lock mr-1"></i>This request is <?= $r['status'] ?>.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($requests)): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4">
            <i class="fa-solid fa-cogs text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Service Requests</h3>
        <p class="text-sm text-slate-600 max-w-sm mx-auto">Service requests assigned to you will appear here. Ask your manager to assign requests.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_METHOD'] === 'POST' && isset(<?php
$pageTitle = 'Service Requests';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = $_SESSION['user_id'];
$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $srId = (int)$_POST['request_id'];
        $newStatus = $_POST['new_status'];
        $notes = trim($_POST['internal_notes'] ?? '');
        $pdo->prepare("UPDATE service_requests SET status = ?, internal_notes = CONCAT(COALESCE(internal_notes,''), '\n---\n', ?) WHERE id = ? AND assigned_to = ?")->execute([$newStatus, date('Y-m-d H:i') . ' - ' . $notes, $srId, $uid]);
        logAudit($pdo, 'update_service_request', 'service_request', $srId, "Status → $newStatus");
        $msg = 'Service request updated.';
    } elseif ($_POST['action'] === 'add_quote') {
        $srId = (int)$_POST['request_id'];
        $quote = (float)$_POST['quoted_price'];
        $pdo->prepare("UPDATE service_requests SET quoted_price = ?, status = 'quoted' WHERE id = ? AND assigned_to = ?")->execute([$quote, $srId, $uid]);
        logAudit($pdo, 'quote_service_request', 'service_request', $srId, "Quote: ₹$quote");
        $msg = 'Quote submitted for approval.';
    }
}

// Fetch service requests assigned to this employee
$filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM service_requests WHERE assigned_to = ?";
$params = [$uid];
if ($filter) { $sql .= " AND status = ?"; $params[] = $filter; }
$sql .= " ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $requests = $stmt->fetchAll();

$totalReqs = count($requests);
$newCount = count(array_filter($requests, fn($r) => $r['status'] === 'new'));
$progressCount = count(array_filter($requests, fn($r) => in_array($r['status'], ['reviewing', 'design_in_progress', 'manufacturing', 'testing'])));
$completedCount = count(array_filter($requests, fn($r) => $r['status'] === 'completed'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Requests</h2>
    <p class="text-sm text-slate-500 mt-1">Manage PCB service requests assigned to you — update status, add quotes, and track progress.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-cogs text-cyan-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalReqs ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">My Requests</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-inbox text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $newCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">New</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-spinner text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $progressCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">In Progress</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $completedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Completed</div>
    </div>
</div>

<!-- Filters -->
<div class="flex items-center gap-2 mb-5">
    <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['new','reviewing','quoted','design_in_progress','manufacturing','testing','completed'] as $s): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
</div>

<!-- Requests List -->
<div class="space-y-4">
    <?php foreach ($requests as $r): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-base font-semibold text-white"><?= htmlspecialchars($r['title']) ?></h3>
                    <?= statusBadge($r['status']) ?>
                    <?= priorityBadge($r['priority']) ?>
                </div>
                <div class="text-xs text-slate-500">
                    <span class="text-emerald-400 font-mono">#SR-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span> ·
                    <?= ucwords(str_replace('_', ' ', $r['service_type'])) ?> ·
                    <?= htmlspecialchars($r['customer_name']) ?> (<?= htmlspecialchars($r['customer_email']) ?>)
                </div>
            </div>
            <span class="text-xs text-slate-600 shrink-0"><?= timeAgo($r['created_at']) ?></span>
        </div>

        <?php if ($r['description']): ?>
        <p class="text-sm text-slate-400 mb-3"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Layers</span>
                <span class="text-white font-medium"><?= $r['layers'] ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Board Size</span>
                <span class="text-white font-medium"><?= htmlspecialchars($r['board_size'] ?? '—') ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quantity</span>
                <span class="text-white font-medium"><?= number_format($r['quantity']) ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quoted Price</span>
                <span class="text-emerald-400 font-medium"><?= $r['quoted_price'] ? '₹' . number_format($r['quoted_price'], 2) : '—' ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-800/50">
            <?php if (!in_array($r['status'], ['completed', 'cancelled'])): ?>
            <!-- Update Status -->
            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <select name="new_status" class="input-field px-2 py-1.5 rounded-lg text-xs">
                    <option value="reviewing" <?= $r['status']==='reviewing'?'selected':'' ?>>Reviewing</option>
                    <option value="design_in_progress" <?= $r['status']==='design_in_progress'?'selected':'' ?>>Design In Progress</option>
                    <option value="manufacturing" <?= $r['status']==='manufacturing'?'selected':'' ?>>Manufacturing</option>
                    <option value="testing" <?= $r['status']==='testing'?'selected':'' ?>>Testing</option>
                    <option value="completed">Completed</option>
                </select>
                <input type="text" name="internal_notes" placeholder="Notes..." class="input-field px-2 py-1.5 rounded-lg text-xs w-40">
                <button class="text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-lg hover:bg-blue-600/40 transition font-medium">
                    <i class="fa-solid fa-sync mr-1"></i>Update
                </button>
            </form>

            <?php if (!$r['quoted_price']): ?>
            <!-- Add Quote -->
            <form method="POST" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="action" value="add_quote">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <input type="number" name="quoted_price" step="0.01" placeholder="₹ Quote" required class="input-field px-2 py-1.5 rounded-lg text-xs w-28">
                <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                    <i class="fa-solid fa-tag mr-1"></i>Quote
                </button>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-xs text-slate-500"><i class="fa-solid fa-lock mr-1"></i>This request is <?= $r['status'] ?>.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($requests)): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4">
            <i class="fa-solid fa-cogs text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Service Requests</h3>
        <p class="text-sm text-slate-600 max-w-sm mx-auto">Service requests assigned to you will appear here. Ask your manager to assign requests.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'update_status') {
        $srId = (int)$_POST['request_id'];
        $newStatus = $_POST['new_status'];
        $notes = trim($_POST['internal_notes'] ?? '');
        $pdo->prepare("UPDATE service_requests SET status = ?, internal_notes = CONCAT(COALESCE(internal_notes,''), '\n---\n', ?) WHERE id = ? AND assigned_to = ?")->execute([$newStatus, date('Y-m-d H:i') . ' - ' . $notes, $srId, $uid]);
        logAudit($pdo, 'update_service_request', 'service_request', $srId, "Status → $newStatus");
        $msg = 'Service request updated.';
    } elseif ($_POST['action'] === 'add_quote') {
        $srId = (int)$_POST['request_id'];
        $quote = (float)$_POST['quoted_price'];
        $pdo->prepare("UPDATE service_requests SET quoted_price = ?, status = 'quoted' WHERE id = ? AND assigned_to = ?")->execute([$quote, $srId, $uid]);
        logAudit($pdo, 'quote_service_request', 'service_request', $srId, "Quote: ₹$quote");
        $msg = 'Quote submitted for approval.';
    }
}

// Fetch service requests assigned to this employee
$filter = $_GET['status'] ?? '';
$sql = "SELECT * FROM service_requests WHERE assigned_to = ?";
$params = [$uid];
if ($filter) { $sql .= " AND status = ?"; $params[] = $filter; }
$sql .= " ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $requests = $stmt->fetchAll();

$totalReqs = count($requests);
$newCount = count(array_filter($requests, fn($r) => $r['status'] === 'new'));
$progressCount = count(array_filter($requests, fn($r) => in_array($r['status'], ['reviewing', 'design_in_progress', 'manufacturing', 'testing'])));
$completedCount = count(array_filter($requests, fn($r) => $r['status'] === 'completed'));
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= $msg ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Requests</h2>
    <p class="text-sm text-slate-500 mt-1">Manage PCB service requests assigned to you — update status, add quotes, and track progress.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-cogs text-cyan-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $totalReqs ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">My Requests</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-inbox text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $newCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">New</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-spinner text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $progressCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">In Progress</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $completedCount ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Completed</div>
    </div>
</div>

<!-- Filters -->
<div class="flex items-center gap-2 mb-5">
    <a href="?status=" class="<?= !$filter?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= !$filter?'text-white':'text-slate-400' ?>">All</a>
    <?php foreach(['new','reviewing','quoted','design_in_progress','manufacturing','testing','completed'] as $s): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s?'btn-primary':'btn-secondary' ?> px-3 py-1.5 rounded-lg text-xs <?= $filter===$s?'text-white':'text-slate-400' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
</div>

<!-- Requests List -->
<div class="space-y-4">
    <?php foreach ($requests as $r): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-base font-semibold text-white"><?= htmlspecialchars($r['title']) ?></h3>
                    <?= statusBadge($r['status']) ?>
                    <?= priorityBadge($r['priority']) ?>
                </div>
                <div class="text-xs text-slate-500">
                    <span class="text-emerald-400 font-mono">#SR-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span> ·
                    <?= ucwords(str_replace('_', ' ', $r['service_type'])) ?> ·
                    <?= htmlspecialchars($r['customer_name']) ?> (<?= htmlspecialchars($r['customer_email']) ?>)
                </div>
            </div>
            <span class="text-xs text-slate-600 shrink-0"><?= timeAgo($r['created_at']) ?></span>
        </div>

        <?php if ($r['description']): ?>
        <p class="text-sm text-slate-400 mb-3"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Layers</span>
                <span class="text-white font-medium"><?= $r['layers'] ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Board Size</span>
                <span class="text-white font-medium"><?= htmlspecialchars($r['board_size'] ?? '—') ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quantity</span>
                <span class="text-white font-medium"><?= number_format($r['quantity']) ?></span>
            </div>
            <div class="bg-slate-800/30 px-3 py-2 rounded-lg text-xs">
                <span class="text-slate-500 block">Quoted Price</span>
                <span class="text-emerald-400 font-medium"><?= $r['quoted_price'] ? '₹' . number_format($r['quoted_price'], 2) : '—' ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-800/50">
            <?php if (!in_array($r['status'], ['completed', 'cancelled'])): ?>
            <!-- Update Status -->
            <form method="POST" class="flex items-center gap-2">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <select name="new_status" class="input-field px-2 py-1.5 rounded-lg text-xs">
                    <option value="reviewing" <?= $r['status']==='reviewing'?'selected':'' ?>>Reviewing</option>
                    <option value="design_in_progress" <?= $r['status']==='design_in_progress'?'selected':'' ?>>Design In Progress</option>
                    <option value="manufacturing" <?= $r['status']==='manufacturing'?'selected':'' ?>>Manufacturing</option>
                    <option value="testing" <?= $r['status']==='testing'?'selected':'' ?>>Testing</option>
                    <option value="completed">Completed</option>
                </select>
                <input type="text" name="internal_notes" placeholder="Notes..." class="input-field px-2 py-1.5 rounded-lg text-xs w-40">
                <button class="text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-lg hover:bg-blue-600/40 transition font-medium">
                    <i class="fa-solid fa-sync mr-1"></i>Update
                </button>
            </form>

            <?php if (!$r['quoted_price']): ?>
            <!-- Add Quote -->
            <form method="POST" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="action" value="add_quote">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <input type="number" name="quoted_price" step="0.01" placeholder="₹ Quote" required class="input-field px-2 py-1.5 rounded-lg text-xs w-28">
                <button class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                    <i class="fa-solid fa-tag mr-1"></i>Quote
                </button>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-xs text-slate-500"><i class="fa-solid fa-lock mr-1"></i>This request is <?= $r['status'] ?>.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($requests)): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/60 flex items-center justify-center mb-4">
            <i class="fa-solid fa-cogs text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-400 mb-2">No Service Requests</h3>
        <p class="text-sm text-slate-600 max-w-sm mx-auto">Service requests assigned to you will appear here. Ask your manager to assign requests.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
