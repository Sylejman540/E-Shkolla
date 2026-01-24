<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

/* AUTH */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'schooladmin') {
    http_response_code(403);
    exit('Unauthorized');
}

/* ACCEPT BOTH POST & GET (for now) */
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$schoolId = (int)$_SESSION['user']['school_id'];

if ($id === 0) {
    http_response_code(400);
    exit('Invalid ID');
}

/* DELETE */
$stmt = $pdo->prepare("
    DELETE FROM class_schedule
    WHERE id = ? AND school_id = ?
");

$stmt->execute([$id, $schoolId]);

/* AJAX SAFE */
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    echo json_encode(['success' => true]);
    exit;
}

/* FALLBACK REDIRECT */
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
