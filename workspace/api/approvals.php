<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    requireRole(['core', 'sub_core']);
    $data = json_decode(file_get_contents('php://input'), true);
    $task_id = $data['task_id'] ?? null;
    $comments = $data['comments'] ?? '';
    
    if ($task_id) {
        $stmt = $pdo->prepare("INSERT INTO task_approvals (task_id, approved_by, comments) VALUES (?, ?, ?)");
        $stmt->execute([$task_id, $_SESSION['user_id'], $comments]);
        
        $stmt2 = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
        $stmt2->execute([$task_id]);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
    }
} else {
    $stmt = $pdo->query("SELECT * FROM task_approvals LIMIT 50");
    echo json_encode($stmt->fetchAll());
}
?>
