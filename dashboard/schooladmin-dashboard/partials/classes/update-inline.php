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

// Lejojmë class_header
$allowedFields = ['grade', 'max_students', 'status', 'class_header'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['status' => 'error', 'message' => 'Fusha nuk lejohet']);
    exit;
}

try {
    $finalValue = $value;

    // LOGJIKA SPECIALE: Nëse po editojmë mësuesin me tekst
    if ($field === 'class_header') {
        if ($value === "" || strtolower($value) === "i pacaktuar") {
            $finalValue = null;
        } else {
            // Gjejmë ID-në e mësuesit bazuar në emrin që shkruajti përdoruesi
            $stmtTeacher = $pdo->prepare("SELECT id FROM users WHERE name = ? AND school_id = ? AND role = 'teacher' LIMIT 1");
            $stmtTeacher->execute([$value, $schoolId]);
            $teacher = $stmtTeacher->fetch();

            if (!$teacher) {
                echo json_encode(['status' => 'error', 'message' => 'Mësuesi nuk u gjet! Shkruani emrin saktë.']);
                exit;
            }
            $finalValue = $teacher['id'];
        }
    }

    // Ekzekutojmë Update
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