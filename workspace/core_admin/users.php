<?php
$pageTitle = 'Marketplace Users';
require_once __DIR__ . '/../includes/header.php';
requireRole(['core_admin', 'admin']);

// API endpoint for fetching user tokens on demand
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api_tokens_for_email'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['api_tokens_for_email']);
    $stmt = $pdo->prepare("SELECT * FROM service_tokens WHERE user_email = ? ORDER BY created_at DESC");
    $stmt->execute([$email]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reply_token') {
        requireCsrf();
        $tokenId = (int)$_POST['token_id'];
        $tokenNumber = trim($_POST['token_number']);
        $customerEmail = trim($_POST['customer_email']);
        $emailSubject = trim($_POST['email_subject']);
        $replyBody = trim($_POST['reply_body']);
        $postStatus = $_POST['post_status'] ?? 'replied';

        // Update token status
        $pdo->prepare("UPDATE service_tokens SET status = ? WHERE id = ?")->execute([$postStatus, $tokenId]);

        // Log audit
        $replyLog = json_encode([
            'subject' => $emailSubject,
            'to' => $customerEmail,
            'sent_by' => $_SESSION['full_name'] ?? $_SESSION['username'],
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        logAudit($pdo, 'reply_service_token', 'service_token', $tokenId, $replyLog);

        // Build mailto link params for redirect
        $mailtoSubject = rawurlencode($emailSubject);
        $mailtoBody = rawurlencode($replyBody);
        $mailtoLink = "mailto:$customerEmail?subject=$mailtoSubject&body=$mailtoBody";

        // Redirect to open mail composer — use json_encode to prevent XSS via mailto URL
        $safeMailtoLink = json_encode($mailtoLink, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo "<script>window.location.href = {$safeMailtoLink}; setTimeout(function(){ window.location.href = 'users.php?msg=reply_sent'; }, 1000);</script>";
        exit;
    }
}

// Fetch registered marketplace users
$usersQuery = "
    SELECT u.id, u.email as user_email, u.full_name, u.created_at as registered_at, u.last_login_at,
           COUNT(st.id) as total_requests,
           MIN(st.created_at) as first_request,
           MAX(st.created_at) as last_request,
           GROUP_CONCAT(DISTINCT st.service_type SEPARATOR ', ') as services
    FROM users u
    LEFT JOIN service_tokens st ON u.email = st.user_email
    WHERE u.role = 'marketplace_user' OR u.role = 'vendor'
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$userEmails = $pdo->query($usersQuery)->fetchAll();

// Add unregistered users who have made service requests
$unregisteredQuery = "
    SELECT NULL as id, st.user_email, 'Unregistered Visitor' as full_name, NULL as registered_at, NULL as last_login_at,
           COUNT(st.id) as total_requests,
           MIN(st.created_at) as first_request,
           MAX(st.created_at) as last_request,
           GROUP_CONCAT(DISTINCT st.service_type SEPARATOR ', ') as services
    FROM service_tokens st
    LEFT JOIN users u ON st.user_email = u.email
    WHERE u.id IS NULL AND st.user_email IS NOT NULL AND st.user_email != ''
    GROUP BY st.user_email
    ORDER BY last_request DESC
";
$unregisteredUsers = $pdo->query($unregisteredQuery)->fetchAll();

$userEmails = array_merge($userEmails, $unregisteredUsers);

// Search
$search = $_GET['search'] ?? '';
if ($search) {
    $userEmails = array_filter($userEmails, function($u) use ($search) {
        return stripos($u['user_email'], $search) !== false || 
               stripos($u['services'] ?? '', $search) !== false ||
               stripos($u['full_name'], $search) !== false;
    });
}

// Tracking stats
$totalVisitors = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM marketplace_tracking")->fetchColumn();
$totalPageViews = $pdo->query("SELECT COUNT(*) FROM marketplace_tracking")->fetchColumn();
?>

<?php if (isset($_GET['msg'])): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
    <i class="fa-solid fa-check-circle"></i>
    <?php if ($_GET['msg'] === 'reply_sent') echo 'Reply sent successfully. Your mail app should have opened with the composed message.'; ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Marketplace Users</h2>
    <p class="text-sm text-slate-500 mt-1">Track all marketplace visitors, quotation requests, and service orders. Click a user to view full details and reply.</p>
</div>

<!-- Overview Stats -->
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-user-group text-emerald-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= count($userEmails) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Active Users</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-ticket text-purple-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= $pdo->query("SELECT COUNT(*) FROM service_tokens")->fetchColumn() ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Total Requests</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center"><i class="fa-solid fa-globe text-blue-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= number_format($totalVisitors) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Unique Visitors</div>
    </div>
    <div class="glass-card stat-glow p-4 rounded-2xl">
        <div class="flex items-center justify-between mb-2">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-eye text-amber-400 text-sm"></i></div>
        </div>
        <div class="text-xl font-bold text-white"><?= number_format($totalPageViews) ?></div>
        <div class="text-[10px] text-slate-500 uppercase tracking-widest">Page Views</div>
    </div>
</div>

<!-- Search -->
<form method="GET" class="glass-card p-4 rounded-xl mb-6 flex items-center gap-3">
    <div class="relative flex-1">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by email or service type..." class="input-field w-full pl-9 pr-4 py-2 rounded-lg text-sm">
    </div>
    <button class="btn-primary px-4 py-2 rounded-lg text-xs text-white">Search</button>
    <a href="users.php" class="btn-secondary px-4 py-2 rounded-lg text-xs text-slate-400">Clear</a>
</form>

<!-- Users Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 shadow-2xl">
    <table class="w-full text-left text-sm">
        <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
            <tr>
                <th class="px-5 py-4 font-semibold">User</th>
                <th class="px-5 py-4 font-semibold">Requests</th>
                <th class="px-5 py-4 font-semibold">Services</th>
                <th class="px-5 py-4 font-semibold">Registered</th>
                <th class="px-5 py-4 font-semibold">Last Request</th>
                <th class="px-5 py-4 font-semibold text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
            <?php foreach ($userEmails as $ue): ?>
            <tr class="table-row cursor-pointer hover:bg-slate-800/30 transition-colors" onclick="openUserDetail('<?= htmlspecialchars($ue['user_email'], ENT_QUOTES) ?>')">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br <?= $ue['id'] ? 'from-emerald-500 to-teal-700' : 'from-purple-600 to-indigo-700' ?> flex items-center justify-center text-xs font-bold text-white shadow-lg">
                            <?= strtoupper(substr($ue['full_name'] ?? $ue['user_email'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-medium text-white"><?= htmlspecialchars($ue['full_name']) ?></div>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($ue['user_email']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-4">
                    <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2.5 py-0.5 rounded-full text-xs font-bold"><?= $ue['total_requests'] ?></span>
                </td>
                <td class="px-5 py-4 text-xs text-slate-300 max-w-[200px] truncate"><?= $ue['services'] ? htmlspecialchars(ucwords(str_replace('-', ' ', $ue['services']))) : '—' ?></td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= $ue['registered_at'] ? date('M j, Y', strtotime($ue['registered_at'])) : '<span class="text-slate-600">Guest</span>' ?></td>
                <td class="px-5 py-4 text-xs text-slate-400"><?= $ue['last_request'] ? date('M j, Y H:i', strtotime($ue['last_request'])) : '—' ?></td>
                <td class="px-5 py-4 text-right" onclick="event.stopPropagation()">
                    <button onclick="openUserDetail('<?= htmlspecialchars($ue['user_email'], ENT_QUOTES) ?>')" class="text-xs text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 hover:border-emerald-500/40 transition">
                        <i class="fa-solid fa-eye mr-1"></i>View Details
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($userEmails)): ?>
            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 text-sm">No marketplace users found yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===================================================== -->
<!-- User Detail Modal (fetches tokens via API) -->
<!-- ===================================================== -->
<script>
const csrfToken = <?= json_encode(generateCsrfToken()) ?>;

async function openUserDetail(email) {
    const existing = document.getElementById('userDetailModal');
    if (existing) existing.remove();

    // Fetch tokens
    const response = await fetch('users.php?api_tokens_for_email=' + encodeURIComponent(email));
    const tokens = await response.json();

    const totalRequests = tokens.length;
    const pendingCount = tokens.filter(t => t.status === 'pending').length;
    const repliedCount = tokens.filter(t => t.status === 'replied' || t.status === 'completed').length;
    
    // Build service types set
    const serviceTypes = [...new Set(tokens.map(t => t.service_type))];
    
    // Build tokens table
    let tokensHTML = '';
    tokens.forEach(t => {
        let details = '';
        try {
            const d = JSON.parse(t.details);
            for (let k in d) {
                if (k === 'ndaAgreed') continue;
                details += `<strong>${escHtml(k.replace(/([A-Z])/g, ' $1'))}:</strong> ${escHtml(d[k]) || '—'}<br>`;
            }
        } catch(e) { details = escHtml(t.details) || '—'; }

        const statusColors = {
            'pending': 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'in_progress': 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            'replied': 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
            'completed': 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'cancelled': 'bg-red-500/10 text-red-400 border-red-500/20',
        };
        const sc = statusColors[t.status] || 'bg-slate-500/10 text-slate-400 border-slate-500/20';

        tokensHTML += `
        <div class="bg-slate-800/40 rounded-xl p-4 border border-slate-700/40 hover:border-emerald-500/20 transition">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="text-emerald-400 font-mono font-bold text-sm tracking-wider">${escHtml(t.token_number)}</span>
                    <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-semibold border ${sc}">${escHtml(t.status.replace('_',' ').toUpperCase())}</span>
                </div>
                <span class="text-[10px] text-slate-500">${new Date(t.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'})}</span>
            </div>
            <div class="text-xs text-slate-300 mb-2">
                <span class="text-slate-500">Service:</span> <span class="text-white font-medium capitalize">${escHtml(t.service_type.replace(/-/g, ' '))}</span>
            </div>
            <div class="text-xs text-slate-400 mb-3 p-2.5 bg-slate-900/40 rounded-lg max-h-24 overflow-y-auto custom-scrollbar leading-relaxed">
                ${details}
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openReplyForm('${t.id}', '${escHtml(t.token_number)}', '${escHtml(email)}', '${escHtml(t.service_type)}')" class="text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-600/40 transition font-medium">
                    <i class="fa-solid fa-reply mr-1"></i>Reply & Send Mail
                </button>
                <a href="service_tokens.php?token=${encodeURIComponent(t.token_number)}" class="text-xs text-blue-400 hover:text-blue-300 hover:underline">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Open in Tokens
                </a>
            </div>
        </div>`;
    });

    const html = `
    <div id="userDetailModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4" onclick="if(event.target===this) this.remove()">
        <div class="w-full max-w-3xl max-h-[92vh] overflow-y-auto custom-scrollbar" style="background: linear-gradient(135deg, rgba(30,41,59,0.97), rgba(15,23,42,0.99)); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); border-radius: 1.25rem; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
            
            <!-- Header -->
            <div class="p-6 border-b border-slate-800/60">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-xl font-bold text-white shadow-xl shadow-purple-500/20">
                            ${escHtml(email).charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">${escHtml(email)}</h2>
                            <p class="text-sm text-slate-400 mt-0.5">Marketplace Customer</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/15 text-purple-400 border border-purple-500/25">${totalRequests} Request${totalRequests !== 1 ? 's' : ''}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/25">${pendingCount} Pending</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/25">${repliedCount} Replied</span>
                            </div>
                        </div>
                    </div>
                    <button onclick="document.getElementById('userDetailModal').remove()" class="text-slate-400 hover:text-white transition p-1"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="p-6 border-b border-slate-800/60">
                <h3 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">User Summary</h3>
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-purple-400">${totalRequests}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Total Orders</div>
                    </div>
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-cyan-400">${serviceTypes.length}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Service Types</div>
                    </div>
                    <div class="bg-slate-800/50 p-3.5 rounded-xl text-center">
                        <div class="text-lg font-bold text-slate-300">${escHtml(serviceTypes.map(s => s.replace(/-/g,' ')).join(', ')) || '—'}</div>
                        <div class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5">Services Used</div>
                    </div>
                </div>
            </div>

            <!-- Orders/Tokens List -->
            <div class="p-6">
                <h3 class="text-xs text-slate-500 uppercase tracking-widest font-semibold mb-4">Service Requests & Orders</h3>
                <div class="space-y-3">
                    ${tokensHTML || '<div class="text-center text-slate-500 text-sm py-8">No service requests found for this user.</div>'}
                </div>
            </div>

        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);
}

// Reply form that opens inline, then submits & opens mail composer
function openReplyForm(tokenId, tokenNumber, customerEmail, serviceType) {
    const existing = document.getElementById('replyFormModal');
    if (existing) existing.remove();

    const templates = {
        'analysis-quotation': { subject: 'Re: Analysis Quotation Request — Electava', body: `Dear Customer,\n\nThank you for your analysis quotation request (Token: ${tokenNumber}).\n\nAfter reviewing your requirements, here are our findings:\n\n• Estimated Timeline: [Insert Timeline]\n• Quotation Amount: ₹[Insert Amount]\n• Analysis Scope: [Insert Scope]\n\nPlease let us know if you'd like to proceed.\n\nBest regards,\nElectava Team` },
        'pcb-design': { subject: 'Re: PCB Design Service — Electava', body: `Dear Customer,\n\nThank you for your PCB design request (Token: ${tokenNumber}).\n\n• Board Complexity: [Insert]\n• Layers: [Insert]\n• Estimated Cost: ₹[Insert]\n• Timeline: [Insert weeks]\n\nBest regards,\nElectava Design Team` },
        'component-sourcing': { subject: 'Re: Component Sourcing — Electava', body: `Dear Customer,\n\nRegarding your component sourcing request (Token: ${tokenNumber}):\n\n• Components: [Insert Part Numbers]\n• Availability: [In Stock / Lead Time]\n• Unit Price: ₹[Insert]\n\nBest regards,\nElectava Components Team` },
    };

    const tpl = templates[serviceType] || { subject: `Re: Service Request ${tokenNumber} — Electava`, body: `Dear Customer,\n\nThank you for your request (Token: ${tokenNumber}).\n\n[Your response here]\n\nBest regards,\nElectava Team` };

    const html = `
    <div id="replyFormModal" class="fixed inset-0 bg-slate-900/85 backdrop-blur-sm flex items-center justify-center z-[60] p-4" onclick="if(event.target===this) this.remove()">
        <div class="w-full max-w-2xl" style="background: linear-gradient(135deg, rgba(30,41,59,0.98), rgba(15,23,42,0.99)); backdrop-filter: blur(20px); border: 1px solid rgba(16,185,129,0.15); border-radius: 1.25rem; box-shadow: 0 25px 50px rgba(0,0,0,0.6);">
            <div class="p-6 border-b border-slate-800/60">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-white"><i class="fa-solid fa-paper-plane text-emerald-400 mr-2"></i>Reply & Compose Mail</h3>
                        <p class="text-xs text-slate-500 mt-1">Token: <span class="text-emerald-400 font-mono">${tokenNumber}</span> · To: <span class="text-slate-300">${customerEmail}</span></p>
                    </div>
                    <button onclick="document.getElementById('replyFormModal').remove()" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="action" value="reply_token">
                <input type="hidden" name="token_id" value="${escHtml(tokenId)}">
                <input type="hidden" name="token_number" value="${escHtml(tokenNumber)}">
                <input type="hidden" name="customer_email" value="${escHtml(customerEmail)}">
                
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1.5 uppercase tracking-widest">Email Subject</label>
                    <input type="text" name="email_subject" value="${escHtml(tpl.subject)}" required class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-[10px] text-slate-500 mb-1.5 uppercase tracking-widest">Reply Message</label>
                    <textarea name="reply_body" required rows="10" class="input-field w-full px-4 py-3 rounded-xl text-sm leading-relaxed">${tpl.body}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-slate-500 mb-1.5 uppercase tracking-widest">Set Status After Send</label>
                        <select name="post_status" class="input-field w-full px-3 py-2.5 rounded-xl text-sm">
                            <option value="replied">Replied</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <div class="bg-blue-500/10 border border-blue-500/20 p-3 rounded-xl text-xs text-blue-400 flex items-center gap-2 w-full">
                            <i class="fa-solid fa-envelope text-sm"></i>
                            <span>Your mail app will open after submitting</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t border-slate-800/50">
                    <button type="button" onclick="document.getElementById('replyFormModal').remove()" class="btn-secondary px-5 py-2.5 rounded-xl text-sm text-slate-300">Cancel</button>
                    <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm text-white font-medium shadow-lg shadow-emerald-600/20">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Submit & Open Mail
                    </button>
                </div>
            </form>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
