<?php
/* =========================
   delete-announcement.php (FINAL)
========================= */
session_start();
require_once __DIR__ . '/../../../../db.php';

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'teacher'
) {
    http_response_code(403);
    exit('Akses i ndaluar');
}

$annId     = (int)($_GET['id'] ?? 0);
$teacherId = (int)$_SESSION['user']['id'];
$schoolId  = (int)$_SESSION['user']['school_id'];

if (!$annId) {
    http_response_code(400);
    exit('ID e pavlefshme');
}

/* =========================
   VERIFY OWNERSHIP + SCHOOL
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

header('Location: /E-Shkolla/teacher-notices');
exit;
