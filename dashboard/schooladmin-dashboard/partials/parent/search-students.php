<?php
session_start();
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? 0;
$q = $_GET['q'] ?? '';

if (strlen($q) < 2) { echo json_encode([]); exit; }

// Kërko me emër ose student_id (ndrysho student_id nëse e ke emrin e kolonës ndryshe)
try {
    $stmt = $pdo->prepare("SELECT student_id as id, name FROM students WHERE school_id = ? AND (name LIKE ? OR student_id LIKE ?) LIMIT 8");
    $stmt->execute([$schoolId, "%$q%", "$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo json_encode([]);
}