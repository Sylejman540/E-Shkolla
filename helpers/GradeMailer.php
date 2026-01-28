<?php

require_once __DIR__ . '/ParentEmails.php';
require_once __DIR__ . '/Mailer.php';

function sendGradeNotification(
    PDO $pdo,
    int $studentId,
    int $classId,
    int $subjectId,
    string $type,
    $value
): void {

    // Async → never blocks saving
    register_shutdown_function(function () use (
        $pdo,
        $studentId,
        $classId,
        $subjectId,
        $type,
        $value
    ) {

        /* ===============================
           STUDENT INFO + EMAIL
        ================================ */
        $stmt = $pdo->prepare("
            SELECT name, email
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) return;

        $studentName  = $student['name'];
        $studentEmail = $student['email'] ?? null;

        /* ===============================
           SUBJECT
        ================================ */
        $stmt = $pdo->prepare("
            SELECT subject_name
            FROM subjects
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$subjectId]);
        $subjectName = $stmt->fetchColumn() ?: 'Lëndë';

        /* ===============================
           TEACHER NAME (NEW)
        ================================ */
        $stmt = $pdo->prepare("
            SELECT u.name
            FROM teachers t
            JOIN users u ON u.id = t.user_id
            WHERE t.school_id = (
                SELECT school_id FROM students WHERE student_id = ?
            )
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $teacherName = $stmt->fetchColumn() ?: 'Mësuesi';

        /* ===============================
           PARENT EMAILS
        ================================ */
        $parentEmails = getParentEmailsByStudent($studentId, $pdo);

        /* ===============================
           MERGE RECIPIENTS
        ================================ */
        $recipients = [];

        if (!empty($parentEmails)) {
            $recipients = array_merge($recipients, $parentEmails);
        }

        if ($studentEmail) {
            $recipients[] = $studentEmail;
        }

        // Remove duplicates
        $recipients = array_values(array_unique($recipients));

        if (empty($recipients)) return;

        /* ===============================
           LABELS
        ================================ */
        $labels = [
            'p1_test'     => 'Test (Periudha I)',
            'p1_activity' => 'Aktivitet (Periudha I)',
            'p1_oral'     => 'Me Goje (Periudha I)',
            'p1_project'  => 'Projekt (Periudha I)',
            'p1_homework' => 'Detyrë (Periudha I)',

            'p2_test'     => 'Test (Periudha II)',
            'p2_activity' => 'Aktivitet (Periudha II)',
            'p2_oral'     => 'Me Goje (Periudha II)',
            'p2_project'  => 'Projekt (Periudha II)',
            'p2_homework' => 'Detyrë (Periudha II)',

            'grade'       => 'Nota Vjetore'
        ];

        $typeLabel = $labels[$type] ?? 'Vlerësim';

        /* ===============================
           EMAIL BODY
        ================================ */
        $body = "
        <div style='font-family:Arial,sans-serif;font-size:14px'>
            <p><strong>Është regjistruar një vlerësim i ri</strong></p>

            <p>
                Nxënësi: <strong>{$studentName}</strong><br>
                Lënda: <strong>{$subjectName}</strong><br>
                Mësuesi: <strong>{$teacherName}</strong><br>
                Lloji: <strong>{$typeLabel}</strong><br>
                Nota: <strong>{$value}</strong>
            </p>

            <hr>
            <small>E-Shkolla • Njoftim automatik</small>
        </div>
        ";

        /* ===============================
           SEND EMAIL
        ================================ */
        sendSchoolEmail(
            $recipients,
            'Njoftim për vlerësim',
            $body
        );
    });
}
