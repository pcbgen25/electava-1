<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . getDashboardUrl($_SESSION['role']));
} else {
    header("Location: /login.php");
}
exit;
?>
