<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SESSION['user']['role']!=='schooladmin') exit;

$d=json_decode(file_get_contents('php://input'),true);

$pdo->prepare("
UPDATE class_schedule SET day=?, period_number=?
WHERE id=? AND school_id=?
")->execute([
    $d['day'], $d['period'], $d['id'],
    $_SESSION['user']['school_id']
]);

echo json_encode(['success'=>true]);
