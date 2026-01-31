<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id  = (int)$_POST['school_id'];
    $class_id   = (int)$_POST['class_id'];
    $user_id    = (int)$_POST['teacher_id']; // Kjo vjen si ID e përdoruesit
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $day        = $_POST['day'];
    $period     = (int)$_POST['period_number'];

    if ($subject_id <= 0 || $user_id <= 0) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Gabim: Plotësoni të gjitha fushat!'];
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        // Përdorim një INSERT me SELECT për të gjetur teacher_id automatikisht
        $sql = "INSERT INTO class_schedule (school_id, class_id, teacher_id, subject_id, day, period_number) 
                SELECT ?, ?, t.id, ?, ?, ?
                FROM teachers t 
                WHERE t.user_id = ?
                ON DUPLICATE KEY UPDATE 
                teacher_id = VALUES(teacher_id), 
                subject_id = VALUES(subject_id)";
        
        $stmt = $pdo->prepare($sql);
        // Vini re: t.id merret nga tabela teachers duke u bazuar te user_id (?) i fundit në execute
        $stmt->execute([$school_id, $class_id, $subject_id, $day, $period, $user_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['msg'] = ['type' => 'success', 'text' => 'Orari u ruajt me sukses!'];
        } else {
            $_SESSION['msg'] = ['type' => 'error', 'text' => 'Gabim: Mësuesi nuk u gjet në sistem!'];
        }

    } catch (PDOException $e) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Gabim: ' . $e->getMessage()];
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}