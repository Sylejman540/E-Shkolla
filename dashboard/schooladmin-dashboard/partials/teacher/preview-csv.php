<?php
require_once __DIR__ . '/../../../middleware/requireSchoolAdmin.php';
require_once __DIR__ . '/../../../../db.php';

header('Content-Type: application/json');

if (!isset($_FILES['csv'])) {
    echo json_encode(['error' => 'CSV mungon']);
    exit;
}

$file = fopen($_FILES['csv']['tmp_name'], 'r');
$header = fgetcsv($file);

$expected = ['name','email','phone','gender','subject_name','class_grade','status'];
if ($header !== $expected) {
    echo json_encode(['error' => 'Strukturë CSV e gabuar']);
    exit;
}

$rows = [];

while (($row = fgetcsv($file)) !== false) {
    [$name,$email,$phone,$gender,$subject,$grade,$status] = array_map('trim', $row);

    $errors = [];

    if (!$name) $errors[] = 'Emri mungon';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email i pavlefshëm';

    $cls = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
    $cls->execute([$grade, $_SESSION['user']['school_id']]);
    if (!$cls->fetch()) $errors[] = 'Klasa nuk ekziston';

    $rows[] = [
        'data' => $row,
        'errors' => $errors
    ];
}

echo json_encode(['rows' => $rows]);
