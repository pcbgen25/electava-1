<?php
$pageTitle = 'Service Tokens Management';
require_once __DIR__ . '/../includes/header.php';
requireRole(['core_admin', 'admin']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $tokenId = (int)$_POST['token_id'];
    $newStatus = $_POST['new_status'];
    $stmt = $pdo->prepare("UPDATE service_tokens SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $tokenId]);
    logAudit($pdo, 'update_token_status', 'service_token', $tokenId, "Status changed to $newStatus");
    header("Location: service_tokens.php?msg=status_updated");
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if (!empty($_GET['token'])) { $where .= " AND token_number LIKE ?"; $params[] = '%'.$_GET['token'].'%'; }
if (!empty($_GET['status'])) { $where .= " AND status = ?"; $params[] = $_GET['status']; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM service_tokens $where");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM service_tokens $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$tokens = $stmt->fetchAll();
?>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i>
    <?php if ($_GET['msg'] === 'status_updated') echo 'Token status updated successfully.'; ?>
    <?php if ($_GET['msg'] === 'reply_sent') echo 'Reply email sent successfully to the customer.'; ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Tokens</h2>
    <p class="text-sm text-slate-500 mt-1">Manage quotation and service requests from the marketplace. Reply to customers directly.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Search Token Number</label>
        <input type="text" name="token" value="<?= htmlspecialchars($_GET['token']??'') ?>" class="input-field px-3 py-2 rounded-lg text-xs w-48" placeholder="SRV-2026-...">
    </div>
    <div>
        <label class="block text-[10px] text-slate-500 mb-1 tracking-wider uppercase">Token Status</label>
        <select name="status" class="input-field px-3 py-2 rounded-lg text-xs w-36">
            <option value="">All Statuses</option>
            <option value="pending" <?= ($_GET['status']??'') === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="in_progress" <?= ($_GET['status']??'') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="replied" <?= ($_GET['status']??'') === 'replied' ? 'selected' : '' ?>>Replied</option>
            <option value="completed" <?= ($_GET['status']??'') === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= ($_GET['status']??'') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white shadow-lg shadow-emerald-500/20">Filter</button>
    <a href="service_tokens.php" class="btn-secondary px-4 py-2 rounded-lg text-xs text-slate-400">Clear</a>
    <span class="text-xs text-slate-600 ml-auto font-medium"><?= number_format($total) ?> tokens</span>
</form>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">Time</th>
                <th class="px-5 py-4 font-semibold">Token Number</th>
                <th class="px-5 py-4 font-semibold">Customer Email</th>
                <th class="px-5 py-4 font-semibold">Requested Service</th>
                <th class="px-5 py-4 font-semibold">Status</th>
                <th class="px-5 py-4 font-semibold text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($tokens as $t): ?>
            <tr class="table-row hover:bg-slate-800/30 transition-colors">
                <td class="px-5 py-4 text-xs text-slate-400 whitespace-nowrap"><?= date('M j, Y H:i', strtotime($t['created_at'])) ?></td>
                <td class="px-5 py-4">
                    <span class="text-emerald-400 font-mono font-bold tracking-wider"><?= htmlspecialchars($t['token_number']) ?></span>
                </td>
                <td class="px-5 py-4 text-xs text-slate-300"><?= htmlspecialchars($t['user_email']) ?></td>
                <td class="px-5 py-4 text-xs text-slate-300"><?= ucwords(str_replace('-', ' ', htmlspecialchars($t['service_type']))) ?></td>
                <td class="px-5 py-4">
                    <?= statusBadge($t['status']) ?>
                </td>
                <td class="px-5 py-4 text-right flex items-center justify-end gap-2">
                    <button onclick='viewToken(<?= json_encode($t) ?>)' class="text-xs text-emerald-400 hover:text-emerald-300 hover:underline">View</button>
                    <button onclick='replyToken(<?= json_encode($t) ?>)' class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-2.5 py-1 rounded-lg hover:bg-emerald-600/40 transition">
                        <i class="fa-solid fa-reply mr-1"></i>Reply
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (count($tokens) === 0): ?>
            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500 text-sm">No service tokens found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-8">
    <?php for ($i = max(1, $page-3); $i <= min($totalPages, $page+3); $i++): ?>
        <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" class="<?= $i===$page ? 'btn-primary shadow-lg shadow-emerald-500/20 text-white' : 'btn-secondary text-slate-400' ?> px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- ===== View Token Modal ===== -->
<script>
function viewToken(token) {
    let detailsStr = '';
    try {
        let detailsObj = JSON.parse(token.details);
        for(let key in detailsObj) {
            if (key === 'ndaAgreed') continue;
            detailsStr += `<div class="flex justify-between py-1.5 border-b border-slate-700/40 last:border-0">
                <span class="text-slate-500 text-xs capitalize">${key.replace(/([A-Z])/g, ' $1')}</span>
                <span class="text-slate-200 text-xs font-medium text-right max-w-[60%]">${detailsObj[key] || '—'}</span>
            </div>`;
        }
    } catch(e) {
        detailsStr = `<p class="text-slate-300 text-sm">${token.details || 'No details available.'}</p>`;
    }

    const modalHTML = `
        <div id="token-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4" onclick="if(event.target===this) this.remove()">
            <div class="glass-card p-6 rounded-2xl shadow-2xl max-w-lg w-full border border-slate-700/50" style="background: linear-gradient(135deg, rgba(30,41,59,0.95), rgba(15,23,42,0.98)); backdrop-filter: blur(20px);">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-white tracking-widest">${token.token_number}</h3>
                        <p class="text-xs text-slate-500 mt-1">Created ${token.created_at}</p>
                    </div>
                    <button onclick="document.getElementById('token-modal').remove()" class="text-slate-400 hover:text-white transition p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div class="bg-slate-800/50 p-3 rounded-xl">
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Customer Email</p>
                        <p class="text-sm text-white truncate">${token.user_email}</p>
                    </div>
                    <div class="bg-slate-800/50 p-3 rounded-xl">
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Service Type</p>
                        <p class="text-sm text-emerald-400 capitalize">${token.service_type.replace(/-/g, ' ')}</p>
                    </div>
                </div>
                <div class="mb-5 p-4 bg-slate-800/40 rounded-xl max-h-60 overflow-y-auto custom-scrollbar">
                    <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-3">Request Details</p>
                    ${detailsStr}
                </div>
                <div class="flex gap-2">
                    <form method="POST" class="flex-1"><input type="hidden" name="token_id" value="${token.id}"><input type="hidden" name="new_status" value="in_progress"><input type="hidden" name="update_status" value="1">
                        <button type="submit" class="w-full bg-slate-800 hover:bg-blue-900/30 text-blue-400 border border-slate-700 hover:border-blue-500/50 py-2.5 rounded-xl text-xs transition font-medium">Mark In Progress</button>
                    </form>
                    <form method="POST" class="flex-1"><input type="hidden" name="token_id" value="${token.id}"><input type="hidden" name="new_status" value="completed"><input type="hidden" name="update_status" value="1">
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 py-2.5 rounded-xl text-xs text-white transition font-medium shadow-lg shadow-emerald-600/20">Mark Completed</button>
                    </form>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// ===== Reply Modal with Advanced Form =====
function replyToken(token) {
    const modalHTML = `
        <div id="reply-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4" onclick="if(event.target===this) this.remove()">
            <div class="glass-card p-6 rounded-2xl shadow-2xl max-w-2xl w-full border border-slate-700/50" style="background: linear-gradient(135deg, rgba(30,41,59,0.97), rgba(15,23,42,0.99)); backdrop-filter: blur(20px);">
                <div class="flex justify-between items-start mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-white"><i class="fa-solid fa-reply text-emerald-400 mr-2"></i>Reply to Customer</h3>
                        <p class="text-xs text-slate-500 mt-1">Token: <span class="text-emerald-400 font-mono">${token.token_number}</span> · To: <span class="text-slate-300">${token.user_email}</span></p>
                    </div>
                    <button onclick="document.getElementById('reply-modal').remove()" class="text-slate-400 hover:text-white transition p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>

                <form method="POST" action="service_token_reply.php" class="space-y-4">
                    <input type="hidden" name="token_id" value="${token.id}">
                    <input type="hidden" name="token_number" value="${token.token_number}">
                    <input type="hidden" name="customer_email" value="${token.user_email}">

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1.5 tracking-wider uppercase">Reply Subject / Category</label>
                        <select name="reply_subject" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm" onchange="updateTemplate(this.value)">
                            <option value="">— Select a reply category —</option>
                            <option value="quotation_service">Quotation / Service Requested — Reply</option>
                            <option value="components_requested">Components Requested — Reply</option>
                            <option value="career_request">Career Info Request — Reply</option>
                            <option value="custom">Custom Response</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1.5 tracking-wider uppercase">Email Subject Line</label>
                        <input type="text" name="email_subject" id="emailSubject" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="Re: Your Electava Service Request">
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1.5 tracking-wider uppercase">Reply Message</label>
                        <textarea name="reply_body" id="replyBody" required rows="10" class="input-field w-full px-4 py-3 rounded-xl text-sm leading-relaxed" placeholder="Type your response to the customer here..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1.5 tracking-wider uppercase">Set Token Status After Send</label>
                            <select name="post_status" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                                <option value="replied">Replied</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1.5 tracking-wider uppercase">CC (Optional)</label>
                            <input type="email" name="cc_email" class="input-field w-full px-3 py-2.5 rounded-xl text-sm" placeholder="cc@electava.com">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-800/50">
                        <button type="button" onclick="document.getElementById('reply-modal').remove()" class="btn-secondary px-5 py-2.5 rounded-xl text-sm text-slate-300">Cancel</button>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm text-white font-medium shadow-lg shadow-emerald-600/20">
                            <i class="fa-solid fa-paper-plane mr-2"></i>Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function updateTemplate(category) {
    const subject = document.getElementById('emailSubject');
    const body = document.getElementById('replyBody');

    const templates = {
        'quotation_service': {
            subject: 'Re: Your Electava Service Quotation Request',
            body: `Dear Customer,\n\nThank you for reaching out to Electava regarding your service request.\n\nAfter reviewing your requirements, we are pleased to provide the following details:\n\n• Estimated Timeline: [Insert Timeline]\n• Quotation Amount: ₹[Insert Amount]\n• Service Scope: [Insert Scope Summary]\n\nPlease review the above and let us know if you'd like to proceed or have any questions.\n\nBest regards,\nElectava Team\nwww.electava.com`
        },
        'components_requested': {
            subject: 'Re: Component Availability & Pricing — Electava',
            body: `Dear Customer,\n\nThank you for your interest in sourcing components through Electava.\n\nRegarding your component request:\n\n• Component(s): [Insert Component Names/Part Numbers]\n• Availability: [In Stock / Lead Time X weeks]\n• Unit Price: ₹[Insert Price]\n• MOQ: [Insert Minimum Order Quantity]\n\nWe can also provide datasheets and manufacturer alternatives if needed.\n\nBest regards,\nElectava Components Team\nwww.electava.com`
        },
        'career_request': {
            subject: 'Re: Career Inquiry — Electava',
            body: `Dear Applicant,\n\nThank you for your interest in joining the Electava team.\n\nWe have reviewed your inquiry and would like to inform you about the following:\n\n• Position: [Insert Role Title]\n• Status: [Open / Under Review / Filled]\n• Next Steps: [Interview Scheduled / Application Under Review / Not Available]\n\nPlease feel free to reach out if you have any further questions.\n\nBest regards,\nElectava HR Team\nwww.electava.com`
        },
        'custom': {
            subject: 'Electava — Response to Your Request',
            body: `Dear Customer,\n\n\n\nBest regards,\nElectava Team\nwww.electava.com`
        }
    };

    if (templates[category]) {
        subject.value = templates[category].subject;
        body.value = templates[category].body;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
