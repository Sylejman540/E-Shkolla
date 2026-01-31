<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$school_id     = (int)$_POST['school_id'];
$class_id      = (int)$_POST['class_id'];
$teacher_id    = (int)$_POST['teacher_id']; // teachers.id (CORRECT)
$subject_id    = (int)$_POST['subject_id'];
$day           = $_POST['day'];
$period_number = (int)$_POST['period_number'];

try {
    /* =========================
       CHECK EXISTING SLOT
    ========================= */
    $check = $pdo->prepare("
        SELECT id 
        FROM class_schedule 
        WHERE class_id = ? 
          AND day = ? 
          AND period_number = ?
        LIMIT 1
    ");
    $check->execute([$class_id, $day, $period_number]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        // UPDATE slot
        $stmt = $pdo->prepare("
            UPDATE class_schedule
            SET teacher_id = ?, subject_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$teacher_id, $subject_id, $existingId]);
    } else {
        // INSERT new slot
        $stmt = $pdo->prepare("
            INSERT INTO class_schedule
                (school_id, class_id, teacher_id, subject_id, day, period_number)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $school_id,
            $class_id,
            $teacher_id,
            $subject_id,
            $day,
            $period_number
        ]);
    }

    $_SESSION['msg'] = [
        'type' => 'success',
        'text' => 'Orari u ruajt me sukses'
    ];

} catch (PDOException $e) {

    // 🔴 DUPLICATE TEACHER TIME SLOT
    if ($e->getCode() === '23000') {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Mësuesi është tashmë i zënë në këtë orë'
        ];
    } else {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Ndodhi një gabim gjatë ruajtjes së orarit'
        ];
    }
}

// 🔁 ALWAYS redirect back to schedule
header("Location: /E-Shkolla/schedule?class_id={$class_id}");
exit;
