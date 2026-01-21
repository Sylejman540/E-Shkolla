<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$school_id = $_SESSION['user']['school_id'] ?? null;

if (!$school_id) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired or unauthorized.']);
    exit;
}

// Get the JSON data from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Të dhëna invalide.']);
    exit;
}

$imported = 0;
$skipped = 0;

try {
    $pdo->beginTransaction();

    // Prepare statement
    $stmt = $pdo->prepare("INSERT INTO classes (school_id, user_id, academic_year, grade, max_students, status) 
                           VALUES (?, ?, ?, ?, ?, ?)");

    // Check if class already exists to avoid duplicates
    $checkStmt = $pdo->prepare("SELECT id FROM classes WHERE school_id = ? AND academic_year = ? AND grade = ?");

    foreach ($data as $row) {
        $academic_year = $row['academic_year'];
        $base_grade    = $row['base_grade'];
        $parallel      = $row['parallel'];
        $teacher_id    = !empty($row['teacher_id']) ? $row['teacher_id'] : null;
        $max_students  = (int)($row['max_students'] ?? 30);
        $status        = $row['status'] ?? 'active';

        // Combine grade (e.g. 10/A)
        $full_grade = $base_grade . ($parallel !== '' ? '/' . $parallel : '');

        // Check for duplicates
        $checkStmt->execute([$school_id, $academic_year, $full_grade]);
        if ($checkStmt->fetch()) {
            $skipped++;
            continue;
        }

        // Insert
        $stmt->execute([
            $school_id,
            $teacher_id,
            $academic_year,
            $full_grade,
            $max_students,
            $status
        ]);
        $imported++;
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'imported' => $imported,
        'skipped' => $skipped
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}