<?php
$pageTitle = 'Service Requests Queue';
require_once __DIR__ . '/../includes/header.php';
requireRole('service_team');

$uid = (int)$_SESSION['user_id'];
$msg = '';

$serviceTeamMembers = $pdo->query("SELECT id, full_name, username FROM users WHERE role != 'vendor' AND status = 'active' ORDER BY full_name, username")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'take_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId > 0) {
            $pdo->prepare("UPDATE service_requests SET assigned_to = ? WHERE id = ?")->execute([$uid, $requestId]);
            logAudit($pdo, 'take_service_request', 'service_request', $requestId, 'Service team member took ownership of the request');
            $msg = 'Service request assigned to you.';
        }
    } elseif ($_POST['action'] === 'save_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $assignedTo = $assignedTo > 0 ? $assignedTo : null;
        $status = $_POST['status'] ?? 'new';
        $priority = $_POST['priority'] ?? 'medium';
        $quotedPriceRaw = trim((string)($_POST['quoted_price'] ?? ''));
        $quotedPrice = $quotedPriceRaw === '' ? null : (float)$quotedPriceRaw;
        $internalNotes = trim((string)($_POST['internal_notes'] ?? ''));
        $requirementNotes = trim((string)($_POST['requirement_notes'] ?? ''));
        $vendorNotes = trim((string)($_POST['vendor_notes'] ?? ''));
        $verificationNotes = trim((string)($_POST['verification_notes'] ?? ''));
        $markContacted = isset($_POST['mark_contacted']) ? 1 : 0;

        $currentStmt = $pdo->prepare("SELECT assigned_to FROM service_requests WHERE id = ?");
        $currentStmt->execute([$requestId]);
        $currentRequest = $currentStmt->fetch();

        if ($currentRequest) {
            $pdo->prepare("
                UPDATE service_requests
                SET assigned_to = ?, status = ?, priority = ?, quoted_price = ?, internal_notes = ?, requirement_notes = ?, vendor_notes = ?, verification_notes = ?,
                    last_contact_at = CASE WHEN ? = 1 THEN NOW() ELSE last_contact_at END
                WHERE id = ?
            ")->execute([
                $assignedTo,
                $status,
                $priority,
                $quotedPrice,
                $internalNotes,
                $requirementNotes,
                $vendorNotes,
                $verificationNotes,
                $markContacted,
                $requestId,
            ]);

            if ($assignedTo && (int)$currentRequest['assigned_to'] !== $assignedTo) {
                notify($pdo, $assignedTo, 'Service request assigned', 'A service request has been assigned to you.', 'task', '/service_team/requests.php');
            }

            logAudit($pdo, 'save_service_request_queue', 'service_request', $requestId, json_encode([
                'assigned_to' => $assignedTo,
                'status' => $status,
                'priority' => $priority,
                'quoted_price' => $quotedPrice,
                'mark_contacted' => (bool)$markContacted,
            ]));

            $msg = 'Service request updated.';
        }
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$ownerFilter = trim((string)($_GET['owner'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT sr.*, e.full_name AS assignee_name
    FROM service_requests sr
    LEFT JOIN users e ON e.id = sr.assigned_to
    WHERE 1=1
";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND sr.status = ?";
    $params[] = $statusFilter;
}

if ($ownerFilter === 'mine') {
    $sql .= " AND sr.assigned_to = ?";
    $params[] = $uid;
} elseif ($ownerFilter === 'unassigned') {
    $sql .= " AND sr.assigned_to IS NULL";
}

if ($search !== '') {
    $sql .= " AND (sr.title LIKE ? OR sr.customer_name LIKE ? OR sr.customer_email LIKE ? OR sr.description LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY FIELD(sr.priority, 'urgent', 'high', 'medium', 'low'), sr.updated_at DESC, sr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$statusOptions = ['new', 'reviewing', 'quoted', 'design_in_progress', 'manufacturing', 'testing', 'completed', 'cancelled'];
$priorityOptions = ['low', 'medium', 'high', 'urgent'];
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Requests Queue</h2>
    <p class="text-sm text-slate-500 mt-1">Capture requirement notes, coordinate vendors, and record verification updates for every incoming service request.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-2xl mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Search</label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title, customer, email..." class="input-field px-3 py-2 rounded-lg text-sm w-64">
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Status</label>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-sm w-48">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
            <option value="<?= $statusOption ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $statusOption)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Owner</label>
        <select name="owner" class="input-field px-3 py-2 rounded-lg text-sm w-40">
            <option value="">All</option>
            <option value="mine" <?= $ownerFilter === 'mine' ? 'selected' : '' ?>>My queue</option>
            <option value="unassigned" <?= $ownerFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
        </select>
    </div>
    <button class="btn-primary px-4 py-2 rounded-lg text-sm text-white">Filter</button>
    <a href="/service_team/requests.php" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Clear</a>
    <span class="text-xs text-slate-500 ml-auto"><?= number_format(count($requests)) ?> requests</span>
</form>

<div class="space-y-4">
    <?php foreach ($requests as $request): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h3 class="text-lg font-semibold text-white"><?= htmlspecialchars($request['title']) ?></h3>
                    <?= statusBadge($request['status']) ?>
                    <?= priorityBadge($request['priority']) ?>
                </div>
                <div class="text-xs text-slate-500 space-x-2">
                    <span class="text-emerald-400 font-mono">#SR-<?= str_pad((string)$request['id'], 4, '0', STR_PAD_LEFT) ?></span>
                    <span><?= ucwords(str_replace('_', ' ', $request['service_type'])) ?></span>
                    <span><?= htmlspecialchars($request['customer_name']) ?></span>
                    <span><?= htmlspecialchars($request['customer_email']) ?></span>
                </div>
            </div>
            <div class="text-xs text-slate-500 text-right">
                <div>Owner: <span class="text-slate-300"><?= htmlspecialchars($request['assignee_name'] ?: 'Unassigned') ?></span></div>
                <div class="mt-1">Updated <?= timeAgo($request['updated_at']) ?></div>
                <div class="mt-1">Last contact: <?= $request['last_contact_at'] ? date('d M Y H:i', strtotime($request['last_contact_at'])) : 'Not marked' ?></div>
            </div>
        </div>

        <?php if ($request['description']): ?>
        <div class="rounded-xl bg-slate-900/20 border border-slate-800/60 p-4 text-sm text-slate-300 mb-4">
            <?= nl2br(htmlspecialchars($request['description'])) ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <div class="bg-slate-900/20 border border-slate-800/50 rounded-xl px-3 py-2">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Layers</div>
                <div class="text-sm text-white mt-1"><?= (int)$request['layers'] ?></div>
            </div>
            <div class="bg-slate-900/20 border border-slate-800/50 rounded-xl px-3 py-2">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Board Size</div>
                <div class="text-sm text-white mt-1"><?= htmlspecialchars($request['board_size'] ?: 'Not provided') ?></div>
            </div>
            <div class="bg-slate-900/20 border border-slate-800/50 rounded-xl px-3 py-2">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Quantity</div>
                <div class="text-sm text-white mt-1"><?= number_format((int)$request['quantity']) ?></div>
            </div>
            <div class="bg-slate-900/20 border border-slate-800/50 rounded-xl px-3 py-2">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Quoted Price</div>
                <div class="text-sm text-emerald-400 mt-1"><?= $request['quoted_price'] !== null ? 'INR ' . number_format((float)$request['quoted_price'], 2) : 'Pending' ?></div>
            </div>
            <div class="bg-slate-900/20 border border-slate-800/50 rounded-xl px-3 py-2">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest">Created</div>
                <div class="text-sm text-white mt-1"><?= date('d M Y', strtotime($request['created_at'])) ?></div>
            </div>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="save_request">
            <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Assign To</label>
                    <select name="assigned_to" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <option value="">Unassigned</option>
                        <?php foreach ($serviceTeamMembers as $member): ?>
                        <option value="<?= (int)$member['id'] ?>" <?= (int)$request['assigned_to'] === (int)$member['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($member['full_name'] ?: $member['username']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Status</label>
                    <select name="status" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <?php foreach ($statusOptions as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $request['status'] === $statusOption ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $statusOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Priority</label>
                    <select name="priority" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <?php foreach ($priorityOptions as $priorityOption): ?>
                        <option value="<?= $priorityOption ?>" <?= $request['priority'] === $priorityOption ? 'selected' : '' ?>><?= ucfirst($priorityOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Quoted Price</label>
                    <input type="number" name="quoted_price" min="0" step="0.01" value="<?= htmlspecialchars($request['quoted_price'] !== null ? (string)$request['quoted_price'] : '') ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="INR amount">
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Requirement Notes</label>
                    <textarea name="requirement_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="What is still needed from the customer?"><?= htmlspecialchars((string)$request['requirement_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Vendor Coordination Notes</label>
                    <textarea name="vendor_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Track sourcing, manufacturing, or supplier follow-up."><?= htmlspecialchars((string)$request['vendor_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Verification Notes</label>
                    <textarea name="verification_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Store checks, approvals, and verification remarks."><?= htmlspecialchars((string)$request['verification_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Internal Team Notes</label>
                    <textarea name="internal_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="General coordination notes for the service desk."><?= htmlspecialchars((string)$request['internal_notes']) ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <label class="inline-flex items-center gap-2 text-xs text-slate-400">
                    <input type="checkbox" name="mark_contacted" value="1" class="rounded border-slate-600 bg-slate-900/30">
                    Mark contact made now
                </label>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm text-white">
                    <i class="fa-solid fa-save mr-1.5"></i>Save Update
                </button>
            </div>
        </form>

        <?php if ((int)$request['assigned_to'] !== $uid): ?>
        <form method="POST" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="take_request">
            <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
            <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">
                <i class="fa-solid fa-hand mr-1.5"></i>Take Ownership
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!$requests): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-dashed border-slate-700/60">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-900/20 flex items-center justify-center mb-4">
            <i class="fa-solid fa-screwdriver-wrench text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-300">No Service Requests Found</h3>
        <p class="text-sm text-slate-500 mt-2">When customers create service requests, they will appear here for the service team to pick up.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
