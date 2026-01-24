<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$schoolId || !$data) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $data['userId'] ?? null;
$field  = $data['field'] ?? null;
$value  = trim($data['value'] ?? '');

// Allowed Student Fields
$allowedFields = ['name', 'email', 'status', 'gender', 'class_name', 'date_birth'];
if (!in_array($field, $allowedFields)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Fushë e pavlefshme']);
    exit;
}

try {
    // 1. Check for no change
    $checkStmt = $pdo->prepare("SELECT $field FROM students WHERE user_id = ? AND school_id = ?");
    $checkStmt->execute([$userId, $schoolId]);
    $currentValue = $checkStmt->fetchColumn();

    if ($currentValue === $value) {
        echo json_encode(['status' => 'no_change']);
        exit;
    }

    // 2. Email uniqueness check in users table
    if ($field === 'email') {
        $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $emailStmt->execute([$value, $userId]);
        if ($emailStmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Ky email ekziston në sistem.']);
            exit;
        }
    }

    // 3. Begin Transaction
    $pdo->beginTransaction();

    // Update Students Table
    $stmt1 = $pdo->prepare("UPDATE students SET $field = ? WHERE user_id = ? AND school_id = ?");
    $stmt1->execute([$value, $userId, $schoolId]);

    // Sync with Users Table if shared field
    $sharedFields = ['name', 'email', 'status'];
    if (in_array($field, $sharedFields)) {
        $stmt2 = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ? AND school_id = ?");
        $stmt2->execute([$value, $userId, $schoolId]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gabim serveri.']);
}