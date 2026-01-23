<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = $_SESSION['user']['school_id'];
    $authorId = $_SESSION['user']['id'];
    $title    = $_POST['title'];
    $content  = $_POST['message'];
    $target   = $_POST['target_role'];
    $classId  = ($target === 'student') ? $_POST['class_id'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (school_id, author_id, title, content, target_role, class_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$schoolId, $authorId, $title, $content, $target, $classId]);
        header("Location: /E-Shkolla/school-announcement?success=1");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}