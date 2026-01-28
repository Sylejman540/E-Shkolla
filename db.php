<?php
date_default_timezone_set('Europe/Tirane');


/* ===============================
   LOAD ENV (ONCE)
================================ */
$envPath = __DIR__ . '/security.env';

if (!file_exists($envPath)) {
    error_log('❌ security.env NOT FOUND at ' . $envPath);
} else {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[$k] = trim($v);
    }
}

/* ===============================
   DATABASE CONFIG (FROM ENV)
================================ */
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'e-shkolla';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';

/* ===============================
   CONNECT
================================ */
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('❌ DB CONNECTION ERROR: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed.');
}
