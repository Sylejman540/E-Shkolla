<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
$schoolId = $user['school_id'] ?? null;

if (!$user || $user['role'] !== 'school_admin' || !$schoolId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$rows = json_decode(file_get_contents('php://input'), true);

if (!is_array($rows) || empty($rows)) {
    echo json_encode(['status' => 'error', 'message' => 'No data']);
    exit;
}

$imported = 0;
$skipped  = 0;

try {
    $pdo->beginTransaction();

    foreach ($rows as $row) {

        $name        = trim($row['name'] ?? '');
        $email       = strtolower(trim($row['email'] ?? ''));
        $password    = $row['password'] ?: 'Teacher123!';
        $phone       = trim($row['phone'] ?? '');
        $gender      = $row['gender'] ?? 'other';
        $status      = $row['status'] ?? 'active';
        $subjectName = trim($row['subject'] ?? '');
        $description = trim($row['description'] ?? '');

        if (!$name || !$email || !$subjectName) {
            $skipped++;
            continue;
        }

        // 1. Check if user already exists
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->execute([$email]);

        if ($checkUser->fetch()) {
            $skipped++;
            continue;
        }

        // 2. Create user
        $stmtUser = $pdo->prepare("
            INSERT INTO users (school_id, name, email, password, role, status)
            VALUES (?, ?, ?, ?, 'teacher', ?)
        ");
        $stmtUser->execute([
            $schoolId,
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $status
        ]);

        $userId = $pdo->lastInsertId();

        // 3. Create teacher
        $stmtTeacher = $pdo->prepare("
            INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtTeacher->execute([
            $schoolId,
            $userId,
            $name,
            $email,
            $phone,
            $gender,
            $subjectName,
            $status
        ]);

        $teacherId = $pdo->lastInsertId();

        // 4. Get or create subject
        $stmtSubject = $pdo->prepare("
            SELECT id FROM subjects
            WHERE school_id = ? AND subject_name = ?
            LIMIT 1
        ");
        $stmtSubject->execute([$schoolId, $subjectName]);
        $subject = $stmtSubject->fetch(PDO::FETCH_ASSOC);

        if ($subject) {
            $subjectId = $subject['id'];
        } else {
            $stmtCreateSubject = $pdo->prepare("
                INSERT INTO subjects (school_id, user_id, name, subject_name, description, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmtCreateSubject->execute([
                $schoolId,
                $userId,
                $name,
                $subjectName,
                $description
            ]);
            $subjectId = $pdo->lastInsertId();
        }

        // 5. Link teacher ↔ subject
        $stmtLink = $pdo->prepare("
            INSERT IGNORE INTO teacher_subjects (school_id, teacher_id, subject_id)
            VALUES (?, ?, ?)
        ");
        $stmtLink->execute([
            $schoolId,
            $teacherId,
            $subjectId
        ]);

        $imported++;
    }

    $pdo->commit();

    echo json_encode([
        'status'   => 'success',
        'imported' => $imported,
        'skipped'  => $skipped
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
