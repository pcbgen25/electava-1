<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    logAudit($pdo, 'logout', 'user', $_SESSION['user_id'], 'User logged out');
    // Record logout time on the most recent login_logs entry for this user
    $pdo->prepare("
        UPDATE login_logs
        SET logout_at = NOW(),
            session_duration_mins = TIMESTAMPDIFF(MINUTE, created_at, NOW())
        WHERE user_id = ? AND logout_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ")->execute([$_SESSION['user_id']]);
}

// Full session teardown
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();
header('Location: /login.php');
exit;
?>
