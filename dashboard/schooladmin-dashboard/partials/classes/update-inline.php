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

$classId = (int) ($data['classId'] ?? 0);
$field   = $data['field'] ?? '';
$value   = trim($data['value'] ?? '');

$allowedFields = [
    'academic_year',
    'grade',
    'max_students',
    'status'
];

if (!$classId || !in_array($field, $allowedFields, true)) {
    exit;
}
$stmt = $pdo->prepare("UPDATE classes SET `$field` = ? WHERE id = ?");
$stmt->execute([$value, $classId]);

if ($field === 'status') {
    $stmt = $pdo->prepare("UPDATE class_schedule SET status = ? WHERE class_id = ?");
    $stmt->execute([$value, $classId]);
}

http_response_code(200);
