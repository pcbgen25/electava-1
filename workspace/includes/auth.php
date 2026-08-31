<?php
if (session_status() === PHP_SESSION_NONE) {
    // Store sessions outside the webroot so they are not accessible via HTTP
    $session_path = sys_get_temp_dir() . '/electava_sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0700, true);
    }
    session_save_path($session_path);

    // Harden session cookies
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);

    session_start();
}

require_once __DIR__ . '/db.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id'])
        && is_numeric($_SESSION['user_id'])
        && (int)$_SESSION['user_id'] > 0;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    // Strict type comparison to prevent type-juggling bypass
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:80px;font-family:Inter,sans-serif;background:#0f172a;color:#f8fafc;min-height:100vh;">
            <h1 style="font-size:48px;color:#ef4444;">403</h1>
            <p style="color:#94a3b8;">Access Denied: You do not have permission to view this page.</p>
            <a href="/" style="color:#10b981;text-decoration:underline;">Return Home</a>
        </div>');
    }
}

function getDashboardUrl($role) {
    switch ($role) {
        case 'core_admin':   return '/core_admin/';
        case 'admin':        return '/admin/';
        case 'employee':     return '/employee/';
        case 'vendor':       return '/vendor/';
        case 'service_team': return '/service_team/';
        default:             return '/';
    }
}

function currentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'domain_id' => $_SESSION['domain_id'] ?? null,
    ];
}

function logAudit($pdo, $action, $entityType = null, $entityId = null, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function logLogin($pdo, $userId, $status = 'success') {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = 'Desktop';
    if (preg_match('/Mobile|Android|iPhone/i', $ua)) $device = 'Mobile';
    elseif (preg_match('/Tablet|iPad/i', $ua)) $device = 'Tablet';
    
    $browser = 'Unknown';
    if (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
    
    $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, device_type, browser, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? null, $ua, $device, $browser, $status]);
}

function notify($pdo, $userId, $title, $message, $type = 'info', $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type, $link]);
}

function getUnreadNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function statusBadge($status) {
    $colors = [
        'pending' => 'amber', 'in_progress' => 'blue', 'submitted' => 'purple',
        'approved' => 'emerald', 'rejected' => 'red', 'completed' => 'emerald',
        'active' => 'emerald', 'on_hold' => 'amber', 'archived' => 'slate',
        'draft' => 'slate', 'pending_approval' => 'amber', 'discontinued' => 'red',
        'new' => 'cyan', 'reviewing' => 'blue', 'quoted' => 'purple',
        'design_in_progress' => 'blue', 'manufacturing' => 'amber',
        'testing' => 'purple', 'cancelled' => 'red',
        'confirmed' => 'blue', 'processing' => 'amber', 'shipped' => 'purple',
        'delivered' => 'emerald',
        'success' => 'emerald', 'failed' => 'red', 'replied' => 'cyan',
        'contacted' => 'blue', 'awaiting_customer' => 'amber',
        'verified' => 'emerald', 'not_needed' => 'slate',
    ];
    $c = $colors[$status] ?? 'slate';
    $label = htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8');
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{$c}-500/10 text-{$c}-400 border border-{$c}-500/20\">{$label}</span>";
}

function priorityBadge($priority) {
    $colors = ['low' => 'slate', 'medium' => 'blue', 'high' => 'amber', 'critical' => 'red', 'urgent' => 'red'];
    $icons = ['low' => 'fa-arrow-down', 'medium' => 'fa-minus', 'high' => 'fa-arrow-up', 'critical' => 'fa-fire'];
    $c = $colors[$priority] ?? 'slate';
    $i = $icons[$priority] ?? 'fa-minus';
    $label = htmlspecialchars(ucfirst($priority), ENT_QUOTES, 'UTF-8');
    return "<span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-{$c}-500/10 text-{$c}-400 border border-{$c}-500/20\"><i class=\"fa-solid {$i} text-[10px]\"></i>{$label}</span>";
}

function hasDomainAccess($domainId) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['role'] === 'core_admin') return true; // Core Admins have global access
    
    // Check primary domain
    if (isset($_SESSION['domain_id']) && $_SESSION['domain_id'] == $domainId) {
        return true;
    }
    
    // Check secondary allowed domains
    if (isset($_SESSION['allowed_domains']) && is_array($_SESSION['allowed_domains'])) {
        if (in_array($domainId, $_SESSION['allowed_domains'])) {
            return true;
        }
    }
    
    return false;
}
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die('Invalid or missing CSRF token. Please go back and try again.');
        }
    }
}
?>
