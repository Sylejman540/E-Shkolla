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

if (!$userId || !$field) {
    http_response_code(400);
    exit;
}

$teacherFields = ['phone', 'gender', 'subject_name'];
$userFields    = ['name', 'email', 'status'];
$subjectFields = ['subject_name', 'description', 'status', 'name'];

if ($field === 'status') {

    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$value, $userId]);

    $pdo->prepare("UPDATE teachers SET status = ? WHERE user_id = ?")->execute([$value, $userId]);

    $pdo->prepare("UPDATE subjects SET status = ? WHERE user_id = ?")->execute([$value, $userId]);

    exit;
}

if (in_array($field, $userFields, true)) {

    $pdo->prepare("UPDATE users SET `$field` = ? WHERE id = ?")->execute([$value, $userId]);

    if ($field === 'name') {
        $pdo->prepare("UPDATE teachers SET name = ? WHERE user_id = ?")->execute([$value, $userId]);

        $pdo->prepare("UPDATE subjects SET name = ? WHERE user_id = ?")->execute([$value, $userId]);
    }
    exit;
}

if (in_array($field, $teacherFields, true)) {

    $pdo->prepare("UPDATE teachers SET `$field` = ? WHERE user_id = ?")->execute([$value, $userId]);

    if ($field === 'subject_name') {
        $pdo->prepare("UPDATE subjects SET subject_name = ? WHERE user_id = ?")->execute([$value, $userId]);
    }

    exit;
}

if (in_array($field, $subjectFields, true)) {

    $pdo->prepare("UPDATE subjects SET `$field` = ? WHERE user_id = ?")->execute([$value, $userId]);
    exit;
}

http_response_code(400);
