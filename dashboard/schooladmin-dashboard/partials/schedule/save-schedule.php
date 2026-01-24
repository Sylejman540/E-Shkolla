<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = $_SESSION['user']['school_id'];
    $userId   = $_SESSION['user']['id'];
    
    // We use INSERT ... ON DUPLICATE KEY UPDATE to handle conflicts automatically
    $stmt = $pdo->prepare("
        INSERT INTO class_schedule 
        (school_id, user_id, class_id, day, period_number, subject_id, teacher_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE 
            subject_id = VALUES(subject_id), 
            teacher_id = VALUES(teacher_id)
    ");

    try {
        $stmt->execute([
            $schoolId,
            $userId,
            $_POST['class_id'],
            $_POST['day'],
            $_POST['period_number'],
            $_POST['subject_id'],
            $_POST['teacher_id']
        ]);
    } catch (Exception $e) {
        // Log error if needed
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;