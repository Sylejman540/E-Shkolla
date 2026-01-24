<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

/* AUTH */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$schoolId = (int)$_SESSION['user']['school_id'];
$userId   = (int)$_SESSION['user']['id'];

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$inserted = 0;
$skipped  = 0;

$checkTeacher = $pdo->prepare("
    SELECT id FROM class_schedule
    WHERE school_id=? AND teacher_id=? AND day=? AND period_number=?
");

$checkClass = $pdo->prepare("
    SELECT id FROM class_schedule
    WHERE school_id=? AND class_id=? AND day=? AND period_number=?
");

$insert = $pdo->prepare("
    INSERT INTO class_schedule
    (school_id, user_id, class_id, day, period_number, subject_id, teacher_id)
    VALUES (?,?,?,?,?,?,?)
");

foreach ($data as $row) {
    $classId  = (int)$row['class_id'];
    $day      = trim($row['day']);
    $period   = (int)$row['period_number'];
    $subject  = (int)$row['subject_id'];
    $teacher  = (int)$row['teacher_id'];

    if (!$classId || !$day || !$period || !$subject || !$teacher) {
        $skipped++;
        continue;
    }

    // teacher busy?
    $checkTeacher->execute([$schoolId, $teacher, $day, $period]);
    if ($checkTeacher->fetch()) {
        $skipped++;
        continue;
    }

    // class busy?
    $checkClass->execute([$schoolId, $classId, $day, $period]);
    if ($checkClass->fetch()) {
        $skipped++;
        continue;
    }

    $insert->execute([
        $schoolId,
        $userId,
        $classId,
        $day,
        $period,
        $subject,
        $teacher
    ]);

    $inserted++;
}

echo json_encode([
    'status'   => 'success',
    'imported' => $inserted,
    'skipped'  => $skipped
]);
