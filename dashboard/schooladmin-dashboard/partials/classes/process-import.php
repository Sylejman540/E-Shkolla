<?php
ob_start(); // Fillojmë buffer-in për të kapur çdo gabim të papritur
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$school_id = $_SESSION['user']['school_id'] ?? null;

if (!$school_id) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Të dhëna invalide.']);
    exit;
}

$imported = 0; $skipped = 0;

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO classes (school_id, academic_year, grade, max_students, user_id, status) VALUES (?, ?, ?, ?, ?, ?)");
    $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE school_id = ? AND academic_year = ? AND grade = ?");

    foreach ($data as $row) {
        $academic_year = $row['academic_year'] ?? '';
        $grade = $row['grade'] ?? '';
        $teacher_id = !empty($row['teacher_id']) ? (int)$row['teacher_id'] : null;
        $max_students = (int)($row['max_students'] ?? 30);
        $status = $row['status'] ?? 'active';

        if (empty($grade)) { $skipped++; continue; }

        $checkStmt->execute([$school_id, $academic_year, $grade]);
        if ($checkStmt->fetch()) { $skipped++; continue; }

        $stmt->execute([$school_id, $academic_year, $grade, $max_students, $teacher_id, $status]);
        $imported++;
    }

    $pdo->commit();
    ob_clean(); // Fshijmë çdo gjë që nuk është JSON
    echo json_encode(['status' => 'success', 'imported' => $imported, 'skipped' => $skipped]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}