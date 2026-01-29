<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// 1. Invalidate the "Remember Me" token in the Database
if (isset($_SESSION['user']['id'])) {
    $userId = (int) $_SESSION['user']['id'];
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$userId]);
}

// 2. Clear the "remember_me" Cookie from the browser
if (isset($_COOKIE['remember_me'])) {
    // Set the expiration date to one hour ago to delete it
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

// 3. Completely destroy the Session
$_SESSION = array(); // Clear all session variables

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 4. Start a fresh session just to show the success message
session_start();
$_SESSION['logout_success'] = true;

// 5. Redirect to login
header("Location: /E-Shkolla/login");
exit;