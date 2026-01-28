<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: /E-Shkolla/login");
    exit();
}
require_once '../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$schoolId = (int) ($data['schoolId'] ?? 0);
$field    = $data['field'] ?? '';
$value    = trim($data['value'] ?? '');

$allowed = ['school_name','city','email','status','admin_id'];
if (!$schoolId || !in_array($field, $allowed, true)) {
    exit;
}

$stmt = $pdo->prepare("UPDATE schools SET `$field` = ? WHERE id = ?");
$stmt->execute([$value, $schoolId]);
