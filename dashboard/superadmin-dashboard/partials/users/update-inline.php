<?php
session_start();
require_once '../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$userId = (int) ($data['userId'] ?? 0);
$field  = $data['field'] ?? '';
$value  = trim($data['value'] ?? '');

$allowed = ['name','email','role','status'];
if (!$userId || !in_array($field, $allowed, true)) {
    exit;
}

if ($userId === $_SESSION['user']['id'] && ($field === 'role' || $field === 'status')) {
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET `$field` = ? WHERE id = ?");
$stmt->execute([$value, $userId]);
