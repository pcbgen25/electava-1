<?php
$pageTitle = 'My Login Times';
require_once __DIR__ . '/../includes/header.php';
requireRole('employee');

$uid = (int) $_SESSION['user_id'];
$detailDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['detail_date'] ?? '') ? $_GET['detail_date'] : date('Y-m-d');
$detailMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['detail_month'] ?? '') ? $_GET['detail_month'] : date('Y-m', strtotime($detailDate));

function employeeFormatDurationLabel(int $seconds): string
{
    if ($seconds <= 0) {
        return '0m';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($hours > 0 && $minutes > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    if ($hours > 0) {
        return $hours . 'h';
    }
    return $minutes . 'm';
}

function employeeFormatHoursLabel(int $seconds): string
{
    return number_format($seconds / 3600, 1) . 'h';
}

function employeeFormatTimeValue(?string $datetime): string
{
    return $datetime ? date('h:i A', strtotime($datetime)) : '--';
}

function employeeLoginLocationLabel(?string $ipAddress): string
{
    $ipAddress = trim((string) $ipAddress);
    if ($ipAddress === '' || $ipAddress === '-') {
        return 'Unknown';
    }
    if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        return 'Localhost';
    }
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ipAddress)) {
        return 'Private Network';
    }
    return 'Remote Network';
}

function employeeAttendanceBadge(string $state): string
{
    $map = [
        'Present' => ['emerald', 'Present'],
        'In Progress' => ['blue', 'In Progress'],
        'Partial' => ['amber', 'Partial'],
        'Failed Only' => ['red', 'Failed Only'],
        'No Activity' => ['slate', 'No Activity'],
    ];

    [$color, $label] = $map[$state] ?? ['slate', $state];

    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-' . $color . '-500/10 text-' . $color . '-400 border border-' . $color . '-500/20">' . htmlspecialchars($label) . '</span>';
}

function employeeSourceSummary(array $sources): string
{
    if (empty($sources)) {
        return '--';
    }

    $labels = [];
    foreach ($sources as $source) {
        $location = employeeLoginLocationLabel($source['ip_address'] ?? null);
        $device = trim((string) ($source['device_type'] ?? '')) ?: 'Device';
        $browser = trim((string) ($source['browser'] ?? '')) ?: 'Browser';
        $labels[] = $location . ' / ' . $device . ' / ' . $browser;
    }

    $labels = array_values(array_unique($labels));
    $visible = array_slice($labels, 0, 2);
    $text = implode(' | ', $visible);

    if (count($labels) > 2) {
        $text .= ' +' . (count($labels) - 2);
    }

    return $text !== '' ? $text : '--';
}

function employeeEmptyDaySummary(string $dayKey): array
{
    return [
        'day_key' => $dayKey,
        'successful_logins' => 0,
        'failed_logins' => 0,
        'sessions' => [],
        'sources' => [],
        'approved_count' => 0,
        'rejected_count' => 0,
        'first_in' => null,
        'last_out' => null,
        'work_seconds' => 0,
        'break_seconds' => 0,
        'break_count' => 0,
        'overtime_seconds' => 0,
        'status' => 'No Activity',
        'where_login' => '--',
    ];
}

$profileStmt = $pdo->prepare("
    SELECT e.*, d.name AS primary_domain_name
    FROM employees e
    LEFT JOIN domains d ON d.id = e.domain_id
    WHERE e.id = ?
    LIMIT 1
");
$profileStmt->execute([$uid]);
$employee = $profileStmt->fetch();

$monthStart = date('Y-m-01', strtotime($detailMonth . '-01'));
$monthEnd = date('Y-m-t', strtotime($monthStart));
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($detailDate)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($detailDate)));

$rangeStart = date('Y-m-d', min(strtotime($monthStart), strtotime($weekStart), strtotime($detailDate)));
$rangeEnd = date('Y-m-d', max(strtotime($monthEnd), strtotime($weekEnd), strtotime($detailDate)));
$queryRangeStart = $rangeStart . ' 00:00:00';
$queryRangeEnd = date('Y-m-d 23:59:59', strtotime($rangeEnd));
$logoutRangeEnd = date('Y-m-d 23:59:59', strtotime($rangeEnd . ' +1 day'));

