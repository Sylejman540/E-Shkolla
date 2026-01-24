<?php
// Disable error reporting to browser to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

header('Content-Type: application/json');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Te dhenat jane bosh ose format i gabuar.');
    }

    $pdo->beginTransaction();

    // Prepare queries
    $getSubject = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ? LIMIT 1");
    $insert = $pdo->prepare("INSERT INTO schedule (school_id, class_id, day, period_number, teacher_id, subject_id) VALUES (?, ?, ?, ?, ?, ?)");

    $imported = 0;
    foreach ($data as $row) {
        // 1. Get the subject for this teacher
        $getSubject->execute([$row['teacher_id']]);
        $subjectId = $getSubject->fetchColumn();

        if ($subjectId) {
            $insert->execute([
                $_SESSION['user']['school_id'],
                $row['class_id'],
                $row['day'],
                $row['period_number'],
                $row['teacher_id'],
                $subjectId
            ]);
            $imported++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'imported' => $imported]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;