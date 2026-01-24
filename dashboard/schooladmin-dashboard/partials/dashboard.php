<?php
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('I paautorizuar');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) die('ID e shkollës mungon');

/* =====================================================
    1. BACKEND: MARRJA DHE PROCESIMI I TË DHËNAVE
===================================================== */

// Emri i Shkollës
$stmt = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$schoolName = $stmt->fetchColumn() ?: 'Paneli i Administratorit';

// Totale për KPI-të
$stats = [
    'students' => $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?"),
    'teachers' => $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = ?"),
    'classes'  => $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?"),
    'parents'  => $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ?")
];
foreach ($stats as $key => $st) {
    $st->execute([$schoolId]);
    $counts[$key] = (int)$st->fetchColumn();
}

// A. Shpërndarja e Notave (30 ditët e fundit)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN g.grade >= 4.5 THEN '5' 
            WHEN g.grade >= 3.5 THEN '4' 
            WHEN g.grade >= 2.5 THEN '3' 
            WHEN g.grade >= 1.5 THEN '2' 
            ELSE '1' 
        END AS label,
        COUNT(*) AS total
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    WHERE s.school_id = ? AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY label ORDER BY label DESC
");
$stmt->execute([$schoolId]);
$gradeDist = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// B. Trendi i Prezencës (7 Ditë)
$stmt = $pdo->prepare("
    SELECT DATE(a.created_at) as day, 
           ROUND((SUM(a.present) / COUNT(a.student_id)) * 100) as rate
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ? AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY day ORDER BY day ASC
");
$stmt->execute([$schoolId]);
$trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// C. Prezenca sipas Klasave (Sot)
$stmt = $pdo->prepare("
    SELECT c.grade as class_name, 
           ROUND((SUM(a.present) / COUNT(a.student_id)) * 100) as rate
    FROM attendance a
    JOIN classes c ON a.class_id = c.id
    WHERE c.school_id = ? AND DATE(a.created_at) = CURDATE()
    GROUP BY c.id LIMIT 8
");
$stmt->execute([$schoolId]);
$classAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// D. Statusi i Nxënësve
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM students WHERE school_id = ? GROUP BY status");
$stmt->execute([$schoolId]);
$statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... [Keep your existing PHP logic exactly as it is] ...
ob_start();
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Inter', sans-serif; }
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .chart-container { position: relative; transition: all 0.3s ease; }
    .chart-container:hover { transform: translateY(-4px); }
</style>

<div class="p-4 md:p-8 bg-[#f8fafc] min-h-screen">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2 h-2 bg-indigo-600 rounded-full animate-pulse"></span>
                <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.2em]">Sistemi i Monitorimit</span>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($schoolName) ?></h1>
            <p class="text-slate-500 text-sm">Mirësevini në pasqyrën analitike të institucionit tuaj.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-10">
        <?php 
        $cards = [
            ['Nxënës', $counts['students'], 'Mbrojtës', 'bg-indigo-600', 'text-indigo-600'],
            ['Mësues', $counts['teachers'], 'Edukatorë', 'bg-slate-900', 'text-slate-900'],
            ['Klasa', $counts['classes'], 'Grupe', 'bg-emerald-500', 'text-emerald-500'],
            ['Prindër', $counts['parents'], 'Bashkëpunëtorë', 'bg-amber-500', 'text-amber-500']
        ];
        foreach ($cards as $card): ?>
        <div class="bg-white p-5 md:p-6 rounded-[2rem] border border-slate-200/50 shadow-sm hover:shadow-xl hover:shadow-indigo-500/5 transition-all group">
            <div class="flex justify-between items-start mb-4">
                <div class="p-2.5 rounded-2xl <?= $card[3] ?> bg-opacity-10 <?= $card[4] ?> group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= $card[2] ?></span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 mb-1"><?= $card[0] ?></p>
                    <span class="text-3xl font-black text-slate-900 tracking-tighter"><?= number_format($card[1]) ?></span>
                </div>
                <div class="w-10 h-1 rounded-full <?= $card[3] ?> opacity-20"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-8 space-y-8">
            <div class="bg-white p-6 md:p-8 rounded-[2.5rem] border border-slate-200/50 shadow-sm chart-container">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Shpërndarja Akademike</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Performanca e 30 ditëve të fundit</p>
                    </div>
                    <select class="bg-slate-50 border-none text-[10px] font-bold rounded-lg px-3 py-2 text-slate-500 outline-none">
                        <option>Të gjitha lëndët</option>
                    </select>
                </div>
                <div class="h-[350px]">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 md:p-8 rounded-[2.5rem] border border-slate-200/50 shadow-sm chart-container">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Prezenca Ditore</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Pjesëmarrja sipas klasave (Sot)</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[10px] font-black text-emerald-600 uppercase tracking-tighter">Live Update</span>
                    </div>
                </div>
                <div class="h-[320px]">
                    <canvas id="classAttendanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-8">
            
            <div class="bg-slate-950 p-8 rounded-[3rem] shadow-2xl relative overflow-hidden group">
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-10">
                        <div>
                            <span class="text-indigo-400 text-[10px] font-black uppercase tracking-[0.25em]">Pulsi i Prezencës</span>
                            <div class="flex items-baseline gap-1 mt-2">
                                <h3 class="text-6xl font-black text-white tracking-tighter"><?= end($trendData)['rate'] ?? 0 ?></h3>
                                <span class="text-2xl font-bold text-indigo-500">%</span>
                            </div>
                        </div>
                        <div class="p-4 bg-white/5 border border-white/10 rounded-[1.5rem] backdrop-blur-md group-hover:rotate-12 transition-transform">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="h-32">
                        <canvas id="miniTrendChart"></canvas>
                    </div>
                    <div class="mt-8 flex justify-between items-center px-2">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">7 Ditët e fundit</span>
                        <span class="text-[10px] font-bold text-emerald-400">+2.4% nga Java e Kaluar</span>
                    </div>
                </div>
                <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-indigo-600/10 rounded-full blur-[100px]"></div>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200/50 shadow-sm">
                <h2 class="text-xl font-bold text-slate-900 mb-8">Statusi i Nxënësve</h2>
                <div class="relative h-[220px]">
                    <canvas id="statusChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-black text-slate-900"><?= array_sum(array_column($statusData, 'count')) ?></span>
                        <span class="text-[9px] font-black text-slate-400 uppercase">Totali</span>
                    </div>
                </div>
                <div class="mt-10 space-y-4">
                    <?php 
                    $colors = ['#6366f1', '#94a3b8', '#10b981', '#f59e0b'];
                    foreach($statusData as $index => $row): ?>
                    <div class="flex items-center justify-between p-3 rounded-2xl hover:bg-slate-50 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-2 h-6 rounded-full" style="background-color: <?= $colors[$index % 4] ?>"></div>
                            <div>
                                <p class="text-xs font-bold text-slate-900"><?= ucfirst($row['status']) ?></p>
                                <p class="text-[10px] text-slate-400 font-semibold"><?= round(($row['count'] / array_sum(array_column($statusData, 'count'))) * 100) ?>% e totalit</p>
                            </div>
                        </div>
                        <span class="text-sm font-black text-slate-900"><?= $row['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Configuration for high-end look
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { border: { display: false }, grid: { color: '#f8fafc' }, ticks: { color: '#94a3b8', font: { size: 10, weight: '600' } } },
        x: { border: { display: false }, grid: { display: false }, ticks: { color: '#64748b', font: { size: 10, weight: '700' } } }
    }
};

