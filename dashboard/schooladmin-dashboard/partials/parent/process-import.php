<?php
declare(strict_types=1);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
$parents = json_decode(file_get_contents('php://input'), true);

if (!$schoolId || !is_array($parents)) {
    echo json_encode(['status' => 'error', 'message' => 'Sesioni ka skaduar ose të dhënat janë gabim.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Mapimi në memorie për performancë
    $stmt = $pdo->prepare("SELECT email, id FROM users WHERE school_id = ?");
    $stmt->execute([$schoolId]);
    $userMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'parent', 'active')");
    $stmtParent = $pdo->prepare("INSERT INTO parents (school_id, user_id, name, email, phone, relation) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtLink = $pdo->prepare("INSERT IGNORE INTO parent_student (school_id, parent_id, student_id) VALUES (?, ?, ?)");

    $count = 0;
    foreach ($parents as $row) {
        $email = strtolower(trim($row['email']));
        $studentId = (int)$row['student_id'];

        // 1. User
        if (isset($userMap[$email])) {
            $userId = $userMap[$email];
        } else {
            $pass = password_hash($row['password'] ?: 'Prindi123!', PASSWORD_DEFAULT);
            $stmtUser->execute([$schoolId, $row['name'], $email, $pass]);
            $userId = (int)$pdo->lastInsertId();
            $userMap[$email] = $userId;
        }

        // 2. Parent Profile
        $stmtCheckP = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
        $stmtCheckP->execute([$userId, $schoolId]);
        $parentId = $stmtCheckP->fetchColumn();

        if (!$parentId) {
            $stmtParent->execute([$schoolId, $userId, $row['name'], $email, $row['phone'] ?? '', $row['relation'] ?? 'other']);
            $parentId = (int)$pdo->lastInsertId();
        }

        // 3. Link Student
        $stmtLink->execute([$schoolId, $parentId, $studentId]);
        $count++;
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'imported' => $count]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}