<?php
declare(strict_types=1);

/* ===============================
    DEBUG & JSON RESPONSE
================================ */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

/* ===============================
    SAFETY NET
================================ */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Fatal error: ' . $error['message']
        ]);
    }
});

/* ===============================
    AUTH CHECK
================================ */
$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) {
    echo json_encode(['status' => 'error', 'message' => 'Sesioni ka skaduar.']);
    exit;
}

$raw = file_get_contents('php://input');
$students = json_decode($raw, true);

if (!is_array($students)) {
    echo json_encode(['status' => 'error', 'message' => 'JSON i pavlefshëm.']);
    exit;
}

$imported = 0;
$skipped  = 0;

try {
    $pdo->beginTransaction();

    // Cache classes for this school to get grade names
    $stmtClassMap = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
    $stmtClassMap->execute([$schoolId]);
    $classMap = $stmtClassMap->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'student', ?)");
    
    $stmtStudent = $pdo->prepare("INSERT INTO students (school_id, user_id, student_code, name, gender, class_name, date_birth, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmtLink = $pdo->prepare("INSERT INTO student_class (school_id, student_id, class_id) VALUES (?, ?, ?)");

    foreach ($students as $row) {
        /* Normalize keys */
        $r = [];
        foreach ($row as $k => $v) {
            $r[strtolower(trim((string)$k))] = trim((string)$v);
        }

        $name       = $r['name'] ?? '';
        $email      = strtolower($r['email'] ?? '');
        $pass       = $r['password'] ?? 'Nxenesi2026!';
        $classId    = (int)($r['class_id'] ?? 0);
        $gender     = $r['gender'] ?? 'other';
        $birthday   = $r['date_birth'] ?? $r['birthday'] ?? null;
        $status     = $r['status'] ?? 'active';
        $stuCode    = $r['student_code'] ?? '';

        // Validation
        if (!$name || !$email || !$classId || !$birthday) {
            $skipped++;
            continue;
        }

        /* Prevent duplicate users */
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $skipped++;
            continue;
        }

        // Auto-generate student code if empty
        if (empty($stuCode)) {
            $stuCode = "STU-" . date("Y") . "-" . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        try {
            /* 1. User Table */
            $stmtUser->execute([$schoolId, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $status]);
            $userId = (int)$pdo->lastInsertId();

            /* 2. Student Table */
            $className = $classMap[$classId] ?? 'E pacaktuar';
            $stmtStudent->execute([$schoolId, $userId, $stuCode, $name, $gender, $className, $birthday, $email, $status]);
            $studentId = (int)$pdo->lastInsertId();

            /* 3. Link Table */
            $stmtLink->execute([$schoolId, $studentId, $classId]);

            $imported++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }

    $pdo->commit();
    echo json_encode([
        'status'   => 'success',
        'imported' => $imported,
        'skipped'  => $skipped,
        'message'  => 'Procesi u përfundua.'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}