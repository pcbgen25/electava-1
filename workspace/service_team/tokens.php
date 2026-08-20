<?php
$pageTitle = 'Service Tokens Queue';
require_once __DIR__ . '/../includes/header.php';
requireRole('service_team');

$uid = (int)$_SESSION['user_id'];
$msg = '';

$serviceTeamMembers = $pdo->query("SELECT id, full_name, username FROM employees WHERE role = 'service_team' AND is_active = 1 ORDER BY full_name, username")->fetchAll();

function renderTokenDetails(?string $details): string {
    if (!$details) {
        return '<div class="text-sm text-slate-500">No request details were submitted.</div>';
    }

    $decoded = json_decode($details, true);
    if (!is_array($decoded)) {
        return '<div class="text-sm text-slate-300 whitespace-pre-wrap">' . nl2br(htmlspecialchars($details)) . '</div>';
    }

    $rows = [];
    foreach ($decoded as $key => $value) {
        if ($value === '' || $value === null || $key === 'ndaAgreed') {
            continue;
        }
        $label = trim((string)preg_replace('/([A-Z])/', ' $1', (string)$key));
        $displayValue = is_scalar($value) ? (string)$value : json_encode($value);
        $rows[] = '<div class="flex items-start justify-between gap-3 py-2 border-b border-slate-800/40 last:border-0">'
            . '<span class="text-[11px] uppercase tracking-widest text-slate-500">' . htmlspecialchars($label) . '</span>'
            . '<span class="text-sm text-slate-200 text-right max-w-[65%]">' . htmlspecialchars($displayValue) . '</span>'
            . '</div>';
    }

    if (!$rows) {
        return '<div class="text-sm text-slate-500">No structured request details were available.</div>';
    }

    return implode('', $rows);
}

