<?php
session_start();
require_once '../../../../db.php';

if (
    !isset($_SESSION['user']) ||
    !in_array($_SESSION['user']['role'], ['super_admin', 'school_admin'], true)
) {
    http_response_code(403);
    exit;
}   



$data = json_decode(file_get_contents('php://input'), true);

$userId = (int) ($data['userId'] ?? 0);
$field  = $data['field'] ?? '';
$value  = trim($data['value'] ?? '');

$allowedStudentsFields = ['phone','gender','class_name','date_birth','status'];
$allowedUserFields    = ['name','email','status'];

if (!$userId) {
    exit;
}

/**
 * 1️⃣ STATUS CHANGE (SYNC USERS + TEACHERS)
 */
if ($field === 'status') {

    // Update USERS (source of truth)
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$value, $userId]);

    // Update TEACHERS mirror
    $stmt = $pdo->prepare(
        "UPDATE students SET status = ? WHERE user_id = ?"
    );
    $stmt->execute([$value, $userId]);

    exit;
}

/**
 * 2️⃣ USER FIELDS (name, email)
 */
if (in_array($field, $allowedUserFields, true)) {

    $stmt = $pdo->prepare("UPDATE users SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $userId]);

    exit;
}

/**
 * 3️⃣ TEACHER-SPECIFIC FIELDS
 */
if (in_array($field, $allowedStudentsFields, true)) {

    $stmt = $pdo->prepare(
        "UPDATE students SET `$field` = ? WHERE user_id = ?"
    );
    $stmt->execute([$value, $userId]);

    exit;
}
