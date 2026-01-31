<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$input = json_decode(file_get_contents('php://input'), true);
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$input || !$schoolId) {
    echo json_encode(['status' => 'error', 'message' => 'Kërkesë e pavlefshme']);
    exit;
}

$classId = $input['classId'];
$field   = $input['field'];
$value   = trim($input['value']);

$allowedFields = ['grade', 'max_students', 'status', 'class_header'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['status' => 'error', 'message' => 'Fusha nuk lejohet']);
    exit;
}

try {
    $finalValue = $value;

    if ($field === 'class_header') {
        if ($value === "" || strtolower($value) === "i pacaktuar") {
            $finalValue = null;
        } else {
            // 1. Find the teacher ID by name
            $stmtTeacher = $pdo->prepare("SELECT id FROM users WHERE name = ? AND school_id = ? AND role = 'teacher' LIMIT 1");
            $stmtTeacher->execute([$value, $schoolId]);
            $teacher = $stmtTeacher->fetch();

            if (!$teacher) {
                echo json_encode(['status' => 'error', 'message' => 'Mësuesi nuk u gjet! Shkruani emrin saktë.']);
                exit;
            }

            $teacherId = $teacher['id'];

            // 2. CHECK: Is this teacher already assigned to another class?
            // We exclude the current class ID so the user can re-save the same teacher.
            $stmtCheck = $pdo->prepare("SELECT id FROM classes WHERE class_header = ? AND school_id = ? AND id != ? LIMIT 1");
            $stmtCheck->execute([$teacherId, $schoolId, $classId]);
            
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Ky mësues është tashmë kujdestar i një klase tjetër!']);
                exit;
            }

            $finalValue = $teacherId;
        }
    }

    // Execute Update
    $stmt = $pdo->prepare("UPDATE classes SET $field = :value WHERE id = :id AND school_id = :school_id");
    $stmt->execute([
        ':value' => $finalValue,
        ':id' => $classId,
        ':school_id' => $schoolId
    ]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Gabim gjatë ruajtjes']);
}