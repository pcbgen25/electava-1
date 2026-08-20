<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    logAudit($pdo, 'logout', 'user', $_SESSION['user_id'], 'User logged out');
}
session_destroy();
header("Location: /login.php");
exit;
?>
