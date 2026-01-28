<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'teacher'
) {
    http_response_code(403);
    exit('Akses i ndaluar');
}

$schoolId  = (int)$_SESSION['user']['school_id'];
$teacherId = (int)$_SESSION['user']['id'];

$title     = trim($_POST['title'] ?? '');
$content   = trim($_POST['message'] ?? '');
$target    = $_POST['target_role'] ?? 'all';
$classId   = $_POST['class_id'] ?? null;
$classId   = ($classId !== null && $classId !== '') ? (int)$classId : null;
$expiresAt = $_POST['expires_at'] ?? null;

if ($title === '' || $content === '') {
    http_response_code(422);
    exit('Titulli dhe mesazhi janë të detyrueshme');
}

/* =========================
   ONLY SCHOOL VALIDATION
========================= */
if (in_array($target, ['student','parent'], true)) {

    if (!$classId) {
        http_response_code(422);
        exit('Klasa është e detyrueshme');
    }

    // Class must belong to same school — NOTHING ELSE
    $classCheck = $pdo->prepare("
        SELECT 1
        FROM classes
        WHERE id = ?
          AND school_id = ?
        LIMIT 1
    ");
    $classCheck->execute([$classId, $schoolId]);

    if (!$classCheck->fetchColumn()) {
        http_response_code(403);
        exit('Klasa nuk i përket shkollës suaj');
    }

} else {
    $classId = null;
}

/* =========================
   INSERT
========================= */
$stmt = $pdo->prepare("
    INSERT INTO announcements (
        school_id,
        teacher_id,
        author_id,
        title,
        content,
        target_role,
        class_id,
        expires_at,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $schoolId,
    $teacherId,
    $teacherId,
    $title,
    $content,
    $target,
    $classId,
    $expiresAt ?: null
]);

$emails = [];

if ($target === 'parent') {

$stmt = $pdo->prepare("
    SELECT DISTINCT u.email
    FROM users u
    JOIN parents p ON p.user_id = u.id
    JOIN parent_student sp ON sp.parent_id = p.id
    JOIN students s ON s.user_id = sp.student_id
    WHERE u.school_id = ?
      AND s.class_id = ?
      AND u.email IS NOT NULL
");

    $stmt->execute([$schoolId, $classId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

} elseif ($target === 'student') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT u.email
        FROM users u
        JOIN students s ON s.user_id = u.id
        WHERE u.school_id = ?
          AND s.class_id = ?
          AND u.email IS NOT NULL
    ");
    $stmt->execute([$schoolId, $classId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

} elseif ($target === 'all') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT email
        FROM users
        WHERE school_id = ?
          AND email IS NOT NULL
    ");
    $stmt->execute([$schoolId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (!empty($emails)) {

    $subject = "Njoftim nga shkolla";
    $message = "
        <h3>" . htmlspecialchars($title) . "</h3>
        <p>" . nl2br(htmlspecialchars($content)) . "</p>
        <hr>
        <small>E dërguar nga E-Shkolla</small>
    ";

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: E-Shkolla <no-reply@yourdomain.com>"
    ];

    foreach ($emails as $email) {
        mail($email, $subject, $message, implode("\r\n", $headers));
    }
}


header('Location: /E-Shkolla/teacher-notices');
exit;
