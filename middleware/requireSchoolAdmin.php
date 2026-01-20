<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user']['id']) ||
    !isset($_SESSION['user']['role']) ||
    $_SESSION['user']['role'] !== 'school_admin'
) {
    http_response_code(403);
    exit('Nuk keni autorizim për këtë veprim.');
}

if (empty($_SESSION['user']['school_id'])) {
    header('Location: /E-Shkolla/login');
    exit();
}
