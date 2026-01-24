<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SESSION['user']['role']!=='schooladmin') exit;

$pdo->prepare("
DELETE FROM class_schedule WHERE id=? AND school_id=?
")->execute([
    $_POST['id'],
    $_SESSION['user']['school_id']
]);

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
