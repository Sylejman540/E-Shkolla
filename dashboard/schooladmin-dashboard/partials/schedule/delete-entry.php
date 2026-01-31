<?php
/**
 * Skedari për fshirjen e orës nga orari
 * Kthehet vetëm JSON për të mundësuar shfaqjen e Toast në frontend
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

// Sigurohemi që asnjë gabim PHP të mos shfaqet si tekst (që do të prishte JSON)
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

/* 1. KONTROLLI I AUTORIZIMIT */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    echo json_encode(['success' => false, 'error' => 'Qasje e paautorizuar!']);
    exit;
}

/* 2. KONTROLLI I METODËS DHE TË DHËNAVE */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodë e palejuar (Kërkohet POST).']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$schoolId = (int)($_SESSION['user']['school_id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID e pavlefshme.']);
    exit;
}

/* 3. EKZEKUTIMI I FSHIRJES */
try {
    // Fshijmë vetëm nëse ora i përket shkollës së administratorit (Mbrojtje Cross-School)
    $stmt = $pdo->prepare("DELETE FROM class_schedule WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $schoolId]);

    if ($stmt->rowCount() > 0) {
        // Çdo gjë në rregull
        echo json_encode(['success' => true]);
    } else {
        // Nuk u gjet rreshti ose nuk i përket kësaj shkolle
        echo json_encode(['success' => false, 'error' => 'Elementi nuk u gjet ose nuk keni leje.']);
    }
} catch (PDOException $e) {
    // Gabim në nivel database
    echo json_encode(['success' => false, 'error' => 'Gabim në server: ' . $e->getMessage()]);
}

exit; // Ndërprejmë çdo ekzekutim tjetër