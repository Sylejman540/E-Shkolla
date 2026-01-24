<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

header('Content-Type: application/json');

// Check for valid teacher ID
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

if ($teacher_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // We join 'subjects' with 'teacher_subjects' mapping table
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.subject_name 
        FROM subjects s
        INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
        WHERE ts.teacher_id = ?
        ORDER BY s.subject_name ASC
    ");
    
    $stmt->execute([$teacher_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return empty array if no results, otherwise the list
    echo json_encode($subjects ?: []);

} catch (PDOException $e) {
    // If the database query fails (e.g., column doesn't exist)
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}