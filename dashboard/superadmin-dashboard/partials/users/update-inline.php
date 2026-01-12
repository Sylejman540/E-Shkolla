<?php
session_start();
require_once '../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$userId = (int) ($data['userId'] ?? 0);
$field  = $data['field'] ?? '';
$value  = trim($data['value'] ?? '');

$allowed = ['name','email','role','status'];
if (!$userId || !in_array($field, $allowed, true)) {
    exit;
}

// Prevent self-lockout
if ($userId === $_SESSION['user']['id'] && ($field === 'role' || $field === 'status')) {
    exit;
}

/**
 * 1️⃣ Update USERS table (always)
 */
$stmt = $pdo->prepare("UPDATE users SET `$field` = ? WHERE id = ?");
$stmt->execute([$value, $userId]);

/**
 * 2️⃣ If status changed → sync role table
 */
if ($field === 'status') {

    // Get user's role
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();

    switch ($role) {
        case 'teacher':
            $stmt = $pdo->prepare(
                "UPDATE teachers SET status = ? WHERE user_id = ?"
            );
            $stmt->execute([$value, $userId]);
            break;

        case 'student':
            $stmt = $pdo->prepare(
                "UPDATE students SET status = ? WHERE user_id = ?"
            );
            $stmt->execute([$value, $userId]);
            break;

        case 'parent':
            $stmt = $pdo->prepare(
                "UPDATE parents SET status = ? WHERE user_id = ?"
            );
            $stmt->execute([$value, $userId]);
            break;
    }
}
