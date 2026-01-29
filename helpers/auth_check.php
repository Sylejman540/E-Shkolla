<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user'])) {
    if (isset($_COOKIE['remember_me'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$_COOKIE['remember_me']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'school_id' => (int) $user['school_id'],
                'role' => $user['role']
            ];
        } else {
            setcookie('remember_me', '', time() - 3600, '/');
            header("Location: /E-Shkolla/login"); exit;
        }
    } else {
        header("Location: /E-Shkolla/login"); exit;
    }
}