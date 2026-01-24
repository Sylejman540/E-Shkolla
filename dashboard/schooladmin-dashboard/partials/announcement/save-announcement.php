<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

// Auth Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id   = $_SESSION['user']['school_id'];
    $title       = trim($_POST['title'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $target_role = $_POST['target_role'] ?? 'all';
    $expires_at  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    // THE FIX: If class_id is empty string, set it to NULL
    $class_id = $_POST['class_id'] ?? '';
    $class_id = ($class_id === '') ? null : (int)$class_id;

    if (empty($title) || empty($message)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=missing_fields');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO announcements 
            (school_id, title, content, target_role, class_id, expires_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $school_id,
            $title,
            $message,
            $target_role,
            $class_id,
            $expires_at
        ]);

        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=announcement_created');
    } catch (PDOException $e) {
        // Log error if needed: error_log($e->getMessage());
        exit("Error: " . $e->getMessage());
    }
}