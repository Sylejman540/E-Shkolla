<?php
require_once __DIR__ . '/../../../../db.php';

$stmt = $pdo->prepare("
SELECT cs.id, cs.day, cs.period_number,
       s.subject_name, t.name AS teacher_name
FROM class_schedule cs
JOIN subjects s ON s.id=cs.subject_id
JOIN teachers t ON t.id=cs.teacher_id
WHERE cs.class_id=? AND cs.school_id=?
");
$stmt->execute([$_GET['class_id'], $_GET['school_id']]);

$grid=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
    $grid[$r['day']][$r['period_number']]=$r;
}

echo json_encode(['grid'=>$grid]);
