<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if ($role === 'sub_core') {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE created_by = ?");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll());
    } elseif ($role === 'employee') {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll());
    } else {
        $stmt = $pdo->query("SELECT * FROM tasks");
        echo json_encode($stmt->fetchAll());
    }
} elseif ($method === 'POST') {
    requireRole(['core', 'sub_core']);
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
    // Employee updating task status
    $data = json_decode(file_get_contents('php://input'), true);
    $task_id = $data['id'] ?? null;
    $status = $data['status'] ?? 'pending';
    
    if ($task_id) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $task_id]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing task ID']);
    }
}
?>
