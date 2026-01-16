<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../db.php';

/* SESSION */
$parentId = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

/* CHILD SELECTION (via URL or default) */
$studentId = (int)($_GET['student_id'] ?? 0);

if (!$parentId || !$schoolId) {
    die('Access denied');
}

/* OWNERSHIP CHECK — VERY IMPORTANT */
$stmt = $pdo->prepare("
    SELECT s.id
    FROM parent_student ps
    JOIN students s ON s.id = ps.student_id
    WHERE ps.parent_id = ? AND s.id = ?
");
$stmt->execute([$parentId, $studentId]);

if (!$stmt->fetch()) {
    die('Unauthorized child access');
}

/* =========================
   KPI DATA (REPLACE WITH REAL QUERIES)
   ========================= */

$myClasses          = 5;
$attendancePercent  = 92;
$pendingAssignments = 3;
$averageGrade       = 4.2;

/* CHART DATA */
$presentTotal = 120;
$missingTotal = 10;

$assignmentsCompleted = 18;
$assignmentsPending   = 3;

$subjectLabels = ['Matematikë', 'Gjuhë', 'Biologji', 'Fizikë'];
$subjectGrades = [4.5, 4.0, 4.2, 3.8];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Paneli i Prindit | E-Shkolla</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 dark:bg-gray-900">
<main class="lg:pl-72">
<div class="px-4 py-8">

  <!-- HEADER -->
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
    Paneli i Prindit
  </h1>

  <!-- KPI CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-8">

    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow">
      <p class="text-sm text-gray-500">Klasat</p>
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

    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72">
      <canvas id="attendanceChart"></canvas>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72">
      <canvas id="assignmentsChart"></canvas>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow h-72 md:col-span-2">
      <canvas id="gradesChart"></canvas>
    </div>

  </div>

</div>
</main>

<script>
new Chart(attendanceChart, {
  type: 'doughnut',
  data: {
    labels: ['Prezent', 'Mungesë'],
    datasets: [{ data: [<?= $presentTotal ?>, <?= $missingTotal ?>], backgroundColor: ['#22c55e', '#ef4444'] }]
  }
});

new Chart(assignmentsChart, {
  type: 'pie',
  data: {
    labels: ['Dorëzuara', 'Në pritje'],
    datasets: [{ data: [<?= $assignmentsCompleted ?>, <?= $assignmentsPending ?>], backgroundColor: ['#3b82f6', '#f59e0b'] }]
  }
});

new Chart(gradesChart, {
  type: 'bar',
  data: {
    labels: <?= json_encode($subjectLabels) ?>,
    datasets: [{ data: <?= json_encode($subjectGrades) ?>, backgroundColor: '#6366f1', borderRadius: 6 }]
  },
  options: { scales: { y: { beginAtZero: true, max: 5 } } }
});
</script>

</body>
</html>