function buildTokenReplyHtml(string $tokenNumber, string $replyBody): string {
    return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 0 auto; background: #1e293b; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; color: white; }
        .body { padding: 28px 32px; }
        .body p { line-height: 1.7; color: #cbd5e1; font-size: 14px; margin: 0 0 14px; }
        .token { display: inline-block; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34d399; padding: 6px 14px; border-radius: 8px; font-family: monospace; letter-spacing: 1px; font-weight: bold; }
        .footer { padding: 18px 32px; border-top: 1px solid rgba(255,255,255,0.06); color: #64748b; font-size: 11px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Electava Service Team</h1>
        </div>
        <div class="body">
            <p><span class="token">' . htmlspecialchars($tokenNumber) . '</span></p>
            ' . nl2br(htmlspecialchars($replyBody)) . '
        </div>
        <div class="footer">
            Reply sent from Electava service operations.
        </div>
    </div>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'take_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        if ($tokenId > 0) {
            $pdo->prepare("UPDATE service_tokens SET assigned_to = ?, status = CASE WHEN status = 'pending' THEN 'in_progress' ELSE status END WHERE id = ?")->execute([$uid, $tokenId]);
            logAudit($pdo, 'take_service_token', 'service_token', $tokenId, 'Service team member took ownership of the token');
            $msg = 'Service token assigned to you.';
        }
    } elseif ($_POST['action'] === 'save_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $assignedTo = $assignedTo > 0 ? $assignedTo : null;
        $status = $_POST['status'] ?? 'pending';
        $internalNotes = trim((string)($_POST['internal_notes'] ?? ''));
        $requirementNotes = trim((string)($_POST['requirement_notes'] ?? ''));
        $vendorNotes = trim((string)($_POST['vendor_notes'] ?? ''));
        $verificationNotes = trim((string)($_POST['verification_notes'] ?? ''));
        $markContacted = isset($_POST['mark_contacted']) ? 1 : 0;

        $currentStmt = $pdo->prepare("SELECT assigned_to FROM service_tokens WHERE id = ?");
        $currentStmt->execute([$tokenId]);
        $currentToken = $currentStmt->fetch();

        if ($currentToken) {
            $pdo->prepare("
                UPDATE service_tokens
                SET assigned_to = ?, status = ?, internal_notes = ?, requirement_notes = ?, vendor_notes = ?, verification_notes = ?,
                    last_contact_at = CASE WHEN ? = 1 THEN NOW() ELSE last_contact_at END
                WHERE id = ?
            ")->execute([
                $assignedTo,
                $status,
                $internalNotes,
                $requirementNotes,
                $vendorNotes,
                $verificationNotes,
                $markContacted,
                $tokenId,
            ]);

            if ($assignedTo && (int)$currentToken['assigned_to'] !== $assignedTo) {
                notify($pdo, $assignedTo, 'Service token assigned', 'A marketplace service token has been assigned to you.', 'task', '/service_team/tokens.php');
            }

            logAudit($pdo, 'save_service_token_queue', 'service_token', $tokenId, json_encode([
                'assigned_to' => $assignedTo,
                'status' => $status,
                'mark_contacted' => (bool)$markContacted,
            ]));

            $msg = 'Service token updated.';
        }
    } elseif ($_POST['action'] === 'reply_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        $tokenNumber = trim((string)($_POST['token_number'] ?? ''));
        $customerEmail = trim((string)($_POST['customer_email'] ?? ''));
        $emailSubject = trim((string)($_POST['email_subject'] ?? ''));
        $replyBody = trim((string)($_POST['reply_body'] ?? ''));
        $postStatus = trim((string)($_POST['post_status'] ?? 'replied'));
        $ccEmail = trim((string)($_POST['cc_email'] ?? ''));

        if ($tokenId > 0 && $customerEmail !== '' && $emailSubject !== '' && $replyBody !== '') {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Electava Service Team <noreply@electava.com>\r\n";
            $headers .= "Reply-To: support@electava.com\r\n";
            if ($ccEmail !== '') {
                $headers .= "Cc: $ccEmail\r\n";
            }

            $mailSent = @mail($customerEmail, $emailSubject, buildTokenReplyHtml($tokenNumber, $replyBody), $headers);

            $pdo->prepare("
                UPDATE service_tokens
                SET assigned_to = COALESCE(assigned_to, ?), status = ?, last_contact_at = NOW()
                WHERE id = ?
            ")->execute([$uid, $postStatus, $tokenId]);

            logAudit($pdo, 'reply_service_token', 'service_token', $tokenId, json_encode([
                'to' => $customerEmail,
                'cc' => $ccEmail,
                'subject' => $emailSubject,
                'status' => $postStatus,
                'mail_status' => $mailSent ? 'sent' : 'queued_locally',
            ]));

            $msg = $mailSent ? 'Reply sent successfully.' : 'Reply saved and marked locally. Mail delivery could not be confirmed in this environment.';
        }
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$ownerFilter = trim((string)($_GET['owner'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT st.*, e.full_name AS assignee_name
    FROM service_tokens st
    LEFT JOIN employees e ON e.id = st.assigned_to
    WHERE 1=1
";
$params = [];

if ($statusFilter !== '') {
    $sql .= " AND st.status = ?";
    $params[] = $statusFilter;
}

if ($ownerFilter === 'mine') {
    $sql .= " AND st.assigned_to = ?";
    $params[] = $uid;
} elseif ($ownerFilter === 'unassigned') {
    $sql .= " AND st.assigned_to IS NULL";
}

if ($search !== '') {
    $sql .= " AND (st.token_number LIKE ? OR st.user_email LIKE ? OR st.service_type LIKE ? OR st.details LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY st.updated_at DESC, st.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tokens = $stmt->fetchAll();

$statusOptions = ['pending', 'in_progress', 'replied', 'completed', 'cancelled'];
?>

<?php if ($msg): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Tokens Queue</h2>
    <p class="text-sm text-slate-500 mt-1">Handle marketplace service inquiries, reply to customers, and record coordination with vendors and verification notes.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-2xl mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Search</label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Token, email, service..." class="input-field px-3 py-2 rounded-lg text-sm w-64">
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Status</label>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-sm w-44">
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
    <a href="/service_team/tokens.php" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">Clear</a>
    <span class="text-xs text-slate-500 ml-auto"><?= number_format(count($tokens)) ?> tokens</span>
</form>

<div class="space-y-4">
    <?php foreach ($tokens as $token): ?>
    <div class="glass-card rounded-2xl p-5 border border-slate-700/50">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h3 class="text-lg font-semibold text-white"><?= htmlspecialchars($token['token_number']) ?></h3>
                    <?= statusBadge($token['status']) ?>
                </div>
                <div class="text-xs text-slate-500 space-x-2">
                    <span><?= htmlspecialchars($token['user_email'] ?: 'No email provided') ?></span>
                    <span><?= ucwords(str_replace(['_', '-'], ' ', (string)$token['service_type'])) ?></span>
                </div>
            </div>
            <div class="text-xs text-slate-500 text-right">
                <div>Owner: <span class="text-slate-300"><?= htmlspecialchars($token['assignee_name'] ?: 'Unassigned') ?></span></div>
                <div class="mt-1">Updated <?= timeAgo($token['updated_at']) ?></div>
                <div class="mt-1">Last contact: <?= $token['last_contact_at'] ? date('d M Y H:i', strtotime($token['last_contact_at'])) : 'Not marked' ?></div>
            </div>
        </div>

        <div class="rounded-xl bg-slate-900/20 border border-slate-800/60 p-4 mb-4">
            <div class="text-[10px] uppercase tracking-widest text-slate-500 mb-3">Request Details</div>
            <?= renderTokenDetails($token['details']) ?>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_token">
            <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Assign To</label>
                    <select name="assigned_to" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <option value="">Unassigned</option>
                        <?php foreach ($serviceTeamMembers as $member): ?>
                        <option value="<?= (int)$member['id'] ?>" <?= (int)$token['assigned_to'] === (int)$member['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($member['full_name'] ?: $member['username']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Status</label>
                    <select name="status" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                        <?php foreach ($statusOptions as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $token['status'] === $statusOption ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $statusOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Requirement Notes</label>
                    <textarea name="requirement_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Capture what the customer still needs to provide."><?= htmlspecialchars((string)$token['requirement_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Vendor Coordination Notes</label>
                    <textarea name="vendor_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Track quotes, supplier follow-up, and vendor communication."><?= htmlspecialchars((string)$token['vendor_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Verification Notes</label>
                    <textarea name="verification_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Store checks, approvals, and verification remarks."><?= htmlspecialchars((string)$token['verification_notes']) ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Internal Team Notes</label>
                    <textarea name="internal_notes" rows="4" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="General service desk notes for the team."><?= htmlspecialchars((string)$token['internal_notes']) ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-xs text-slate-400">
                    <input type="checkbox" name="mark_contacted" value="1" class="rounded border-slate-600 bg-slate-900/30">
                    Mark contact made now
                </label>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm text-white">
                    <i class="fa-solid fa-save mr-1.5"></i>Save Update
                </button>
            </div>
        </form>

        <?php if ((int)$token['assigned_to'] !== $uid): ?>
        <form method="POST" class="mt-3">
            <input type="hidden" name="action" value="take_token">
            <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
            <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">
                <i class="fa-solid fa-hand mr-1.5"></i>Take Ownership
            </button>
        </form>
        <?php endif; ?>

        <details class="mt-4 rounded-xl border border-slate-700/50 bg-slate-900/10">
            <summary class="cursor-pointer list-none px-4 py-3 text-sm text-emerald-400 font-medium flex items-center justify-between">
                <span><i class="fa-solid fa-paper-plane mr-1.5"></i>Reply to Customer</span>
                <span class="text-xs text-slate-500">Open reply form</span>
            </summary>
            <div class="px-4 pb-4">
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="reply_token">
                    <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
                    <input type="hidden" name="token_number" value="<?= htmlspecialchars($token['token_number']) ?>">
                    <input type="hidden" name="customer_email" value="<?= htmlspecialchars($token['user_email']) ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Email Subject</label>
                            <input type="text" name="email_subject" required class="input-field w-full px-3 py-2 rounded-lg text-sm" value="Re: Your Electava Service Request">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Status After Reply</label>
                            <select name="post_status" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                                <option value="replied">Replied</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">CC Email</label>
                        <input type="email" name="cc_email" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Optional internal or vendor CC">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Reply Message</label>
                        <textarea name="reply_body" rows="8" required class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Write the customer response here.">Dear Customer,

Thank you for contacting Electava. We have reviewed your service request and our service team is working on the next steps.

Please let us know if there are any additional files, notes, or requirements you would like us to review.

Best regards,
Electava Service Team</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm text-white">
                            <i class="fa-solid fa-paper-plane mr-1.5"></i>Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </details>
    </div>
    <?php endforeach; ?>

    <?php if (!$tokens): ?>
    <div class="glass-card rounded-2xl p-12 text-center border border-dashed border-slate-700/60">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-900/20 flex items-center justify-center mb-4">
            <i class="fa-solid fa-ticket text-slate-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-300">No Service Tokens Found</h3>
        <p class="text-sm text-slate-500 mt-2">Marketplace inquiry tokens will appear here for the service desk once customers submit them.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
