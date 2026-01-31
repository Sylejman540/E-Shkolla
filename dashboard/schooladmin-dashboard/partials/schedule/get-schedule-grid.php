<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

$classId = (int)$_GET['class_id'];

$stmt = $pdo->prepare("
    SELECT cs.*, s.subject_name, u.name AS teacher_name
    FROM class_schedule cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE cs.class_id = ?
");
$stmt->execute([$classId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grid = [];
foreach ($rows as $r) {
    $grid[$r['day']][$r['period_number']] = $r;
}
echo json_encode(['grid' => $grid]);