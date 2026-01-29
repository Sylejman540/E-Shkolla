<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';

if (isset($_SESSION['user']['id'])) {
    $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['user']['id']]);
}

if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

$_SESSION = array();
session_destroy();

session_start();
$_SESSION['logout_success'] = true;
header("Location: /E-Shkolla/login");
exit;