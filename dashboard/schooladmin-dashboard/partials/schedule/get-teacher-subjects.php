<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php'; // Rruga drejt skedarit të lidhjes

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$class_id   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($teacher_id <= 0 || $class_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Query që lidh lidhjen mësues-klasë me emrin e lëndës
    $stmt = $pdo->prepare("
        SELECT s.id, s.subject_name 
        FROM teacher_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.teacher_id = ? AND ts.class_id = ?
    ");
    $stmt->execute([$teacher_id, $class_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($subjects);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Gabim në server"]);
}