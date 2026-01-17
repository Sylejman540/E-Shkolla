<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db.php'; 

// Total schools
$totalSchools = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();

// Active schools
$stmt = $pdo->prepare("SELECT COUNT(*) FROM schools WHERE status = ?");
$stmt->execute(['Active']);
$activeSchools = $stmt->fetchColumn();

// Inactive schools
$stmt->execute(['Inactive']);
$inactiveSchools = $stmt->fetchColumn();

// Total users
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Users by role
$stmt = $pdo->query("
    SELECT role, COUNT(*) as total
    FROM users
    GROUP BY role
");

$usersByRole = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usersByRole[$row['role']] = $row['total'];
}

ob_start();
?>
<div class="px-4 sm:px-6 lg:px-8">
        <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">SuperAdmin Dashboard</h3>
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Schools</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($activeSchools) ?></dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($totalUsers) ?></dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">Active Schools</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars($activeSchools) ?></dd>
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
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>
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
