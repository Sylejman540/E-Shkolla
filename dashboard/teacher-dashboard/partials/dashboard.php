<?php
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$teacherId = (int) ($_SESSION['user']['id'] ?? 0);
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);

if (!$teacherId || !$schoolId) {
    die('Invalid session');
}

/* ================= KPI DATA ================= */

// My classes
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT class_id)
    FROM class_schedule
    WHERE teacher_id = ?
      AND school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$myClasses = (int) $stmt->fetchColumn();

// Total students (unique)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sc.student_id)
    FROM student_class sc
    JOIN classes c ON c.id = sc.class_id
    WHERE c.school_id = ?
");
$stmt->execute([$schoolId]);
$totalStudents = (int) $stmt->fetchColumn();

// Today lessons
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM class_schedule
    WHERE teacher_id = ?
      AND school_id = ?
      AND day = DAYNAME(CURDATE())
");
$stmt->execute([$teacherId, $schoolId]);
$todayLessons = (int) $stmt->fetchColumn();

// Pending assignments
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignments
    WHERE teacher_id = ?
      AND school_id = ?
      AND completed_at IS NULL
");
$stmt->execute([$teacherId, $schoolId]);
$pendingTasks = (int) $stmt->fetchColumn();

/* ================= CHART DATA ================= */

// Attendance today
$stmt = $pdo->prepare("
    SELECT
        SUM(present = 1) AS present,
        SUM(missing = 1) AS missing
    FROM attendance
    WHERE teacher_id = ?
      AND school_id = ?
      AND DATE(created_at) = CURDATE()
");
$stmt->execute([$teacherId, $schoolId]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$presentToday = (int) ($attendance['present'] ?? 0);
$missingToday = (int) ($attendance['missing'] ?? 0);

// Assignments completion
$stmt = $pdo->prepare("
    SELECT
        SUM(completed_at IS NOT NULL) AS completed,
        SUM(completed_at IS NULL)     AS pending
    FROM assignments
    WHERE teacher_id = ?
      AND school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$assignments = $stmt->fetch(PDO::FETCH_ASSOC);

$assignmentsCompleted = (int) ($assignments['completed'] ?? 0);
$assignmentsPending   = (int) ($assignments['pending'] ?? 0);

// Grades by class
$stmt = $pdo->prepare("
    SELECT c.grade AS class_name, ROUND(AVG(g.grade), 2) AS avg_grade
    FROM grades g
    JOIN classes c ON c.id = g.class_id
    WHERE g.teacher_id = ?
      AND g.school_id = ?
    GROUP BY c.grade
");
$stmt->execute([$teacherId, $schoolId]);
$gradesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gradeLabels = [];
$gradeValues = [];

foreach ($gradesData as $row) {
    $gradeLabels[] = $row['class_name'];
    $gradeValues[] = (float) $row['avg_grade'];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Teacher Dashboard | E-Shkolla</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 dark:bg-gray-900">
<main class="lg:pl-72">
<div class="px-4 py-8">

  <!-- HEADER -->
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
    Paneli i Mësuesit
  </h1>

  <!-- KPI CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Klasat e mia</p>
      <p class="text-3xl font-bold"><?= $myClasses ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Nxënës gjithsej</p>
      <p class="text-3xl font-bold"><?= $totalStudents ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Ora sot</p>
      <p class="text-3xl font-bold"><?= $todayLessons ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Detyra në pritje</p>
      <p class="text-3xl font-bold"><?= $pendingTasks ?></p>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Attendance -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72">
      <canvas id="attendanceChart"></canvas>
    </div>

    <!-- Assignments -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72">
      <canvas id="assignmentsChart"></canvas>
    </div>

    <!-- Grades -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72 md:col-span-2">
      <canvas id="gradesChart"></canvas>
    </div>

  </div>

</div>
</main>

<script>
const attendanceData = {
  present: <?= $presentToday ?>,
  missing: <?= $missingToday ?>
};

const assignmentData = {
  completed: <?= $assignmentsCompleted ?>,
  pending: <?= $assignmentsPending ?>
};

const gradeLabels = <?= json_encode($gradeLabels) ?>;
const gradeValues = <?= json_encode($gradeValues) ?>;

/* Attendance */
new Chart(document.getElementById('attendanceChart'), {
  type: 'doughnut',
  data: {
    labels: ['Prezent', 'Mungesë'],
    datasets: [{
      data: [attendanceData.present, attendanceData.missing],
      backgroundColor: ['#22c55e', '#ef4444'],
      borderWidth: 0
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Prezenca Sot' },
      legend: { position: 'bottom' }
    }
  }
});

/* Assignments */
new Chart(document.getElementById('assignmentsChart'), {
  type: 'pie',
  data: {
    labels: ['Dorëzuara', 'Në pritje'],
    datasets: [{
      data: [assignmentData.completed, assignmentData.pending],
      backgroundColor: ['#3b82f6', '#f59e0b']
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Detyrat' },
      legend: { position: 'bottom' }
    }
  }
});

/* Grades */
new Chart(document.getElementById('gradesChart'), {
  type: 'bar',
  data: {
    labels: gradeLabels,
    datasets: [{
      label: 'Nota mesatare',
      data: gradeValues,
      backgroundColor: '#6366f1',
      borderRadius: 6
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Notat Mesatare sipas Klasës' },
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        max: 5
      }
    }
  }
});
</script>


</body>
</html>
