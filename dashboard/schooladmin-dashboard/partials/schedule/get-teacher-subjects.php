<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
// class_id mund ta mbajmë për logjikë të mëvonshme, por po e heqim nga WHERE për momentin
$class_id   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($teacher_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Marrim të gjitha lëndët që ky mësues jep
    $stmt = $pdo->prepare("
        SELECT s.id, s.subject_name 
        FROM teacher_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($subjects ?: []); // Sigurohemi që kthehet array bosh nëse s'ka lëndë
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]); // Kjo të ndihmon të shohësh gabimin fiks
}