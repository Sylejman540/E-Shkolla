<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$schoolId) {
    die('School ID missing');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ?");
$stmt->execute([$schoolId]);
$totalParents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ? AND status = ?");
$stmt->execute([$schoolId, 'Active']);
$activeParents = $stmt->fetchColumn();

$stmt->execute([$schoolId, 'Inactive']);
$inactiveParents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
$stmt->execute([$schoolId]);
$totalStudents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM students WHERE school_id = ? GROUP BY status");
$stmt->execute([$schoolId]);

$studentsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = ?");
$stmt->execute([$schoolId]);
$totalTeachers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM teachers WHERE school_id = ? GROUP BY status");
$stmt->execute([$schoolId]);

$teachersByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* ===============================
   ATTENDANCE TODAY
================================ */
$attendanceStmt = $pdo->prepare("
    SELECT present, COUNT(*) AS total
    FROM attendance
    WHERE school_id = ?
      AND id IN (
          SELECT MAX(id)
          FROM attendance
          WHERE school_id = ?
          GROUP BY student_id
      )
    GROUP BY present
");
$attendanceStmt->execute([$schoolId, $schoolId]);

$present = 0;
$absent  = 0;

foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['present'] ? $present = $row['total'] : $absent = $row['total'];
}

/* ===============================
   TEACHERS STATUS
================================ */
$activeTeachers   = $teachersByStatus['Active']   ?? 0;
$inactiveTeachers = $teachersByStatus['Inactive'] ?? 0;

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5">
          <div class="h-64">
            <canvas id="attendanceChart"></canvas>
          </div>

          <div class="h-64">
            <canvas id="teachersStatusChart"></canvas>
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
        data: [
          <?= (int)$present ?>,
          <?= (int)$absent ?>
        ],
        backgroundColor: ['#6366f1', '#ef4444'],
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
        data: [
          <?= (int)$activeTeachers ?>,
          <?= (int)$inactiveTeachers ?>
        ],
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
});
</script>


</body>
</html>
