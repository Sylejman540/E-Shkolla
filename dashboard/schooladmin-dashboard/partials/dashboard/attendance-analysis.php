<?php
/**
 * E-Shkolla — Attendance Drill-Down (Root Cause Analysis)
 * Version: V2.A (Diagnosis Engine)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =======================
   AUTH & INPUT
======================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'I paautorizuar']);
    exit;
}

$schoolId = (int)($_SESSION['user']['school_id'] ?? 0);
$classId  = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

if (!$schoolId || !$classId) {
    http_response_code(400);
    echo json_encode(['error' => 'Mungon ID e shkollës ose klasës']);
    exit;
}

/* =======================
   DATA EXTRACTION
======================= */

/* A. Chronic absentees */
$stmt = $pdo->prepare("
    SELECT 
        s.name,
        COUNT(*) AS absences,
        MAX(a.created_at) AS last_date
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    JOIN student_class sc ON sc.student_id = s.student_id
    WHERE sc.class_id = ?
      AND s.school_id = ?
      AND a.present = 0
      AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY s.student_id
    HAVING absences >= 2
    ORDER BY absences DESC
");
$stmt->execute([$classId, $schoolId]);
$recurrentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* B. Subject-level patterns */
$stmt = $pdo->prepare("
    SELECT 
        sub.subject_name,
        ROUND((SUM(a.present) / COUNT(*)) * 100) AS rate
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.class_id = ?
      AND a.school_id = ?
      AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY sub.id
    ORDER BY rate ASC
");
$stmt->execute([$classId, $schoolId]);
$subjectPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* C. Timeline */
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) AS day,
        ROUND((SUM(present) / COUNT(*)) * 100) AS rate
    FROM attendance
    WHERE class_id = ?
      AND school_id = ?
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->execute([$classId, $schoolId]);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================
   DIAGNOSIS LOGIC
======================= */

/* Class size */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM student_class 
    WHERE class_id = ?
");
$stmt->execute([$classId]);
$classSize = max(1, (int)$stmt->fetchColumn());

$avgRate = count($timeline)
    ? array_sum(array_column($timeline, 'rate')) / count($timeline)
    : 100;

/* Problematic subjects */
$problematicSubjects = array_filter($subjectPatterns, function ($s) use ($avgRate) {
    return $s['rate'] < ($avgRate - 15);
});

$pattern = 'isolated';
$summary = 'Vijueshmëria paraqitet e qëndrueshme me raste sporadike.';

if (!empty($problematicSubjects)) {
    $pattern = 'subject_specific';
    $names = implode(', ', array_column(array_slice($problematicSubjects, 0, 2), 'subject_name'));
    $summary = "Problemi lidhet kryesisht me lëndë specifike: {$names}.";
} elseif (count($recurrentStudents) > ($classSize * 0.25)) {
    $pattern = 'systematic';
    $summary = 'Problemi është sistematik: një pjesë e konsiderueshme e klasës mungon rregullisht.';
} elseif (!empty($recurrentStudents)) {
    $pattern = 'chronic_absence';
    $summary = 'Problemi është i përqendruar te disa nxënës me mungesa të përsëritura.';
}

/* =======================
   RESPONSE
======================= */
echo json_encode([
    'summary'  => $summary,
    'pattern'  => $pattern,
    'students' => array_map(fn($s) => [
        'name'     => $s['name'],
        'absences' => (int)$s['absences']
    ], $recurrentStudents),
    'subjects' => array_map(fn($s) => [
        'name' => $s['subject_name'],
        'rate' => (int)$s['rate']
    ], array_slice($problematicSubjects, 0, 3)),
    'timeline' => array_map(fn($t) => [
        'date' => $t['day'],
        'rate' => (int)$t['rate']
    ], $timeline),
    'confidence' => count($timeline) >= 4 ? 'high' : 'medium'
]);
