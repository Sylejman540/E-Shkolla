<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$id = $_GET['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'];

if ($id && $schoolId) {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $schoolId]);
}
header("Location: /E-Shkolla/school-announcement");