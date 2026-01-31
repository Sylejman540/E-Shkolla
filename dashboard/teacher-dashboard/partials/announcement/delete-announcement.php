<?php
/* =========================
   delete-announcement.php (FIXED & FINAL)
========================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/* =========================
   AUTH
========================= */
$user = $_SESSION['user'] ?? null;

if (!$user || ($user['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Akses i ndaluar');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];
$annId    = (int)($_GET['id'] ?? 0);

if (!$annId) {
    http_response_code(400);
    exit('ID e pavlefshme');
}

/* =========================
   RESOLVE REAL teacher_id
========================= */
$tStmt = $pdo->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ? AND school_id = ?
    LIMIT 1
");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    http_response_code(403);
    exit('Mësues i pavlefshëm');
}

/* =========================
   VERIFY OWNERSHIP
========================= */
$check = $pdo->prepare("
    SELECT 1
    FROM announcements
    WHERE id = ?
      AND teacher_id = ?
      AND school_id = ?
    LIMIT 1
");
$check->execute([$annId, $teacherId, $schoolId]);

if (!$check->fetchColumn()) {
    http_response_code(403);
    exit('Nuk keni të drejtë ta fshini këtë njoftim');
}

/* =========================
   DELETE
========================= */
$del = $pdo->prepare("
    DELETE FROM announcements
    WHERE id = ?
");
$del->execute([$annId]);

header('Location: /E-Shkolla/teacher-notices?deleted=1');
exit;
