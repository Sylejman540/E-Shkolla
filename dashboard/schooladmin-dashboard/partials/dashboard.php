<?php require_once __DIR__ . '/../index.php'; ?>
<?php
require_once __DIR__  . '/../../../db.php';   

$totalParents = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE status = ?");
$stmt->execute(['Active']);
$activeParents = $stmt->fetchColumn();

$stmt->execute(['Inactive']);
$inactiveParents = $stmt->fetchColumn();

$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM students GROUP BY status");

$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM teachers GROUP BY status");

$usersByRole = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usersByRole[$row['status']] = $row['total'];
}
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
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Parents</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($totalParents) ?></dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Students</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($totalStudents) ?></dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Teachers</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($totalTeachers) ?></dd>
            </div>
        </dl>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="h-64">
    <canvas id="attendanceChart"></canvas>
  </div>

  <div class="h-64">
    <canvas id="teachersStatusChart"></canvas>
  </div>

  <div class="h-64 md:col-span-2">
    <canvas id="studentsByClassChart"></canvas>
  </div>

  <div class="h-64 md:col-span-2">
    <canvas id="absencesByClassChart"></canvas>
  </div>
</div>

    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // 1. Attendance Today
  new Chart(document.getElementById('attendanceChart'), {
    type: 'doughnut',
    data: {
      labels: ['Prezent', 'Mungesë'],
      datasets: [{
        data: [420, 38],
        backgroundColor: ['#22c55e', '#ef4444'],
        borderWidth: 0
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Pjesëmarrja sot'
        },
        legend: {
          position: 'bottom'
        }
      }
    }
  });

  // 2. Teachers Status
  new Chart(document.getElementById('teachersStatusChart'), {
    type: 'doughnut',
    data: {
      labels: ['Aktiv', 'Jo aktiv'],
      datasets: [{
        data: [42, 3],
        backgroundColor: ['#6366f1', '#e5e7eb'],
        borderWidth: 0
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Statusi i mësuesve'
        },
        legend: {
          position: 'bottom'
        }
      }
    }
  });

  // 3. Students by Class
  new Chart(document.getElementById('studentsByClassChart'), {
    type: 'bar',
    data: {
      labels: ['6A', '6B', '7A', '7B', '8A', '9A'],
      datasets: [{
        label: 'Nxënës',
        data: [28, 30, 27, 29, 31, 26],
        backgroundColor: '#3b82f6',
        borderRadius: 6
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Nxënës sipas klasës'
        },
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });

  // 4. Absences by Class
  new Chart(document.getElementById('absencesByClassChart'), {
    type: 'bar',
    data: {
      labels: ['6A', '6B', '7A', '7B', '8A'],
      datasets: [{
        label: 'Mungesa',
        data: [2, 5, 1, 4, 3],
        backgroundColor: '#f97316',
        borderRadius: 6
      }]
    },
    options: {
      indexAxis: 'y',
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Mungesa sot sipas klasës'
        },
        legend: { display: false }
      },
      scales: {
        x: {
          beginAtZero: true
        }
      }
    }
  });

});
</script>


</body>
</html>
