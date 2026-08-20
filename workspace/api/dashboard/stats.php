<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$domain_id = $_SESSION['domain_id'] ?? null;

$stats = [];

if ($role === 'core') {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['pending_tasks'] = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
} elseif ($role === 'sub_core') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND created_by IN (SELECT id FROM users WHERE domain_id = ?)");
    $stmt->execute([$domain_id]);
    $stats['open_domain_tasks'] = $stmt->fetchColumn();
} elseif ($role === 'employee') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND assigned_to = ?");
    $stmt->execute([$user_id]);
    $stats['my_open_tasks'] = $stmt->fetchColumn();
} elseif ($role === 'vendor') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = (SELECT id FROM vendors WHERE user_id = ?) AND status = 'pending'");
    $stmt->execute([$user_id]);
    $stats['pending_orders'] = $stmt->fetchColumn();
}

echo json_encode($stats);
?>
