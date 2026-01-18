<?php
session_start();
require_once '../../../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$classId = (int)($data['classId'] ?? 0);
$field   = $data['field'] ?? '';
$value   = trim($data['value'] ?? '');

$allowedFields = ['grade', 'max_students', 'status'];

if (!$classId || !in_array($field, $allowedFields)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    http_response_code(400);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE classes SET `$field` = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$value, $classId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    http_response_code(500);
}