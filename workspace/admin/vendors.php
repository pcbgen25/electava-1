<?php
$pageTitle = 'Vendor Management';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$vendors = $pdo->query("SELECT v.*, u.username, u.email, u.full_name, u.status FROM vendors v JOIN users u ON v.user_id = u.id ORDER BY v.company_name")->fetchAll();
?>
<div class="mb-6"><p class="text-sm text-slate-500"><?= count($vendors) ?> registered vendors</p></div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($vendors as $v): ?>
    <div class="glass-card p-5 rounded-2xl">
        <div class="flex items-start justify-between mb-3">
            <div><h4 class="font-semibold text-white"><?= htmlspecialchars($v['company_name']) ?></h4><p class="text-xs text-slate-500"><?= htmlspecialchars($v['contact_person']??'') ?></p></div>
            <?= $v['is_approved'] ? '<span class="text-[10px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">Approved</span>' : '<span class="text-[10px] text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full">Pending</span>' ?>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-400 mb-3">
            <span><i class="fa-solid fa-envelope mr-1 text-slate-600"></i><?= htmlspecialchars($v['email']) ?></span>
        </div>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1">
                <?php for ($i=1;$i<=5;$i++): ?><i class="fa-solid fa-star text-[10px] <?= $i <= round($v['rating']??0) ? 'text-amber-400' : 'text-slate-700' ?>"></i><?php endfor; ?>
                <span class="text-xs text-slate-500 ml-1"><?= number_format($v['rating']??0,1) ?></span>
            </div>
            <span class="text-xs text-slate-500"><?= $v['payment_terms'] ?></span>
        </div>
        <?php if ($v['on_time_delivery_rate']): ?>
        <div class="mt-3 pt-3 border-t border-slate-800/40">
            <div class="flex justify-between text-xs mb-1"><span class="text-slate-500">On-time Delivery</span><span class="text-emerald-400"><?= $v['on_time_delivery_rate'] ?>%</span></div>
            <div class="h-1.5 bg-slate-800 rounded-full"><div class="h-full bg-emerald-500/50 rounded-full" style="width:<?= $v['on_time_delivery_rate'] ?>%"></div></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($vendors)): ?><div class="col-span-full glass-card p-8 rounded-2xl text-center"><p class="text-slate-500">No vendors registered yet</p></div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
