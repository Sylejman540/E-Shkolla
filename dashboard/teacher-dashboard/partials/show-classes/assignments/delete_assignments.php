<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

header('Content-Type: application/json');

// ✅ Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// ✅ Auth & school check
$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ✅ Validate assignment ID
$assignmentId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($assignmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit;
}

// ✅ Delete (school-scoped)
$stmt = $pdo->prepare("
    DELETE FROM assignments
    WHERE id = ? AND school_id = ?
");
$success = $stmt->execute([$assignmentId, $schoolId]);

if ($success && $stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment not found']);
}
