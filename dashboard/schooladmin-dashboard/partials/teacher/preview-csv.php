<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId || empty($_FILES['csv'])) {
    echo json_encode(['error' => 'Pa autorizim']); exit;
}

$file = fopen($_FILES['csv']['tmp_name'], 'r');
$header = fgetcsv($file);
if ($header) {
    $header[0] = preg_replace('/^[\xEF\xBB\xBF]+/', '', $header[0]);
    $header = array_map('trim', $header);
}

$rows = [];
$validRows = [];

while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 7) continue;
    $row = array_map('trim', $row);
    [$name, $email, $phone, $gender, $subject, $grade, $status] = $row;
    
    $errors = [];
    // Kontrolli i emailit
    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $emailCheck->execute([$email]);
    if ($emailCheck->fetch()) { $errors[] = 'Email ekziston'; }

    // Kontrolli i klasës
    $classCheck = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ? LIMIT 1");
    $classCheck->execute([$grade, $schoolId]);
    if (!$classCheck->fetch()) { $errors[] = 'Klasa s\'ekziston'; }

    $rowData = ['data' => $row, 'errors' => $errors];
    $rows[] = $rowData;
    if (empty($errors)) { $validRows[] = $rowData; }
}
fclose($file);

// RUAJTJA NË SESION PËR HAPIN FINAL
$_SESSION['csv_rows'] = $validRows;

echo json_encode([
    'rows' => $rows,
    'valid_count' => count($validRows)
]);