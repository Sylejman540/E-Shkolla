<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/* =========================
   AUTH CHECK
========================= */
if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] !== 'school_admin'
) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* =========================
   INPUT
========================= */
$school_id  = (int) $_SESSION['user']['school_id'];
$author_id  = (int) $_SESSION['user']['id'];

$title       = trim($_POST['title'] ?? '');
$message     = trim($_POST['message'] ?? '');
$target_role = $_POST['target_role'] ?? 'all';
$expires_at  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

/* CLASS FILTER (NULL SAFE) */
$class_id = $_POST['class_id'] ?? '';
$class_id = ($class_id === '') ? null : (int)$class_id;

/* =========================
   VALIDATION
========================= */
if ($title === '' || $message === '') {
    header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=missing_fields');
    exit;
}

try {
    /* =========================
       INSERT ANNOUNCEMENT
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO announcements (
            school_id,
            author_id,
            title,
            content,
            target_role,
            class_id,
            expires_at,
            created_at
        ) VALUES (
            :school_id,
            :author_id,
            :title,
            :content,
            :target_role,
            :class_id,
            :expires_at,
            NOW()
        )
    ");

    $stmt->execute([
        ':school_id'  => $school_id,
        ':author_id'  => $author_id,
        ':title'      => $title,
        ':content'    => $message,
        ':target_role'=> $target_role,
        ':class_id'   => $class_id,
        ':expires_at' => $expires_at
    ]);

    header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=announcement_created');
    exit;

} catch (PDOException $e) {
    error_log('[Announcement Create Error] ' . $e->getMessage());
    http_response_code(500);
    exit('Failed to create announcement');
}
