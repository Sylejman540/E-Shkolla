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

$allowedParentsFields = ['academic_year','grade','max_students'];

if (!$userId) {
    exit;
}


if ($field === 'status') {


    $stmt = $pdo->prepare(
        "UPDATE parents SET status = ? WHERE user_id = ?"
    );
    $stmt->execute([$value, $userId]);

    exit;
}


if (in_array($field, $allowedParentsFields, true)) {

    $stmt = $pdo->prepare(
        "UPDATE classes SET `$field` = ? WHERE user_id = ?"
    );
    $stmt->execute([$value, $userId]);

    exit;
}
