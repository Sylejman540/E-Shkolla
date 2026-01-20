<?php
declare(strict_types=1);

/* ===============================
   DEBUG (disable later in prod)
================================ */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===============================
   JSON RESPONSE GUARANTEE
================================ */
header('Content-Type: application/json');

/* ===============================
   SESSION
================================ */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===============================
   DB CONNECTION
================================ */
require_once __DIR__ . '/../../../../db.php';

/* ===============================
   SAFETY NET FOR FATAL ERRORS
================================ */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
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
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesioni ka skaduar. Ju lutem kyçuni përsëri.'
    ]);
    exit;
}

/* ===============================
   READ JSON INPUT
================================ */
$raw = file_get_contents('php://input');
$teachers = json_decode($raw, true);

if (!is_array($teachers)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Të dhënat nuk janë valide (JSON i pavlefshëm).'
    ]);
    exit;
}

/* ===============================
   COUNTERS
================================ */
$imported = 0;
$skipped  = 0;

/* ===============================
   PREPARED STATEMENTS
================================ */
try {
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("
        INSERT INTO users (school_id, name, email, password, role, status)
        VALUES (?, ?, ?, ?, 'teacher', ?)
    ");

    $stmtTeacher = $pdo->prepare("
        INSERT INTO teachers
        (school_id, user_id, name, email, phone, gender, description, subject_name, status, profile_photo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtSubject = $pdo->prepare("
        INSERT INTO subjects
        (school_id, user_id, name, subject_name, description, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmtLink = $pdo->prepare("
        INSERT INTO teacher_class
        (school_id, teacher_id, class_id, subject_id)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($teachers as $row) {

        /* Normalize keys */
        $r = [];
        foreach ($row as $k => $v) {
            $r[strtolower(trim($k))] = trim((string)$v);
        }

        $name   = $r['name'] ?? '';
        $email  = strtolower($r['email'] ?? '');
        $phone  = $r['phone'] ?? '';
        $class  = (int)($r['class_id'] ?? 0);
        $gender = $r['gender'] ?? 'other';
        $subj   = $r['subject'] ?? 'E pacaktuar';
        $status = $r['status'] ?? 'active';
        $desc   = $r['description'] ?? '';
        $pass   = $r['password'] ?? 'Temp123!';
        $photo  = $r['profile_photo'] ?? 'assets/img/default-avatar.png';

        if (!$name || !$email || !$class) {
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

        try {
            /* User */
            $stmtUser->execute([
                $schoolId,
                $name,
                $email,
                password_hash($pass, PASSWORD_DEFAULT),
                $status
            ]);
            $userId = $pdo->lastInsertId();

            /* Teacher */
            $stmtTeacher->execute([
                $schoolId, $userId, $name, $email,
                $phone, $gender, $desc, $subj, $status, $photo
            ]);
            $teacherId = $pdo->lastInsertId();

            /* Subject */
            $stmtSubject->execute([
                $schoolId, $userId, $name, $subj, $desc, $status
            ]);
            $subjectId = $pdo->lastInsertId();

            /* Link */
            $stmtLink->execute([
                $schoolId, $teacherId, $class, $subjectId
            ]);

            $imported++;

        } catch (Throwable $rowError) {
            error_log('IMPORT ROW ERROR: ' . $rowError->getMessage());
            $skipped++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'imported' => $imported,
        'skipped'  => $skipped,
        'message'  => 'Importimi përfundoi me sukses.'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Gabim serveri: ' . $e->getMessage()
    ]);
}
