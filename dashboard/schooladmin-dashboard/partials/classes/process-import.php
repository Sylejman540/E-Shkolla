<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
$userId   = $_SESSION['user']['id'] ?? null;

if (!$schoolId || !$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Sesioni ka skaduar.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'JSON i pavlefshëm.']);
    exit;
}

$imported = 0;
$skipped  = 0;

try {
    $pdo->beginTransaction();

    // Cache teachers (user_id → valid)
    $stmtTeachers = $pdo->prepare("
        SELECT user_id 
        FROM teachers 
        WHERE school_id = ? AND status = 'active'
    ");
    $stmtTeachers->execute([$schoolId]);
    $validTeachers = array_flip($stmtTeachers->fetchAll(PDO::FETCH_COLUMN));

    // Cache existing class_headers
    $stmtUsedHeaders = $pdo->prepare("
        SELECT class_header 
        FROM classes 
        WHERE school_id = ? AND class_header IS NOT NULL
    ");
    $stmtUsedHeaders->execute([$schoolId]);
    $usedHeaders = array_flip($stmtUsedHeaders->fetchAll(PDO::FETCH_COLUMN));

    // Prevent duplicate grades
    $stmtGrades = $pdo->prepare("
        SELECT grade, academic_year 
        FROM classes 
        WHERE school_id = ?
    ");
    $stmtGrades->execute([$schoolId]);
    $existingGrades = [];
    foreach ($stmtGrades->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $existingGrades[$g['academic_year'].'|'.$g['grade']] = true;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO classes
        (school_id, user_id, class_header, academic_year, grade, max_students, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($data as $row) {

        $academicYear = trim($row['academic_year'] ?? '');
        $grade        = trim($row['grade'] ?? '');
        $classHeader  = isset($row['class_header']) ? (int)$row['class_header'] : null;
        $maxStudents  = (int)($row['max_students'] ?? 30);
        $status       = $row['status'] ?? 'active';

        if (!$academicYear || !$grade) {
            $skipped++;
            continue;
        }

        // Duplicate grade check
        $key = $academicYear.'|'.$grade;
        if (isset($existingGrades[$key])) {
            $skipped++;
            continue;
        }

        // Validate class_header (teacher)
        if ($classHeader) {
            if (!isset($validTeachers[$classHeader])) {
                $skipped++;
                continue;
            }

            if (isset($usedHeaders[$classHeader])) {
                $skipped++;
                continue;
            }

            $usedHeaders[$classHeader] = true;
        }

        $stmtInsert->execute([
            $schoolId,
            $userId,        // creator
            $classHeader,   // kujdestar
            $academicYear,
            $grade,
            $maxStudents,
            $status
        ]);

        $existingGrades[$key] = true;
        $imported++;
    }

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'imported' => $imported,
        'skipped'  => $skipped
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
