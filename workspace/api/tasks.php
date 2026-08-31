<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (in_array($role, ['core_admin', 'admin'], true)) {
        $rows = $pdo->query('SELECT * FROM tasks ORDER BY created_at DESC')->fetchAll();
    } elseif ($role === 'employee') {
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE assigned_to = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll();
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode($rows);
} elseif ($method === 'POST') {
    requireRole(['core_admin', 'admin', 'employee']);
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'] ?? 'New Task',
        $data['description'] ?? '',
        $data['assigned_to'] ?? null,
        $user_id,
        $data['due_date'] ?? null
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $task_id = $data['id'] ?? null;
    $status = $data['status'] ?? 'pending';

    $allowedStatuses = ['pending', 'in_progress', 'submitted', 'completed', 'on_hold'];
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid status value']);
        exit;
    }

    if (in_array($role, ['core_admin', 'admin'], true)) {
        $stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?');
        $stmt->execute([$status, $task_id]);
    } elseif ($role === 'employee') {
        $stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?');
        $stmt->execute([$status, $task_id, $user_id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Task not found or not assigned to you']);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode(['success' => true]);
}
?>
