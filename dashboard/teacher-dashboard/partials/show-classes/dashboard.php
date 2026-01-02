<?php
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../../../../db.php';

/* ===== DEMO DATA (replace later with real queries) ===== */
$className   = 'X/3';
$subjectName = 'Gjeografi';
$lessonTime  = '08:00 – 08:45';
$lessonDate  = 'E Hënë, 02 Janar 2026';

$totalStudents = 20;
$presentCount  = 18;
$missingCount  = 2;
$activeTasks   = 2;

/* Demo students */
$students = [
    ['id' => 1, 'name' => 'Ana Krasniqi'],
    ['id' => 2, 'name' => 'Jon Dervishi'],
    ['id' => 3, 'name' => 'Sara Berisha'],
];
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

  <!-- TABS -->
  <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
    <nav class="flex gap-6 text-sm font-medium">
      <span class="pb-3 border-b-2 border-indigo-600 text-indigo-600">
        Prezenca
      </span>
      <span class="pb-3 text-gray-400">Nxënësit</span>
      <span class="pb-3 text-gray-400">Detyrat</span>
      <span class="pb-3 text-gray-400">Shënime</span>
    </nav>
  </div>

  <!-- ATTENDANCE TABLE -->
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-10">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-4 py-3 text-left">Nxënësi</th>
          <th class="px-4 py-3 text-left">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y dark:divide-gray-700">

        <?php foreach ($students as $s): ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
          <td class="px-4 py-3 text-gray-900 dark:text-white">
            <?= htmlspecialchars($s['name']) ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200">
                Prezent
              </button>
              <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-red-100 text-red-800 hover:bg-red-200">
                Mungon
              </button>
              <button class="px-3 py-1.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200">
                Vonë
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

      </tbody>
    </table>
  </div>

  <!-- CHARTS SECTION -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Attendance Trend -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
      <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
        Prezenca – 7 orët e fundit
      </h3>
      <canvas id="attendanceTrend"></canvas>
    </div>

    <!-- Assignment Completion -->
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
/* Attendance Trend */
new Chart(document.getElementById('attendanceTrend'), {
  type: 'line',
  data: {
    labels: ['Hën','Mar','Mër','Enj','Pre','Hën','Mar'],
    datasets: [{
      data: [92, 90, 88, 91, 94, 93, 95],
      borderColor: '#22c55e',
      backgroundColor: 'rgba(34,197,94,0.15)',
      tension: 0.4,
      fill: true
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { min: 0, max: 100 } }
  }
});

/* Assignment Completion */
new Chart(document.getElementById('assignmentChart'), {
  type: 'doughnut',
  data: {
    labels: ['Dorëzuara', 'Pa dorëzuara'],
    datasets: [{
      data: [16, 4],
      backgroundColor: ['#3b82f6', '#f59e0b']
    }]
  },
  options: {
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>

</body>
</html>
