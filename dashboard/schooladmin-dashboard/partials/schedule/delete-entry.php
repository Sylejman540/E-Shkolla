<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    $stmt = $pdo->prepare("DELETE FROM class_schedule WHERE id = ? AND school_id = ?");
    $success = $stmt->execute([$data['id'], $_SESSION['user']['school_id']]);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
exit;