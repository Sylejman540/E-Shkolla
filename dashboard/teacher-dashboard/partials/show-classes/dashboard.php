<?php
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);

if (!$classId || !$subjectId || !$schoolId) {
    die('Missing context');
}



/* ================= CLASS & SUBJECT ================= */

$stmt = $pdo->prepare("
    SELECT grade
    FROM classes
    WHERE id = ? AND school_id = ?
");
$stmt->execute([$classId, $schoolId]);
$className = $stmt->fetchColumn() ?: '-';

$stmt = $pdo->prepare("
    SELECT name
    FROM subjects
    WHERE id = ?
");
$stmt->execute([$subjectId]);
$subjectName = $stmt->fetchColumn() ?: '-';

/* ================= LESSON DATE ================= */
/* last attendance record = lesson date */

$stmt = $pdo->prepare("
    SELECT MAX(created_at)
    FROM attendance
    WHERE class_id = ? AND subject_id = ?
");
$stmt->execute([$classId, $subjectId]);
$lessonDate = $stmt->fetchColumn();
$lessonDate = $lessonDate
    ? date('l, d M Y', strtotime($lessonDate))
    : '-';

/* ================= STUDENTS ================= */

$stmt = $pdo->prepare("
    SELECT s.student_id AS id, s.name
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    WHERE sc.class_id = ?
    ORDER BY s.name
");
$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = count($students);

/* ================= ATTENDANCE ================= */

$stmt = $pdo->prepare("
    SELECT
        SUM(present = 1) AS present_count,
        SUM(missing = 1) AS missing_count
    FROM attendance
    WHERE class_id = ?
      AND subject_id = ?
      AND DATE(created_at) = CURDATE()
");
$stmt->execute([$classId, $subjectId]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$presentCount = (int) ($attendance['present_count'] ?? 0);
$missingCount = (int) ($attendance['missing_count'] ?? 0);

/* ================= ACTIVE TASKS ================= */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignments
    WHERE class_id = ?
      AND school_id = ?
      AND (completed_at IS NULL)
");
$stmt->execute([$classId, $schoolId]);
$activeTasks = (int) $stmt->fetchColumn();

/* ================= LESSON TIME ================= */
/* Optional – static or from schedule table */
$lessonTime = '08:00 – 08:45';

// Attendance trend (last 7 days)
$trendStmt = $pdo->prepare("
    SELECT 
        DATE(created_at) AS day,
        ROUND(AVG(present) * 100) AS present_percent
    FROM attendance
    WHERE class_id = ?
      AND subject_id = ?
      AND created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

$trendStmt->execute([$classId, $subjectId]);
$trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceLabels = [];
$attendanceValues = [];

foreach ($trendData as $row) {
    $attendanceLabels[] = date('D', strtotime($row['day']));
    $attendanceValues[] = (int)$row['present_percent'];
}

$assignmentStatsStmt = $pdo->prepare("
    SELECT
        SUM(completed_at IS NOT NULL) AS completed,
        SUM(completed_at IS NULL)     AS pending
    FROM assignments
    WHERE class_id = ?
      AND school_id = ?
");

$assignmentStatsStmt->execute([$classId, $schoolId]);
$assignmentStats = $assignmentStatsStmt->fetch(PDO::FETCH_ASSOC);

$completedAssignments = (int) ($assignmentStats['completed'] ?? 0);
$pendingAssignments   = (int) ($assignmentStats['pending'] ?? 0);

?>  
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Klasa <?= $className ?> – <?= $subjectName ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 dark:bg-gray-900">
<main class="lg:pl-72">
<div class="max-w-6xl px-6 py-6">

  <!-- HEADER -->
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
      Klasa <?= $className ?> · <?= $subjectName ?>
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400">
      <?= $lessonDate ?> · Ora <?= $lessonTime ?>
    </p>
  </div>

  <!-- KPI CARDS -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <p class="text-xs text-gray-500">Nxënës</p>
      <p class="text-xl font-bold"><?= $totalStudents ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <p class="text-xs text-gray-500">Prezente</p>
      <p class="text-xl font-bold text-green-600"><?= $presentCount ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <p class="text-xs text-gray-500">Mungojnë</p>
      <p class="text-xl font-bold text-red-600"><?= $missingCount ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <p class="text-xs text-gray-500">Detyra aktive</p>
      <p class="text-xl font-bold"><?= $activeTasks ?></p>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
        Prezenca – 7 orët e fundit
      </h3>
      <canvas id="attendanceTrend"></canvas>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
        Detyrat
      </h3>
      <canvas id="assignmentChart"></canvas>
    </div>
  </div>

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const attendanceLabels = <?= json_encode($attendanceLabels ?: []) ?>;
  const attendanceValues = <?= json_encode($attendanceValues ?: []) ?>;

  const completedAssignments = <?= (int)$completedAssignments ?>;
  const activeAssignments    = <?= (int)$activeAssignments ?>;

  /* ================= ATTENDANCE TREND ================= */

  const attendanceCanvas = document.getElementById('attendanceTrend');
  if (attendanceCanvas && attendanceLabels.length) {
    new Chart(attendanceCanvas, {
      type: 'line',
      data: {
        labels: attendanceLabels,
        datasets: [{
          data: attendanceValues,
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.15)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: {
              callback: value => value + '%'
            }
          }
        }
      }
    });
  }

  /* ================= ASSIGNMENT CHART ================= */

  const assignmentCanvas = document.getElementById('assignmentChart');
  if (assignmentCanvas) {
    new Chart(assignmentCanvas, {
      type: 'doughnut',
      data: {
        labels: ['Të Përfunduara', 'Aktive'],
        datasets: [{
          data: [completedAssignments, activeAssignments],
          backgroundColor: ['#3b82f6', '#f59e0b']
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }

});
</script>

</body>
</html>
