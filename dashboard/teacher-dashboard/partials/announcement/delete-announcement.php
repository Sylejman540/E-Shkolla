<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

$id = $_GET['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'];

if ($id && $schoolId) {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $schoolId]);
}
header("Location: /E-Shkolla/teacher-notices");