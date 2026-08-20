<?php
$pageTitle = 'Service Team Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole('service_team');

$uid = (int)$_SESSION['user_id'];

$openRequests = (int)$pdo->query("SELECT COUNT(*) FROM service_requests WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
$myRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status NOT IN ('completed','cancelled')");
$myRequestsStmt->execute([$uid]);
$myRequests = (int)$myRequestsStmt->fetchColumn();
$unassignedRequests = (int)$pdo->query("SELECT COUNT(*) FROM service_requests WHERE assigned_to IS NULL AND status NOT IN ('completed','cancelled')")->fetchColumn();
$quotePending = (int)$pdo->query("SELECT COUNT(*) FROM service_requests WHERE quoted_price IS NULL AND status IN ('new','reviewing')")->fetchColumn();

$openTokens = (int)$pdo->query("SELECT COUNT(*) FROM service_tokens WHERE status NOT IN ('completed','cancelled')")->fetchColumn();
$myTokensStmt = $pdo->prepare("SELECT COUNT(*) FROM service_tokens WHERE assigned_to = ? AND status NOT IN ('completed','cancelled')");
$myTokensStmt->execute([$uid]);
$myTokens = (int)$myTokensStmt->fetchColumn();
$unassignedTokens = (int)$pdo->query("SELECT COUNT(*) FROM service_tokens WHERE assigned_to IS NULL AND status NOT IN ('completed','cancelled')")->fetchColumn();
$repliedTokens = (int)$pdo->query("SELECT COUNT(*) FROM service_tokens WHERE status = 'replied'")->fetchColumn();

$recentRequests = $pdo->query("
    SELECT sr.*, e.full_name AS assignee_name
    FROM service_requests sr
    LEFT JOIN employees e ON e.id = sr.assigned_to
    ORDER BY sr.updated_at DESC, sr.created_at DESC
    LIMIT 6
")->fetchAll();

$recentTokens = $pdo->query("
    SELECT st.*, e.full_name AS assignee_name
    FROM service_tokens st
    LEFT JOIN employees e ON e.id = st.assigned_to
    ORDER BY st.updated_at DESC, st.created_at DESC
    LIMIT 6
")->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">Service Operations</h2>
    <p class="text-sm text-slate-500 mt-1">Take ownership of incoming service work, coordinate with customers or vendors, and keep verification notes visible for the full team.</p>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <a href="/service_team/requests.php?owner=mine" class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center"><i class="fa-solid fa-screwdriver-wrench text-cyan-400"></i></div>
            <span class="text-xs text-cyan-400 bg-cyan-500/10 px-2 py-0.5 rounded-full"><?= $openRequests ?> open</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $myRequests ?></div>
        <div class="text-xs text-slate-500 mt-0.5">My Active Requests</div>
    </a>
    <a href="/service_team/requests.php?owner=unassigned" class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center"><i class="fa-solid fa-inbox text-amber-400"></i></div>
            <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full"><?= $quotePending ?> quote pending</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $unassignedRequests ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Unassigned Requests</div>
    </a>
    <a href="/service_team/tokens.php?owner=mine" class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center"><i class="fa-solid fa-ticket text-emerald-400"></i></div>
            <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full"><?= $openTokens ?> active</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $myTokens ?></div>
        <div class="text-xs text-slate-500 mt-0.5">My Service Tokens</div>
    </a>
    <a href="/service_team/tokens.php?owner=unassigned" class="glass-card stat-glow p-5 rounded-2xl">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center"><i class="fa-solid fa-envelope-open-text text-purple-400"></i></div>
            <span class="text-xs text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded-full"><?= $repliedTokens ?> replied</span>
        </div>
        <div class="text-2xl font-bold text-white"><?= $unassignedTokens ?></div>
        <div class="text-xs text-slate-500 mt-0.5">Unassigned Tokens</div>
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Recent Service Requests</h3>
                <p class="text-xs text-slate-500 mt-1">Customer requirements, vendor coordination, and verification progress.</p>
            </div>
            <a href="/service_team/requests.php" class="text-xs text-emerald-400 hover:text-emerald-300">Open Queue</a>
        </div>
        <div class="space-y-3">
            <?php foreach ($recentRequests as $request): ?>
            <div class="rounded-xl border border-slate-700/50 bg-slate-900/20 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-white"><?= htmlspecialchars($request['title']) ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            #SR-<?= str_pad((string)$request['id'], 4, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($request['customer_name']) ?> · <?= htmlspecialchars($request['customer_email']) ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <?= statusBadge($request['status']) ?>
                        <div class="text-[11px] text-slate-600 mt-1"><?= timeAgo($request['updated_at']) ?></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-3 text-[11px] text-slate-400">
                    <span>Owner: <?= htmlspecialchars($request['assignee_name'] ?: 'Unassigned') ?></span>
                    <span>Priority: <?= ucfirst($request['priority']) ?></span>
                    <span>Quote: <?= $request['quoted_price'] !== null ? 'INR ' . number_format((float)$request['quoted_price'], 2) : 'Pending' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$recentRequests): ?>
            <div class="rounded-xl border border-dashed border-slate-700/60 p-8 text-center text-sm text-slate-500">No service requests yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Recent Service Tokens</h3>
                <p class="text-xs text-slate-500 mt-1">Marketplace inquiries ready for follow-up and reply.</p>
            </div>
            <a href="/service_team/tokens.php" class="text-xs text-emerald-400 hover:text-emerald-300">Open Tokens</a>
        </div>
        <div class="space-y-3">
            <?php foreach ($recentTokens as $token): ?>
            <div class="rounded-xl border border-slate-700/50 bg-slate-900/20 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-white"><?= htmlspecialchars($token['token_number']) ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            <?= htmlspecialchars($token['user_email'] ?: 'No email provided') ?> · <?= ucwords(str_replace(['_', '-'], ' ', (string)$token['service_type'])) ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <?= statusBadge($token['status']) ?>
                        <div class="text-[11px] text-slate-600 mt-1"><?= timeAgo($token['updated_at']) ?></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-3 text-[11px] text-slate-400">
                    <span>Owner: <?= htmlspecialchars($token['assignee_name'] ?: 'Unassigned') ?></span>
                    <span>Last Contact: <?= $token['last_contact_at'] ? date('d M Y H:i', strtotime($token['last_contact_at'])) : 'Not marked yet' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$recentTokens): ?>
            <div class="rounded-xl border border-dashed border-slate-700/60 p-8 text-center text-sm text-slate-500">No service tokens yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
