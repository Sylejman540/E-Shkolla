<?php
session_start();

/* =========================
   FORCE PHP LOGGING (DEBUG)
========================= */
ini_set('log_errors', '1');
ini_set('error_log', 'C:/laragon/tmp/php-force.log');
error_reporting(E_ALL);

/* =========================
   LOAD ENV (SMTP)
========================= */
$envPath = dirname(__DIR__, 4) . '/security.env';
foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[$k] = trim($v);
}

require __DIR__ . '/../../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../../db.php';

/* =========================
   REQUEST + AUTH
========================= */
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

$teacherId = (int) $_SESSION['user']['id'];
$schoolId  = (int) $_SESSION['user']['school_id'];

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
   INSERT ANNOUNCEMENT
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

/* =========================
   FETCH EMAIL RECIPIENTS
========================= */
$emails = [];

if ($target === 'parent') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT u.email
        FROM parent_student sp
        JOIN parents p ON p.id = sp.parent_id
        JOIN users u ON u.id = p.user_id
        JOIN students s ON s.user_id = sp.student_id
        WHERE s.class_id = ?
          AND u.email IS NOT NULL
    ");
    $stmt->execute([$classId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

} elseif ($target === 'student') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT u.email
        FROM users u
        JOIN students s ON s.user_id = u.id
        WHERE s.class_id = ?
          AND u.email IS NOT NULL
    ");
    $stmt->execute([$classId]);
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

} elseif ($target === 'all') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT email
        FROM users
        WHERE email IS NOT NULL
    ");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* =========================
   ALWAYS SEND COPY TO TEACHER
========================= */
$teacherEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$teacherEmailStmt->execute([$teacherId]);
$teacherEmail = $teacherEmailStmt->fetchColumn();

if ($teacherEmail) {
    $emails[] = $teacherEmail;
}

/* Remove duplicates */
$emails = array_unique($emails);

/* =========================
   DEBUG
========================= */
error_log('🔥 EMAILS FOUND: ' . print_r($emails, true));

/* =========================
   SEND EMAILS (SMTP)
========================= */
foreach ($emails as $email) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_USER'], 'E-Shkolla');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Njoftim nga shkolla';
        $mail->Body = "
            <h3>" . htmlspecialchars($title) . "</h3>
            <p>" . nl2br(htmlspecialchars($content)) . "</p>
            <hr>
            <small>E-Shkolla</small>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log('MAIL ERROR: ' . $mail->ErrorInfo);
    }
}

/* =========================
   REDIRECT
========================= */
header('Location: /E-Shkolla/teacher-notices');
exit;
