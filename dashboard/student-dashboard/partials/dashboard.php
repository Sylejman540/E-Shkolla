<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = $_SESSION['user']['id'] ?? null;
$schoolId  = $_SESSION['user']['school_id'] ?? null;

/* SAFETY */
if (!$studentId || !$schoolId) {
    die('Access denied');
}

/* KPI DATA (example – adjust queries if needed) */
$myClasses          = $myClasses ?? 5;
$attendancePercent  = $attendancePercent ?? 92;
$pendingAssignments = $pendingAssignments ?? 3;
$averageGrade       = $averageGrade ?? 4.2;

/* CHART DATA */
$presentTotal = $presentTotal ?? 120;
$missingTotal = $missingTotal ?? 10;

$assignmentsCompleted = $assignmentsCompleted ?? 18;
$assignmentsPending   = $assignmentsPending ?? 3;

$subjectLabels = $subjectLabels ?? ['Matematikë', 'Gjuhë', 'Biologji', 'Fizikë'];
$subjectGrades = $subjectGrades ?? [4.5, 4.0, 4.2, 3.8];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard | E-Shkolla</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 dark:bg-gray-900">
<main class="lg:pl-72">
<div class="px-4 py-8">

  <!-- HEADER -->
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
    Paneli i Nxënësit
  </h1>

  <!-- KPI CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-8">

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Klasat e mia</p>
      <p class="text-3xl font-bold"><?= $myClasses ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Prezenca</p>
      <p class="text-3xl font-bold"><?= $attendancePercent ?>%</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Detyra në pritje</p>
      <p class="text-3xl font-bold"><?= $pendingAssignments ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Nota mesatare</p>
      <p class="text-3xl font-bold"><?= $averageGrade ?></p>
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
/* Attendance */
new Chart(document.getElementById('attendanceChart'), {
  type: 'doughnut',
  data: {
    labels: ['Prezent', 'Mungesë'],
    datasets: [{
      data: [<?= $presentTotal ?>, <?= $missingTotal ?>],
      backgroundColor: ['#22c55e', '#ef4444'],
      borderWidth: 0
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Prezenca Totale' },
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
      data: [<?= $assignmentsCompleted ?>, <?= $assignmentsPending ?>],
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
    labels: <?= json_encode($subjectLabels) ?>,
    datasets: [{
      label: 'Nota mesatare',
      data: <?= json_encode($subjectGrades) ?>,
      backgroundColor: '#6366f1',
      borderRadius: 6
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Notat sipas Lëndës' },
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true, max: 5 }
    }
  }
});
</script>

</body>
</html>
