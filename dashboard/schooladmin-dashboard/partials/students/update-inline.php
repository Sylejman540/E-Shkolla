<?php
session_start();
require_once '../../../../db.php';

if (
    !isset($_SESSION['user']) ||
    !in_array($_SESSION['user']['role'], ['super_admin', 'school_admin'], true)
) {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$userId = (int) ($data['userId'] ?? 0);
$field  = $data['field'] ?? '';
$value  = trim($data['value'] ?? '');

if (!$userId || !$field) {
    http_response_code(400);
    exit;
}

$userFields     = ['name', 'email', 'status'];
$studentFields  = ['name', 'email', 'phone', 'gender', 'class_name', 'date_birth', 'status'];

if (in_array($field, $userFields, true)) {
    $stmt = $pdo->prepare("UPDATE users SET `$field` = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$value, $userId]);
}

if (in_array($field, $studentFields, true)) {
    $stmt = $pdo->prepare("UPDATE students SET `$field` = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$value, $userId]);
}

echo json_encode(['success' => true]);
