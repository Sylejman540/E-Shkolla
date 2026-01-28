<?php
// handle_command.php
require_once __DIR__ . '/../../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $schoolId = $_SESSION['user']['school_id'];
    $classId  = (int)$data['class_id'];
    $title    = $data['title'];
    $message  = $data['message'] ?? 'Vijueshmëri e ulët vërejtur sot.';

    // 1. Gjejmë kujdestarin e klasës (user_id e mësuesit që është class_header)
    $stmt = $pdo->prepare("
        SELECT teacher_id FROM classes WHERE id = ? AND school_id = ?
    ");
    $stmt->execute([$classId, $schoolId]);
    $teacherId = $stmt->fetchColumn();

    if ($teacherId) {
        // 2. Ruajmë njoftimin në tabelën e njoftimeve (supozojmë se tabela quhet notifications)
        // Përshtateni emrin e tabelës dhe kolonave sipas databazës tuaj
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            SELECT user_id, ?, ?, 'warning', NOW() 
            FROM teachers WHERE id = ?
        ");
        $stmt->execute([$title, $message, $teacherId]);

        // 3. Regjistrojmë veprimin në log-et e administratorit
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (school_id, action_title, created_at) 
            VALUES (?, ?, NOW())
        ");
        $logStmt->execute([$schoolId, "Njoftuar kujdestari për klasën $classId"]);

        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Kujdestari nuk u gjet']);
    }
}