$loginStmt = $pdo->prepare("
    SELECT created_at, ip_address, device_type, browser
    FROM login_logs
    WHERE user_id = ? AND status = 'success' AND created_at BETWEEN ? AND ?
    ORDER BY created_at ASC
");
$loginStmt->execute([$uid, $queryRangeStart, $queryRangeEnd]);
$loginEvents = $loginStmt->fetchAll();

$logoutStmt = $pdo->prepare("
    SELECT created_at
    FROM audit_logs
    WHERE user_id = ? AND action = 'logout' AND created_at BETWEEN ? AND ?
    ORDER BY created_at ASC
");
$logoutStmt->execute([$uid, $queryRangeStart, $logoutRangeEnd]);
$logoutEvents = $logoutStmt->fetchAll();

$failedStmt = $pdo->prepare("
    SELECT DATE(created_at) AS day_key, COUNT(*) AS failed_count
    FROM login_logs
    WHERE user_id = ? AND status = 'failed' AND created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
");
$failedStmt->execute([$uid, $queryRangeStart, $queryRangeEnd]);
$failedMap = [];
foreach ($failedStmt->fetchAll() as $failedRow) {
    $failedMap[$failedRow['day_key']] = (int) $failedRow['failed_count'];
}

$approvalDailyStmt = $pdo->prepare("
    SELECT
        DATE(ta.approved_at) AS day_key,
        SUM(CASE WHEN ta.action = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN ta.action = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
    FROM task_approvals ta
    INNER JOIN tasks t ON t.id = ta.task_id
    WHERE (t.assigned_to = ? OR t.created_by = ?) AND ta.approved_at BETWEEN ? AND ?
    GROUP BY DATE(ta.approved_at)
");
$approvalDailyStmt->execute([$uid, $uid, $queryRangeStart, $queryRangeEnd]);
$approvalMap = [];
foreach ($approvalDailyStmt->fetchAll() as $approvalRow) {
    $approvalMap[$approvalRow['day_key']] = [
        'approved_count' => (int) $approvalRow['approved_count'],
        'rejected_count' => (int) $approvalRow['rejected_count'],
    ];
}

$dayActivity = [];
$logoutIndex = 0;
$todayKey = date('Y-m-d');

foreach ($loginEvents as $index => $loginEvent) {
    $loginAt = strtotime($loginEvent['created_at']);
    $dayKey = date('Y-m-d', $loginAt);
    $nextLoginAt = isset($loginEvents[$index + 1]) ? strtotime($loginEvents[$index + 1]['created_at']) : null;

    if (!isset($dayActivity[$dayKey])) {
        $dayActivity[$dayKey] = employeeEmptyDaySummary($dayKey);
    }

    $dayActivity[$dayKey]['successful_logins']++;
    $dayActivity[$dayKey]['sources'][] = $loginEvent;

    while ($logoutIndex < count($logoutEvents) && strtotime($logoutEvents[$logoutIndex]['created_at']) < $loginAt) {
        $logoutIndex++;
    }

    $matchedLogout = null;
    if ($logoutIndex < count($logoutEvents)) {
        $candidateLogoutAt = strtotime($logoutEvents[$logoutIndex]['created_at']);
        if ($candidateLogoutAt >= $loginAt && ($nextLoginAt === null || $candidateLogoutAt <= $nextLoginAt)) {
            $matchedLogout = $logoutEvents[$logoutIndex]['created_at'];
            $logoutIndex++;
        }
    }

    if ($matchedLogout === null && $nextLoginAt !== null && date('Y-m-d', $nextLoginAt) === $dayKey) {
        $matchedLogout = $loginEvents[$index + 1]['created_at'];
    }

    $dayActivity[$dayKey]['sessions'][] = [
        'start' => $loginEvent['created_at'],
        'end' => $matchedLogout,
        'ip_address' => $loginEvent['ip_address'] ?? null,
        'device_type' => $loginEvent['device_type'] ?? null,
        'browser' => $loginEvent['browser'] ?? null,
    ];
}

$allDaySummaries = [];
for ($cursor = strtotime($rangeStart); $cursor <= strtotime($rangeEnd); $cursor = strtotime('+1 day', $cursor)) {
    $dayKey = date('Y-m-d', $cursor);
    $summary = $dayActivity[$dayKey] ?? employeeEmptyDaySummary($dayKey);
    $summary['failed_logins'] = $failedMap[$dayKey] ?? 0;
    $summary['approved_count'] = $approvalMap[$dayKey]['approved_count'] ?? 0;
    $summary['rejected_count'] = $approvalMap[$dayKey]['rejected_count'] ?? 0;

    if (!empty($summary['sessions'])) {
        usort($summary['sessions'], fn($a, $b) => strcmp($a['start'], $b['start']));
        $summary['first_in'] = $summary['sessions'][0]['start'];
        $lastSession = end($summary['sessions']);
        $summary['last_out'] = $lastSession['end'] ?? null;

        $workSeconds = 0;
        $breakSeconds = 0;
        $breakCount = 0;
        $missingEnd = false;

        foreach ($summary['sessions'] as $sessionIndex => $session) {
            if (!empty($session['end'])) {
                $workSeconds += max(0, strtotime($session['end']) - strtotime($session['start']));
            } else {
                $missingEnd = true;
            }

            if (isset($summary['sessions'][$sessionIndex + 1])) {
                $currentEnd = !empty($session['end']) ? strtotime($session['end']) : strtotime($session['start']);
                $nextStart = strtotime($summary['sessions'][$sessionIndex + 1]['start']);
                if ($nextStart > $currentEnd) {
                    $breakSeconds += ($nextStart - $currentEnd);
                    $breakCount++;
                }
            }
        }

        $summary['work_seconds'] = $workSeconds;
        $summary['break_seconds'] = $breakSeconds;
        $summary['break_count'] = $breakCount;
        $summary['overtime_seconds'] = max(0, $workSeconds - (8 * 3600));
        $summary['where_login'] = employeeSourceSummary($summary['sources']);
        $summary['status'] = $missingEnd ? ($dayKey === $todayKey ? 'In Progress' : 'Partial') : 'Present';
    } elseif ($summary['failed_logins'] > 0) {
        $summary['status'] = 'Failed Only';
    }

    $allDaySummaries[$dayKey] = $summary;
}

$monthRows = [];
for ($cursor = strtotime($monthStart); $cursor <= strtotime($monthEnd); $cursor = strtotime('+1 day', $cursor)) {
    $monthRows[] = $allDaySummaries[date('Y-m-d', $cursor)] ?? employeeEmptyDaySummary(date('Y-m-d', $cursor));
}

$daySummary = $allDaySummaries[$detailDate] ?? employeeEmptyDaySummary($detailDate);

$sumRange = function (string $startDate, string $endDate, string $metric) use ($allDaySummaries): int {
    $total = 0;
    for ($cursor = strtotime($startDate); $cursor <= strtotime($endDate); $cursor = strtotime('+1 day', $cursor)) {
        $dayKey = date('Y-m-d', $cursor);
        $total += (int) ($allDaySummaries[$dayKey][$metric] ?? 0);
    }
    return $total;
};

$detailMetrics = [
    'day_hours' => (int) ($daySummary['work_seconds'] ?? 0),
    'week_hours' => $sumRange($weekStart, $weekEnd, 'work_seconds'),
    'month_hours' => $sumRange($monthStart, $monthEnd, 'work_seconds'),
    'day_break_seconds' => (int) ($daySummary['break_seconds'] ?? 0),
    'day_break_count' => (int) ($daySummary['break_count'] ?? 0),
    'day_logins' => (int) ($daySummary['successful_logins'] ?? 0),
    'month_approvals' => $sumRange($monthStart, $monthEnd, 'approved_count') + $sumRange($monthStart, $monthEnd, 'rejected_count'),
];

$timelineStmt = $pdo->prepare("
    SELECT created_at, 'login' AS event_type, status, device_type, browser, ip_address
    FROM login_logs
    WHERE user_id = ? AND DATE(created_at) = ?
    UNION ALL
    SELECT created_at, 'logout' AS event_type, 'success' AS status, NULL AS device_type, NULL AS browser, ip_address
    FROM audit_logs
    WHERE user_id = ? AND action = 'logout' AND DATE(created_at) = ?
    ORDER BY created_at DESC
");
$timelineStmt->execute([$uid, $detailDate, $uid, $detailDate]);
$selectedDayTimeline = $timelineStmt->fetchAll();

$approvalLogStmt = $pdo->prepare("
    SELECT
        ta.action,
        ta.comments,
        ta.approved_at,
        t.title,
        COALESCE(NULLIF(approver.full_name, ''), approver.username, 'Manager') AS approver_name
    FROM task_approvals ta
    INNER JOIN tasks t ON t.id = ta.task_id
    LEFT JOIN employees approver ON approver.id = ta.approved_by
    WHERE (t.assigned_to = ? OR t.created_by = ?)
      AND ta.approved_at BETWEEN ? AND ?
    ORDER BY ta.approved_at DESC
    LIMIT 12
");
$approvalLogStmt->execute([$uid, $uid, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
$approvalLog = $approvalLogStmt->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-white tracking-tight">My Login Times</h2>
    <p class="text-sm text-slate-500 mt-1">Review your daily login activity, work duration, and logout timeline. Core Admin keeps the master control and full monitoring access.</p>
</div>

<div class="glass-card rounded-2xl p-4 mb-6 border border-cyan-500/20 bg-cyan-500/5">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-user-shield text-cyan-300"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-white">Read-only employee view</h3>
            <p class="text-sm text-slate-400 mt-1">This page is for your own verification. Core Admin still controls employee attendance review, account management, and full login oversight.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Day Status</div>
        <div class="mt-3"><?= employeeAttendanceBadge($daySummary['status']) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Day Hours</div>
        <div class="text-2xl font-bold text-white mt-2"><?= employeeFormatHoursLabel($detailMetrics['day_hours']) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Week Hours</div>
        <div class="text-2xl font-bold text-white mt-2"><?= employeeFormatHoursLabel($detailMetrics['week_hours']) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Month Hours</div>
        <div class="text-2xl font-bold text-white mt-2"><?= employeeFormatHoursLabel($detailMetrics['month_hours']) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Day Breaks</div>
        <div class="text-2xl font-bold text-white mt-2"><?= (int) $detailMetrics['day_break_count'] ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= employeeFormatDurationLabel($detailMetrics['day_break_seconds']) ?></div>
    </div>
    <div class="glass-card p-4 rounded-2xl">
        <div class="text-[11px] uppercase tracking-wider text-slate-500">Month Approvals</div>
        <div class="text-2xl font-bold text-white mt-2"><?= number_format($detailMetrics['month_approvals']) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[340px,minmax(0,1fr)] gap-6 mb-6">
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white mb-4">Employee Overview</h3>
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Name</span>
                <span class="text-white text-right"><?= htmlspecialchars($employee['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username']) ?></span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Role</span>
                <span class="text-white text-right capitalize"><?= htmlspecialchars(str_replace('_', ' ', $employee['role'] ?? 'employee')) ?></span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Primary Domain</span>
                <span class="text-white text-right"><?= htmlspecialchars($employee['primary_domain_name'] ?? 'Not assigned') ?></span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Last Login</span>
                <span class="text-white text-right"><?= !empty($employee['last_login_at']) ? date('d M Y h:i A', strtotime($employee['last_login_at'])) : 'Not recorded' ?></span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Selected Day Logins</span>
                <span class="text-white text-right"><?= number_format($detailMetrics['day_logins']) ?></span>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 rounded-2xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-white">Daily Verification Filters</h3>
                <p class="text-sm text-slate-500 mt-1">Choose a day and month to verify your attendance-style login activity.</p>
            </div>
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Detail Date</label>
                    <input type="date" name="detail_date" value="<?= htmlspecialchars($detailDate) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 uppercase tracking-wider">Detail Month</label>
                    <input type="month" name="detail_month" value="<?= htmlspecialchars($detailMonth) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm text-white font-medium w-full">Apply</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-slate-800/70 bg-slate-900/35 p-4">
                <div class="text-[11px] uppercase tracking-wider text-slate-500">First In</div>
                <div class="text-lg font-semibold text-white mt-2"><?= employeeFormatTimeValue($daySummary['first_in']) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800/70 bg-slate-900/35 p-4">
                <div class="text-[11px] uppercase tracking-wider text-slate-500">Last Out</div>
                <div class="text-lg font-semibold text-white mt-2"><?= employeeFormatTimeValue($daySummary['last_out']) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800/70 bg-slate-900/35 p-4">
                <div class="text-[11px] uppercase tracking-wider text-slate-500">Where Login</div>
                <div class="text-sm font-medium text-white mt-2"><?= htmlspecialchars($daySummary['where_login']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="glass-card rounded-2xl overflow-hidden border border-slate-700/50 mb-6">
    <div class="px-5 py-4 border-b border-slate-800/60">
        <h3 class="text-sm font-semibold text-white">Day-wise Login Detail</h3>
        <p class="text-xs text-slate-500 mt-1">Work duration and break values are calculated from successful login activity and recorded logout events.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-slate-400 uppercase tracking-wider bg-slate-900/80 border-b border-slate-800">
                <tr>
                    <th class="px-5 py-4 font-semibold">Date</th>
                    <th class="px-5 py-4 font-semibold">Status</th>
                    <th class="px-5 py-4 font-semibold">In Time</th>
                    <th class="px-5 py-4 font-semibold">Out Time</th>
                    <th class="px-5 py-4 font-semibold">Work Duration</th>
                    <th class="px-5 py-4 font-semibold">Break Time</th>
                    <th class="px-5 py-4 font-semibold">No. of Breaks</th>
                    <th class="px-5 py-4 font-semibold">Where Login</th>
                    <th class="px-5 py-4 font-semibold">Approvals</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 bg-slate-900/20">
                <?php foreach (array_reverse($monthRows) as $row): ?>
                <tr class="table-row">
                    <td class="px-5 py-4">
                        <a href="?detail_date=<?= urlencode($row['day_key']) ?>&detail_month=<?= urlencode($detailMonth) ?>" class="text-white hover:text-emerald-300 transition">
                            <?= htmlspecialchars(date('d M Y', strtotime($row['day_key']))) ?>
                        </a>
                    </td>
                    <td class="px-5 py-4"><?= employeeAttendanceBadge($row['status']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= employeeFormatTimeValue($row['first_in']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= employeeFormatTimeValue($row['last_out']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= employeeFormatDurationLabel((int) $row['work_seconds']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= employeeFormatDurationLabel((int) $row['break_seconds']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= number_format((int) $row['break_count']) ?></td>
                    <td class="px-5 py-4 text-slate-400 text-xs max-w-xs"><?= htmlspecialchars($row['where_login']) ?></td>
                    <td class="px-5 py-4 text-slate-300"><?= number_format((int) $row['approved_count'] + (int) $row['rejected_count']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr),360px] gap-6">
    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white">Selected Day Timeline</h3>
        <p class="text-xs text-slate-500 mt-1">Exact login, failed login, and logout events for <?= htmlspecialchars(date('d M Y', strtotime($detailDate))) ?>.</p>
        <div class="mt-5 space-y-3">
            <?php foreach ($selectedDayTimeline as $timelineRow): ?>
            <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-800/70 bg-slate-900/30">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $timelineRow['event_type'] === 'logout' ? 'bg-amber-500/10' : (($timelineRow['status'] ?? '') === 'failed' ? 'bg-red-500/10' : 'bg-emerald-500/10') ?>">
                    <i class="fa-solid <?= $timelineRow['event_type'] === 'logout' ? 'fa-right-from-bracket text-amber-400' : (($timelineRow['status'] ?? '') === 'failed' ? 'fa-circle-exclamation text-red-400' : 'fa-right-to-bracket text-emerald-400') ?> text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-white capitalize"><?= htmlspecialchars($timelineRow['event_type']) ?></div>
                    <div class="text-xs text-slate-500 mt-1">
                        <?= htmlspecialchars(date('d M Y h:i A', strtotime($timelineRow['created_at']))) ?>
                        <?php if (!empty($timelineRow['device_type']) || !empty($timelineRow['browser'])): ?>
                            · <?= htmlspecialchars(trim(($timelineRow['device_type'] ?? '') . ' / ' . ($timelineRow['browser'] ?? ''), ' /')) ?>
                        <?php endif; ?>
                        <?php if (!empty($timelineRow['ip_address'])): ?>
                            · <?= htmlspecialchars(employeeLoginLocationLabel($timelineRow['ip_address'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($selectedDayTimeline)): ?>
            <p class="text-sm text-slate-500 py-4">No login or logout activity for the selected day.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="glass-card p-5 rounded-2xl">
        <h3 class="text-sm font-semibold text-white">Approval Activity</h3>
        <p class="text-xs text-slate-500 mt-1">Recent task approval actions related to your assigned or submitted work this month.</p>
        <div class="mt-5 space-y-3">
            <?php foreach ($approvalLog as $approval): ?>
            <div class="p-3 rounded-xl border border-slate-800/70 bg-slate-900/30">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-medium text-white"><?= htmlspecialchars($approval['title']) ?></div>
                    <div><?= statusBadge($approval['action']) ?></div>
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    <?= htmlspecialchars($approval['approver_name']) ?> · <?= htmlspecialchars(date('d M Y h:i A', strtotime($approval['approved_at']))) ?>
                </div>
                <?php if (!empty($approval['comments'])): ?>
                <div class="text-xs text-slate-400 mt-2"><?= htmlspecialchars($approval['comments']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($approvalLog)): ?>
            <p class="text-sm text-slate-500 py-4">No approval activity recorded for this month.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
