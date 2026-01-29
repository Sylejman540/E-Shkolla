<?php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_SESSION['user'])) {
    $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")
        ->execute([session_id()]);
}

if (isset($_COOKIE['remember_token'])) {
    $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
        ->execute([hash('sha256', $_COOKIE['remember_token'])]);

    setcookie('remember_token', '', time() - 3600, '/');
}

session_destroy();
header('Location: /E-Shkolla/login');
exit;
