<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'] ?? $username;
$notifCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);

// Current page detection
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Electava Workspace</title>
    <meta name="description" content="Electava Workspace - Internal Management Platform">
    <script>
        (function () {
            var theme = 'dark';
            try {
                var savedTheme = localStorage.getItem('workspace-theme');
                if (savedTheme === 'light' || savedTheme === 'dark') {
                    theme = savedTheme;
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    theme = 'light';
                }
            } catch (error) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --workspace-bg: #0a0f1a;
            --workspace-main-bg: radial-gradient(circle at top, rgba(15, 23, 42, 0.96), rgba(10, 15, 26, 1) 55%);
            --workspace-panel-bg: rgba(15, 23, 42, 0.8);
            --workspace-card-start: rgba(30, 41, 59, 0.7);
            --workspace-card-end: rgba(15, 23, 42, 0.9);
            --workspace-border: rgba(255, 255, 255, 0.06);
            --workspace-border-strong: rgba(30, 41, 59, 0.7);
            --workspace-text: #f8fafc;
            --workspace-text-soft: #cbd5e1;
            --workspace-muted: #64748b;
            --workspace-muted-soft: #475569;
            --workspace-secondary-bg: rgba(30, 41, 59, 0.8);
            --workspace-secondary-hover: rgba(51, 65, 85, 0.8);
            --workspace-input-bg: rgba(15, 23, 42, 0.6);
            --workspace-input-border: rgba(255, 255, 255, 0.1);
            --workspace-scrollbar: rgba(52, 211, 153, 0.15);
            --workspace-scrollbar-hover: rgba(52, 211, 153, 0.3);
            --workspace-shadow: rgba(16, 185, 129, 0.08);
            --workspace-modal: rgba(0, 0, 0, 0.7);
            --workspace-select-arrow: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        }

        :root[data-theme="light"] {
            --workspace-bg: #e9f1f8;
            --workspace-main-bg: radial-gradient(circle at top, rgba(255, 255, 255, 0.98), rgba(232, 240, 249, 1) 58%);
            --workspace-panel-bg: rgba(255, 255, 255, 0.82);
            --workspace-card-start: rgba(255, 255, 255, 0.94);
            --workspace-card-end: rgba(241, 245, 249, 0.96);
            --workspace-border: rgba(148, 163, 184, 0.28);
            --workspace-border-strong: rgba(148, 163, 184, 0.22);
            --workspace-text: #0f172a;
            --workspace-text-soft: #1e293b;
            --workspace-muted: #475569;
            --workspace-muted-soft: #94a3b8;
            --workspace-secondary-bg: rgba(255, 255, 255, 0.88);
            --workspace-secondary-hover: rgba(241, 245, 249, 0.95);
            --workspace-input-bg: rgba(255, 255, 255, 0.96);
            --workspace-input-border: rgba(148, 163, 184, 0.42);
            --workspace-scrollbar: rgba(71, 85, 105, 0.22);
            --workspace-scrollbar-hover: rgba(71, 85, 105, 0.36);
            --workspace-shadow: rgba(15, 23, 42, 0.08);
            --workspace-modal: rgba(148, 163, 184, 0.45);
            --workspace-select-arrow: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23334155' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--workspace-main-bg); color: var(--workspace-text); transition: background 0.25s ease, color 0.25s ease; }
        .workspace-main { background: transparent; }
        .glass-panel { background: var(--workspace-panel-bg); backdrop-filter: blur(16px); border: 1px solid var(--workspace-border); transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease; }
        .glass-card { background: linear-gradient(135deg, var(--workspace-card-start), var(--workspace-card-end)); backdrop-filter: blur(12px); border: 1px solid var(--workspace-border); transition: all 0.3s ease; }
        .glass-card:hover { border-color: rgba(16, 185, 129, 0.25); transform: translateY(-2px); box-shadow: 0 8px 32px var(--workspace-shadow); }
        .sidebar-item { transition: all 0.2s ease; position: relative; }
        .sidebar-item:hover { background: rgba(16, 185, 129, 0.08); color: #34d399; }
        .sidebar-item.active { background: linear-gradient(90deg, rgba(16, 185, 129, 0.15), transparent); color: #10b981; }
        .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: #10b981; border-radius: 0 2px 2px 0; }
        .stat-glow { position: relative; }
        .stat-glow::before { content: ''; position: absolute; inset: -1px; border-radius: inherit; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), transparent, rgba(16, 185, 129, 0.05)); z-index: -1; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--workspace-scrollbar); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--workspace-scrollbar-hover); }
        .table-row { transition: background 0.15s; }
        .table-row:hover { background: rgba(30, 41, 59, 0.5); }
        .btn-primary { background: linear-gradient(135deg, #059669, #10b981); transition: all 0.2s; }
        .btn-primary:hover { background: linear-gradient(135deg, #047857, #059669); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .btn-secondary { background: var(--workspace-secondary-bg); border: 1px solid var(--workspace-input-border); transition: all 0.2s; }
        .btn-secondary:hover { background: var(--workspace-secondary-hover); border-color: var(--workspace-border); }
        .btn-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171; transition: all 0.2s; }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.25); }
        .input-field { background: var(--workspace-input-bg); border: 1px solid var(--workspace-input-border); color: var(--workspace-text); transition: all 0.2s; }
        .input-field:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
        .input-field::placeholder { color: var(--workspace-muted-soft); }
        .modal-backdrop { background: var(--workspace-modal); backdrop-filter: blur(4px); }
        .pulse-dot { animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        select.input-field { appearance: none; background-image: var(--workspace-select-arrow); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em; padding-right: 2.5rem; }
        .theme-toggle { color: #94a3b8; transition: color 0.2s ease; }
        .theme-toggle:hover { color: var(--workspace-text); }

        [data-theme="light"] .sidebar-item { color: #334155; }
        [data-theme="light"] .theme-toggle { color: #64748b; }
        [data-theme="light"] .table-row:hover { background: rgba(226, 232, 240, 0.72); }
        [data-theme="light"] .stat-glow::before { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(148, 163, 184, 0.06), rgba(59, 130, 246, 0.08)); }
        [data-theme="light"] .text-white { color: #0f172a !important; }
        [data-theme="light"] .text-slate-100 { color: #0f172a !important; }
        [data-theme="light"] .text-slate-200 { color: #1e293b !important; }
        [data-theme="light"] .text-slate-300 { color: #334155 !important; }
        [data-theme="light"] .text-slate-400 { color: #475569 !important; }
        [data-theme="light"] .text-slate-500 { color: #64748b !important; }
        [data-theme="light"] .text-slate-600 { color: #94a3b8 !important; }
        [data-theme="light"] .text-slate-700 { color: #cbd5e1 !important; }
        [data-theme="light"] .border-slate-600,
        [data-theme="light"] .border-slate-600\/30,
        [data-theme="light"] .border-slate-700,
        [data-theme="light"] .border-slate-700\/30,
        [data-theme="light"] .border-slate-700\/40,
        [data-theme="light"] .border-slate-700\/50,
        [data-theme="light"] .border-slate-700\/70,
        [data-theme="light"] .border-slate-700\/80,
        [data-theme="light"] .border-slate-800,
        [data-theme="light"] .border-slate-800\/40,
        [data-theme="light"] .border-slate-800\/50,
        [data-theme="light"] .border-slate-800\/60,
        [data-theme="light"] .border-slate-800\/70,
        [data-theme="light"] .border-slate-800\/80 { border-color: rgba(148, 163, 184, 0.3) !important; }
        [data-theme="light"] .bg-slate-500\/10,
        [data-theme="light"] .bg-slate-500\/20,
        [data-theme="light"] .bg-slate-700\/50,
        [data-theme="light"] .bg-slate-800,
        [data-theme="light"] .bg-slate-800\/20,
        [data-theme="light"] .bg-slate-800\/30,
        [data-theme="light"] .bg-slate-800\/40,
        [data-theme="light"] .bg-slate-800\/50,
        [data-theme="light"] .bg-slate-800\/80,
        [data-theme="light"] .bg-slate-950\/40,
        [data-theme="light"] .bg-slate-900\/20,
        [data-theme="light"] .bg-slate-900\/35,
        [data-theme="light"] .bg-slate-900\/40,
        [data-theme="light"] .bg-slate-900\/50,
        [data-theme="light"] .bg-slate-900\/60,
        [data-theme="light"] .bg-slate-900\/80,
        [data-theme="light"] .bg-slate-900\/85,
        [data-theme="light"] .bg-slate-900\/90 { background: rgba(255, 255, 255, 0.84) !important; }
    </style>
</head>
<body class="workspace-shell flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 glass-panel border-r border-slate-800/80 flex flex-col z-20 shrink-0">
        <div class="h-16 flex items-center px-5 border-b border-slate-800/60">
            <div class="flex items-center min-w-0">
                <div class="w-9 h-9 bg-emerald-500/15 rounded-lg flex items-center justify-center border border-emerald-500/25 mr-3 shrink-0">
                    <i class="fa-solid fa-bolt text-emerald-400 text-sm"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-base font-bold text-white tracking-wide block truncate">Electava</span>
                    <span class="block text-[10px] text-slate-500 -mt-0.5 tracking-widest uppercase">Workspace</span>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto custom-scrollbar py-4 px-3 space-y-0.5">
            <div class="text-[10px] uppercase text-slate-600 font-semibold tracking-widest mb-2 px-3">Navigation</div>

            <?php if ($role === 'core_admin'): ?>
                <a href="/core_admin/" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-chart-line w-5 text-center mr-3 text-sm"></i>Dashboard</a>
                <a href="/core_admin/employees.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-users w-5 text-center mr-3 text-sm"></i>Employees</a>

                <a href="/core_admin/projects.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-diagram-project w-5 text-center mr-3 text-sm"></i>Projects</a>
                <a href="/core_admin/domains.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-network-wired w-5 text-center mr-3 text-sm"></i>Domains</a>
                <a href="/core_admin/templates.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-clipboard-list w-5 text-center mr-3 text-sm"></i>Task Templates</a>
                <div class="text-[10px] uppercase text-slate-600 font-semibold tracking-widest mt-5 mb-2 px-3">Monitoring & Tracking</div>
                <a href="/core_admin/logs.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-shield-halved w-5 text-center mr-3 text-sm"></i>Audit Logs</a>
                <a href="/core_admin/login_logs.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-right-to-bracket w-5 text-center mr-3 text-sm"></i>Employee Logins</a>
                <a href="/core_admin/marketplace_tracking.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-globe w-5 text-center mr-3 text-sm"></i>Marketplace Tracking</a>
                <div class="text-[10px] uppercase text-slate-600 font-semibold tracking-widest mt-5 mb-2 px-3">Marketplace Operations</div>
                <a href="/core_admin/users.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-user-group w-5 text-center mr-3 text-sm"></i>Users</a>

                <a href="/core_admin/careers.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-briefcase w-5 text-center mr-3 text-sm"></i>Careers</a>
                <a href="/core_admin/reports.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-chart-pie w-5 text-center mr-3 text-sm"></i>Reports</a>
                <div class="text-[10px] uppercase text-slate-600 font-semibold tracking-widest mt-5 mb-2 px-3">System</div>
                <a href="/core_admin/permissions.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-key w-5 text-center mr-3 text-sm"></i>Permissions</a>
                <a href="/core_admin/settings.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-gear w-5 text-center mr-3 text-sm"></i>Settings</a>

            <?php elseif ($role === 'admin'): ?>
                <a href="/admin/" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-table-columns w-5 text-center mr-3 text-sm"></i>Dashboard</a>
                <a href="/admin/tasks.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-list-check w-5 text-center mr-3 text-sm"></i>Tasks</a>
                <a href="/admin/approvals.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-clipboard-check w-5 text-center mr-3 text-sm"></i>Approvals</a>
                <a href="/admin/team.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-people-group w-5 text-center mr-3 text-sm"></i>Team</a>
                <a href="/admin/vendors.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-handshake w-5 text-center mr-3 text-sm"></i>Vendors</a>
                <a href="/admin/reports.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-chart-pie w-5 text-center mr-3 text-sm"></i>Reports</a>

            <?php elseif ($role === 'employee'): ?>
                <a href="/employee/" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-gauge-high w-5 text-center mr-3 text-sm"></i>Dashboard</a>
                <a href="/employee/tasks.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-list-check w-5 text-center mr-3 text-sm"></i>My Tasks</a>
                <a href="/employee/submissions.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-paper-plane w-5 text-center mr-3 text-sm"></i>Submissions</a>
                <?php if (hasDomainAccess(1)): ?>
                <a href="/employee/components.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-microchip w-5 text-center mr-3 text-sm"></i>Components</a>
                <?php endif; ?>
                <?php if (hasDomainAccess(2)): ?>
                <a href="/employee/services.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-cogs w-5 text-center mr-3 text-sm"></i>My Requests</a>
                <a href="/employee/service_requests.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-screwdriver-wrench w-5 text-center mr-3 text-sm"></i>Service Queue</a>
                <a href="/employee/service_tokens.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-ticket w-5 text-center mr-3 text-sm"></i>Service Tokens</a>
                <?php endif; ?>

            <?php elseif ($role === 'vendor'): ?>
                <a href="/vendor/" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-gauge-high w-5 text-center mr-3 text-sm"></i>Dashboard</a>
                <a href="/vendor/products.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-boxes-stacked w-5 text-center mr-3 text-sm"></i>Products</a>
                <a href="/vendor/orders.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-truck-fast w-5 text-center mr-3 text-sm"></i>Purchase Orders</a>
                <a href="/vendor/inventory.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-warehouse w-5 text-center mr-3 text-sm"></i>Inventory</a>
                <a href="/vendor/profile.php" class="sidebar-item flex items-center px-3 py-2.5 rounded-lg text-sm text-slate-300"><i class="fa-solid fa-building w-5 text-center mr-3 text-sm"></i>Company Profile</a>
            <?php endif; ?>
        </nav>

        <!-- User Footer -->
        <div class="p-4 border-t border-slate-800/60">
            <div class="flex items-center">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-xs font-bold text-white shadow-lg">
                    <?= strtoupper(substr($fullName, 0, 1)) ?>
                </div>
                <div class="ml-3 flex-1 overflow-hidden">
                    <div class="text-sm font-medium text-white truncate"><?= htmlspecialchars($fullName) ?></div>
                    <div class="text-[11px] text-emerald-400/70 capitalize"><?= str_replace('_', ' ', $role) ?></div>
                </div>
                <a href="/logout.php" class="text-slate-500 hover:text-red-400 transition-colors ml-2 p-1" title="Logout">
                    <i class="fa-solid fa-power-off text-sm"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <main class="workspace-main flex-1 flex flex-col h-screen overflow-hidden">
        <!-- Topbar -->
        <header class="h-14 glass-panel border-b border-slate-800/60 flex items-center justify-between px-6 z-10 shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-white"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Overview' ?></h2>
            </div>
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <button type="button" id="themeToggleButton" class="theme-toggle text-slate-400 hover:text-white transition relative p-1" title="Switch to light mode" aria-label="Switch to light mode" aria-pressed="false">
                    <i id="themeToggleIcon" class="fa-solid fa-moon text-base"></i>
                </button>
                <a href="/notifications.php" class="text-slate-400 hover:text-white transition relative p-1">
                    <i class="fa-regular fa-bell text-base"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-emerald-500 rounded-full flex items-center justify-center text-[9px] font-bold text-white pulse-dot"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                    <?php endif; ?>
                </a>
                <div class="w-px h-5 bg-slate-800"></div>
                <span class="text-xs text-slate-500"><?= date('D, M j, Y') ?></span>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar fade-in">
