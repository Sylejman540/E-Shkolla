<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db.php'; 

/** --- 1. DATA AGGREGATION (SQL & PHP) --- **/

// KPI Cards
$totalSchools = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn() ?: 0;
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM schools WHERE status = ?");
$stmt->execute(['Active']);
$activeSchools = $stmt->fetchColumn() ?: 0;

$stmt->execute(['Inactive']);
$inactiveSchools = $stmt->fetchColumn() ?: 0;

// Chart 1: User Distribution
$stmt = $pdo->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
$usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Chart 3: System Growth (Last 6 Months)
$growthQuery = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%b') as month_name, 
        COUNT(*) as count 
    FROM schools 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_name, MONTH(created_at)
    ORDER BY MONTH(created_at) ASC
");
$growthData = $growthQuery->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Chart 4: School Activity Breakdown
$activityQuery = $pdo->query("SELECT status, COUNT(*) as total FROM schools GROUP BY status");
$activityBreakdown = $activityQuery->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8">
        
        <header class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">E-Shkolla Control Center</h1>
                <p class="text-slate-500 font-medium mt-1 text-sm">Real-time system health and institution overview.</p>
            </div>
            <div class="flex gap-3">
                <button class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm active:scale-95">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Reports
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <?php 
            $kpis = [
                ['label' => 'Total Schools', 'value' => $totalSchools, 'color' => 'indigo', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                ['label' => 'Total Users', 'value' => $totalUsers, 'color' => 'blue', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                ['label' => 'Active Schools', 'value' => $activeSchools, 'color' => 'emerald', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Inactive', 'value' => $inactiveSchools, 'color' => 'rose', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z']
            ];
            foreach ($kpis as $kpi): ?>
            <div class="bg-white p-6 rounded-[24px] border border-slate-200/60 shadow-sm hover:shadow-md transition-all">
                <div class="p-2.5 bg-<?= $kpi['color'] ?>-50 w-fit rounded-xl mb-4">
                    <svg class="w-6 h-6 text-<?= $kpi['color'] ?>-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $kpi['icon'] ?>" /></svg>
                </div>
                <h3 class="text-slate-500 text-xs font-bold uppercase tracking-widest"><?= $kpi['label'] ?></h3>
                <p class="text-3xl font-black text-slate-900 mt-1"><?= number_format($kpi['value']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="lg:col-span-2 bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">User Distribution</h2>
                <p class="text-xs text-slate-400 font-medium mb-6">System population by role</p>
                <div class="h-[340px] w-full">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">School Health</h2>
                <p class="text-xs text-slate-400 font-medium mb-6">Active vs Inactive Ratio</p>
                <div class="h-[260px] w-full relative">
                    <canvas id="schoolsChart"></canvas>
                </div>
                <div class="mt-8 space-y-3">
                    <div class="flex justify-between text-xs font-bold uppercase">
                        <span class="text-slate-500 flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-indigo-500"></span> Active</span>
                        <span><?= $totalSchools > 0 ? round(($activeSchools/$totalSchools)*100) : 0 ?>%</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold uppercase">
                        <span class="text-slate-500 flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-200"></span> Inactive</span>
                        <span><?= $totalSchools > 0 ? round(($inactiveSchools/$totalSchools)*100) : 0 ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">System Growth</h2>
                <p class="text-xs text-slate-400 font-medium mb-6">New school registrations (Last 6 Months)</p>
                <div class="h-[300px] w-full">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Activity Breakdown</h2>
                <p class="text-xs text-slate-400 font-medium mb-6">Status attention focus</p>
                <div class="h-[300px] w-full">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

    </main>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php'; // Or your main layout file
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#94a3b8';

// 1. User Distribution
new Chart(document.getElementById('usersChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($usersByRole)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($usersByRole)) ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 8,
            barThickness: 32,
        }]
    },
    options: {
        animation: false,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { borderDash: [5, 5], color: '#f1f5f9', drawBorder: false } },
            x: { grid: { display: false } }
        }
    }
});

// 2. School Health
new Chart(document.getElementById('schoolsChart'), {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Inactive'],
        datasets: [{
            data: [<?= $activeSchools ?>, <?= $inactiveSchools ?>],
            backgroundColor: ['#4f46e5', '#e2e8f0'],
            borderWidth: 0,
            cutout: '80%'
        }]
    },
    options: { animation: false, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

// 3. System Growth
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($growthData)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($growthData)) ?>,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.05)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            borderWidth: 3
        }]
    },
    options: {
        animation: false,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// 4. Activity Breakdown
new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($activityBreakdown)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($activityBreakdown)) ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 6,
            barThickness: 20
        }]
    },
    options: {
        indexAxis: 'y',
        animation: false,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { display: false },
            y: { grid: { display: false }, border: { display: false } }
        }
    }
});
</script>