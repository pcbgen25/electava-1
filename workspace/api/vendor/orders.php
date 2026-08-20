<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('vendor');

header('Content-Type: application/json');
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Get Vendor ID
$stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$user_id]);
$vendor_id = $stmt->fetchColumn();

if (!$vendor_id) {
    echo json_encode(['error' => 'Not a registered vendor profile']);
    exit;
}

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    echo json_encode($stmt->fetchAll());
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['id'] ?? null;
    $status = $data['status'] ?? 'shipped';
    $tracking = $data['tracking_number'] ?? '';
    
    if ($order_id) {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ?, tracking_number = ?, shipped_at = NOW() WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$status, $tracking, $order_id, $vendor_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing ID']);
    }
}
?>
