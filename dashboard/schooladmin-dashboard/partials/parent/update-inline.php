<?php
session_start();
require_once '../../../../db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$parentId = (int)($data['userId'] ?? 0);
$field    = $data['field'] ?? '';
$value    = trim($data['value'] ?? '');

if (!$parentId || !$field) {
    http_response_code(400);
    exit;
}

/**
 * Allowed fields
 */
$allowedFields = ['name', 'email', 'phone', 'relation', 'status'];

if (!in_array($field, $allowedFields, true)) {
    http_response_code(400);
    exit;
}

/**
 * Get linked user_id from parents
 */
$stmt = $pdo->prepare("SELECT user_id FROM parents WHERE id = ?");
$stmt->execute([$parentId]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    http_response_code(404);
    exit;
}

$userId = (int)$parent['user_id'];

/**
 * 1️⃣ UPDATE PARENTS TABLE
 */
$stmt = $pdo->prepare("
    UPDATE parents
    SET `$field` = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([$value, $parentId]);

/**
 * 2️⃣ SYNC USERS TABLE (ONLY WHEN NEEDED)
 */
if (in_array($field, ['name', 'email', 'status'], true)) {

    $stmt = $pdo->prepare("
        UPDATE users
        SET `$field` = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$value, $userId]);
}

echo json_encode(['success' => true]);
