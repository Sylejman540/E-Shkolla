<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

header('Content-Type: application/json; charset=utf-8');

/* TURN OFF PHP NOISE */
ini_set('display_errors', 0);
error_reporting(0);

/* AUTH */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$schoolId = (int)$_SESSION['user']['school_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM class_schedule
    WHERE id = ? AND school_id = ?
");
$stmt->execute([$id, $schoolId]);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Nothing deleted (ID/school mismatch)'
    ]);
    exit;
}

/* 🔥 CRITICAL: JSON ONLY, NO OUTPUT AFTER THIS */
echo json_encode(['success' => true]);
exit;
