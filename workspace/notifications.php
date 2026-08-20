<?php
$pageTitle = 'Notifications';
require_once 'includes/header.php';

$uid = $_SESSION['user_id'];

// Mark as read if requested
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$_GET['mark_read'], $uid]);
    header("Location: /notifications.php");
    exit;
}
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    header("Location: /notifications.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-white">Notifications</h2>
        <p class="text-sm text-slate-500 mt-1"><?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?> unread</p>
    </div>
    <a href="?mark_all=1" class="btn-secondary px-4 py-2 rounded-lg text-sm text-slate-300">
        <i class="fa-solid fa-check-double mr-1"></i>Mark All Read
    </a>
</div>

<div class="space-y-2">
    <?php if (empty($notifications)): ?>
        <div class="glass-card p-12 rounded-2xl text-center">
            <i class="fa-regular fa-bell-slash text-4xl text-slate-600 mb-4"></i>
            <p class="text-slate-500">No notifications yet</p>
        </div>
    <?php endif; ?>
    <?php foreach ($notifications as $n): ?>
        <div class="glass-card p-4 rounded-xl flex items-start gap-4 <?= $n['is_read'] ? 'opacity-60' : '' ?>">
            <?php
                $iconMap = ['info'=>'fa-info-circle text-blue-400','success'=>'fa-check-circle text-emerald-400','warning'=>'fa-exclamation-triangle text-amber-400','error'=>'fa-times-circle text-red-400','task'=>'fa-list-check text-purple-400','approval'=>'fa-clipboard-check text-teal-400'];
                $icon = $iconMap[$n['type']] ?? 'fa-bell text-slate-400';
            ?>
            <div class="w-10 h-10 rounded-lg bg-slate-800/50 flex items-center justify-center shrink-0">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white"><?= htmlspecialchars($n['title']) ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($n['message'] ?? '') ?></p>
                <span class="text-[11px] text-slate-600 mt-1 block"><?= timeAgo($n['created_at']) ?></span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <?php if ($n['link']): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>" class="text-xs text-emerald-400 hover:text-emerald-300">View</a>
                <?php endif; ?>
                <?php if (!$n['is_read']): ?>
                    <a href="?mark_read=<?= $n['id'] ?>" class="text-xs text-slate-500 hover:text-slate-300"><i class="fa-solid fa-check"></i></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
