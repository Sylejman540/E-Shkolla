<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

function stop($msg) {
    echo json_encode(['error' => $msg, 'grid' => []]);
    exit;
}

if (!isset($_SESSION['user']['school_id'])) stop('Session expired');

// Unified check for class_id
$classId = (int)($_GET['class_id'] ?? 0);
$schoolId = (int)$_SESSION['user']['school_id'];

if ($classId === 0) {
    echo json_encode([
        'error' => 'Mungon class_id në kërkesë', 
        'grid' => [],
        'debug_received' => $_GET 
    ]);
    exit;
}

try {
    // We use cs.* to get every column from class_schedule (id, status, created_at, etc.)
    $stmt = $pdo->prepare("
        SELECT 
            cs.*, 
            s.subject_name, 
            t.name AS teacher_name
        FROM class_schedule cs
        JOIN subjects s ON s.id = cs.subject_id
        JOIN teachers t ON t.id = cs.teacher_id
        WHERE cs.class_id = ? AND cs.school_id = ?
        ORDER BY cs.period_number ASC
    ");
    $stmt->execute([$classId, $schoolId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grid = [];
    foreach ($rows as $row) {
        // Grouping by day and period_number for the frontend grid
        $grid[$row['day']][$row['period_number']] = $row;
    }

    echo json_encode(['grid' => $grid]);
} catch (Exception $e) {
    stop($e->getMessage());
}