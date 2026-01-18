<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$input = json_decode(file_get_contents('php://input'), true);
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$input || !$schoolId) {
    echo json_encode(['status' => 'error', 'message' => 'Kërkesë e pavlefshme']);
    exit;
}

$classId = $input['classId'];
$field = $input['field'];
$value = $input['value'];

// Lejo vetëm fushat specifike për siguri
$allowedFields = ['grade', 'max_students', 'status'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['status' => 'error', 'message' => 'Fusha nuk lejohet']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE classes SET $field = :value WHERE id = :id AND school_id = :school_id");
    $stmt->execute([
        ':value' => $value,
        ':id' => $classId,
        ':school_id' => $schoolId
    ]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Dështoi përditësimi']);
}