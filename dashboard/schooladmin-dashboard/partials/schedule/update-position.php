<?php
require_once __DIR__ . '/../../../../db.php';
header('Content-Type: application/json');

// Merr të dhënat nga JSON body
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id']) && isset($data['day'])) {
    try {
        $stmt = $pdo->prepare("UPDATE class_schedule SET day = ? WHERE id = ?");
        $success = $stmt->execute([$data['day'], $data['id']]);
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nuk u bë asnjë ndryshim.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Të dhëna të paplota.']);
}