<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
        die('Unauthorized');
    }

    $schoolId  = $_SESSION['user']['school_id'];
    $teacherId = $_SESSION['user']['id'];

    $title   = $_POST['title'] ?? '';
    $content = $_POST['message'] ?? '';
    $target  = $_POST['target_role'] ?? 'all';
    $classId = ($target === 'student') ? ($_POST['class_id'] ?? null) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO announcements (
                school_id,
                teacher_id,
                author_id,
                title,
                content,
                target_role,
                class_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $schoolId,
            $teacherId,
            $teacherId, // author_id = teacher for now
            $title,
            $content,
            $target,
            $classId
        ]);

        header("Location: /E-Shkolla/school-announcement?success=1");
        exit;

    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