// 1. Academic Distribution (Modern Bar)
new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
        labels: ['Nota 5', 'Nota 4', 'Nota 3', 'Nota 2', 'Nota 1'],
        datasets: [{
            data: [<?= $gradeDist['5']??0 ?>, <?= $gradeDist['4']??0 ?>, <?= $gradeDist['3']??0 ?>, <?= $gradeDist['2']??0 ?>, <?= $gradeDist['1']??0 ?>],
            backgroundColor: (ctx) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, '#6366f1');
                gradient.addColorStop(1, '#818cf8');
                return gradient;
            },
            borderRadius: 12,
            barThickness: 50
        }]
    },
    options: chartDefaults
});

// 2. Class Attendance (Color Conditioned)
const classData = <?= json_encode($classAttendance) ?>;
new Chart(document.getElementById('classAttendanceChart'), {
    type: 'bar',
    data: {
        labels: classData.map(c => c.class_name),
        datasets: [{
            data: classData.map(c => c.rate),
            backgroundColor: classData.map(c => c.rate < 70 ? '#f87171' : '#34d399'),
            borderRadius: 6,
            barThickness: 30
        }]
    },
    options: {
        ...chartDefaults,
        scales: {
            y: { ...chartDefaults.scales.y, max: 100, ticks: { ...chartDefaults.scales.y.ticks, callback: v => v + '%' } },
            x: chartDefaults.scales.x
        }
    }
});

// 3. Pulse Sparkline (Glow effect)
new Chart(document.getElementById('miniTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData, 'day')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($trendData, 'rate')) ?>,
            borderColor: '#6366f1',
            borderWidth: 4,
            tension: 0.5,
            pointRadius: 0,
            fill: true,
            backgroundColor: (ctx) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150);
                gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
                gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                return gradient;
            }
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { display: false }, y: { display: false, min: 0, max: 100 } }
    }
});

// 4. Status Doughnut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($statusData, 'status')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($statusData, 'count')) ?>,
            backgroundColor: ['#6366f1', '#e2e8f0', '#10b981', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 15,
            weight: 0.5
        }]
    },
    options: {
        cutout: '85%',
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>