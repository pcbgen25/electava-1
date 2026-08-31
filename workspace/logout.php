<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    logAudit($pdo, 'logout', 'user', $_SESSION['user_id'], 'User logged out');
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
