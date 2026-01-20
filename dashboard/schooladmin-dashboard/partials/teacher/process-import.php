<?php
error_reporting(0);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../db.php'; 

$schoolId = $_SESSION['user']['school_id'] ?? null;
$jsonData = file_get_contents('php://input');
$teachers = json_decode($jsonData, true);

if (!$teachers || !is_array($teachers)) {
    echo json_encode(["status" => "error", "message" => "Nuk u morën të dhëna të vlefshme."]);
    exit;
}

$successCount = 0;

try {
    $pdo->beginTransaction();

    // 1. Përgatitja e deklaratave SQL bazuar në formën tuaj
    $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', ?)");
    
    $stmtTeacher = $pdo->prepare("INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($teachers as $row) {
        // I kthejmë të gjithë çelësat në shkronja të vogla për siguri
        $cleanRow = array_change_key_case($row, CASE_LOWER);

        // Mapimi i kolonave nga CSV (duhet të përputhen me emrat në tabelë)
        $name    = $cleanRow['name'] ?? $cleanRow['emri'] ?? null;
        $email   = $cleanRow['email'] ?? null;
        $pass    = $cleanRow['password'] ?? $cleanRow['fjalkalimi'] ?? 'Temp123!';
        $phone   = $cleanRow['phone'] ?? $cleanRow['telefoni'] ?? null;
        $gender  = $cleanRow['gender'] ?? $cleanRow['gjinia'] ?? 'male';
        $subject = $cleanRow['subject'] ?? $cleanRow['lenda'] ?? $cleanRow['subject_name'] ?? null;
        $status  = $cleanRow['status'] ?? 'active';

        // Validim minimal
        if (empty($name) || empty($email)) continue;

        // Kontrolli nëse emaili ekziston në 'users'
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) continue; // Anashkalo nëse ekziston

        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

        // A. Insert në tabelën USERS
        $stmtUser->execute([$schoolId, trim($name), trim($email), $hashedPassword, $status]);
        $user_id = $pdo->lastInsertId();

        // B. Insert në tabelën TEACHERS (përfshirë Name dhe Email si në formën tuaj)
        $stmtTeacher->execute([
            $schoolId, 
            $user_id, 
            trim($name), 
            trim($email), 
            $phone, 
            $gender, 
            $subject, 
            $status
        ]);

        $successCount++;
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "imported" => $successCount]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Gabim SQL: " . $e->getMessage()]);
}