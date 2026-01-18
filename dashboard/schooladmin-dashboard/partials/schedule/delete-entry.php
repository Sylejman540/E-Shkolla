<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

// Check if user is logged in and has a school_id
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$schoolId) {
    echo json_encode(['success' => false, 'message' => 'Aksesi u refuzua.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    // We include school_id in the WHERE clause to ensure an admin 
    // can only delete schedules belonging to their own school.
    $stmt = $pdo->prepare("DELETE FROM class_schedule WHERE id = ? AND school_id = ?");
    $success = $stmt->execute([$data['id'], $schoolId]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ky regjistrim nuk u gjet ose nuk keni leje.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID e orarit mungon.']);
}
exit;