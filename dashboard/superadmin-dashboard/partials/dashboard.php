<?php require_once __DIR__ . '/../index.php'; ?>
<?php
// fake data for now (replace later with DB queries)
$totalSchools = 12;
$activeSchools = 9;
$inactiveSchools = 3;

$totalUsers = 320;
$usersByRole = [
    'Super Admin' => 1,
    'School Admin' => 12,
    'Teachers' => 120,
    'Students' => 180,
    'Parents' => 7
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard | E-Shkolla</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100">
<main class="lg:pl-72">
  <div class="xl:pl-18">
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
        <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">SuperAdmin Dashboard</h3>
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Schools</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">71,897</dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">58.16%</dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Active Schools</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">24.57%</dd>
            </div>
        </dl>
        </div>

        <div class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Schools Status -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 h-[420px]">
            <h4 class="mb-4 text-sm font-semibold text-gray-700">
            Schools Status
            </h4>
            <div class="relative h-[320px]">
            <canvas id="schoolsChart"></canvas>
            </div>
        </div>

        <!-- Users by Role -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 h-[420px]">
            <h4 class="mb-4 text-sm font-semibold text-gray-700">
            Users by Role
            </h4>
            <div class="relative h-[320px]">
            <canvas id="usersChart"></canvas>
            </div>
        </div>

        </div>
    </div>
  </div>
</main>

<script>
new Chart(document.getElementById('schoolsChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active Schools', 'Inactive Schools'],
    datasets: [{
      data: [<?= $activeSchools ?>, <?= $inactiveSchools ?>],
      backgroundColor: ['#3b82f6', '#f59e0b'], // blue / amber
      borderWidth: 0
    }]
  },
  options: {
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          boxWidth: 12,
          padding: 20
        }
      }
    }
  }
});

new Chart(document.getElementById('usersChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($usersByRole)) ?>,
    datasets: [{
      label: 'Users',
      data: <?= json_encode(array_values($usersByRole)) ?>,
      backgroundColor: '#6366f1', // indigo
      borderRadius: 8
    }]
  },
  options: {
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#e5e7eb' }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

</script>

</body>
</html>
