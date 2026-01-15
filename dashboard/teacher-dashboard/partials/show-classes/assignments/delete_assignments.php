<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$assignmentId = (int) ($_POST['id'] ?? 0);
$teacherId    = (int) ($_SESSION['user']['id'] ?? 0);
$schoolId     = (int) ($_SESSION['user']['school_id'] ?? 0);

if (!$assignmentId || !$teacherId || !$schoolId) {
    echo json_encode(['success' => false, 'message' => 'Invalid context']);
    exit;
}

/**
 * SECURITY:
 * Delete ONLY if:
 *  - assignment belongs to this teacher
 *  - assignment belongs to this school
 */
$stmt = $pdo->prepare("
    DELETE FROM assignments
    WHERE id = ?
      AND teacher_id = ?
      AND school_id = ?
");

$stmt->execute([$assignmentId, $teacherId, $schoolId]);

if ($stmt->rowCount() === 1) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Detyra nuk u gjet ose nuk keni leje'
    ]);
}
