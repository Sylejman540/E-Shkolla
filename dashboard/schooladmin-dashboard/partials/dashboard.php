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

ob_start();
?>

<div class="p-6 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($schoolName) ?></h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pasqyra e performancës së përgjithshme</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <?php 
        $cards = [
            ['Nxënës', $counts['students'], 'bg-indigo-600'],
            ['Mësues', $counts['teachers'], 'bg-slate-800'],
            ['Klasa', $counts['classes'], 'bg-emerald-500'],
            ['Prindër', $counts['parents'], 'bg-amber-500']
        ];
        foreach ($cards as $card): ?>
        <div class="bg-white p-6 rounded-[28px] border border-slate-200/60 shadow-sm hover:shadow-md transition-shadow">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2"><?= $card[0] ?></p>
            <div class="flex items-center justify-between">
                <span class="text-3xl font-black text-slate-900"><?= number_format($card[1]) ?></span>
                <div class="w-1.5 h-8 <?= $card[2] ?> rounded-full"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 italic">Shpërndarja Akademike</h2>
                        <p class="text-xs text-slate-400 font-medium uppercase tracking-tighter">Analiza e notave të muajit të fundit</p>
                    </div>
                    <div class="h-2 w-12 bg-indigo-100 rounded-full"></div>
                </div>
                <div class="h-[320px]">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-xl font-bold text-slate-900">Pjesëmarrja sipas Klasave (Sot)</h2>
                    <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-full">LIVE DATA</span>
                </div>
                <div class="h-[300px]">
                    <canvas id="classAttendanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            
            <div class="bg-slate-900 text-white p-8 rounded-[40px] shadow-2xl relative overflow-hidden">
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Pulsi i Prezencës</h2>
                            <p class="text-5xl font-black mt-2"><?= end($trendData)['rate'] ?? 0 ?><span class="text-indigo-400">%</span></p>
                        </div>
                        <div class="p-3 bg-white/5 rounded-2xl border border-white/10">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <div class="h-32">
                        <canvas id="miniTrendChart"></canvas>
                    </div>
                    <p class="text-center text-[10px] text-slate-500 font-bold uppercase mt-6 tracking-widest">Trendi 7-Ditor</p>
                </div>
                <div class="absolute -right-16 -top-16 w-48 h-48 bg-indigo-600/20 rounded-full blur-[80px]"></div>
            </div>

            <div class="bg-white p-8 rounded-[32px] border border-slate-200/60 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900 mb-8">Statusi i Regjistrimit</h2>
                <div class="h-[240px]">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-8 space-y-3">
                    <?php 
                    $colors = ['#6366f1', '#94a3b8', '#10b981', '#f59e0b'];
                    foreach($statusData as $index => $row): ?>
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?= $colors[$index % 4] ?>"></div>
                            <span class="font-medium text-slate-600"><?= ucfirst($row['status']) ?></span>
                        </div>
                        <span class="font-bold text-slate-900"><?= $row['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 1. Shpërndarja e Notave
new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
        labels: ['Nota 5', 'Nota 4', 'Nota 3', 'Nota 2', 'Nota 1'],
        datasets: [{
            data: [<?= $gradeDist['5']??0 ?>, <?= $gradeDist['4']??0 ?>, <?= $gradeDist['3']??0 ?>, <?= $gradeDist['2']??0 ?>, <?= $gradeDist['1']??0 ?>],
            backgroundColor: '#6366f1',
            borderRadius: 15,
            barThickness: 45
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: '#f1f5f9', drawBorder: false }, ticks: { font: { size: 11, weight: 'bold' }, color: '#94a3b8' } },
            x: { grid: { display: false } }
        }
    }
});

// 2. Prezenca sipas Klasave (Dinamike)
const classData = <?= json_encode($classAttendance) ?>;
new Chart(document.getElementById('classAttendanceChart'), {
    type: 'bar',
    data: {
        labels: classData.map(c => c.class_name),
        datasets: [{
            data: classData.map(c => c.rate),
            // Ngjyra e kuqe nëse < 70%, e gjelbër nëse > 70%
            backgroundColor: classData.map(c => c.rate < 70 ? '#ef4444' : '#10b981'),
            borderRadius: 8,
            barThickness: 35
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { max: 100, beginAtZero: true, ticks: { callback: v => v + '%', font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
        }
    }
});

// 3. Trendi Sparkline
new Chart(document.getElementById('miniTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData, 'day')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($trendData, 'rate')) ?>,
            borderColor: '#818cf8',
            borderWidth: 4,
            tension: 0.4,
            pointRadius: 0,
            fill: true,
            backgroundColor: 'rgba(129, 140, 248, 0.1)'
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { display: false }, y: { display: false, min: 0, max: 105 } }
    }
});

// 4. Statusi i Nxënësve
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($statusData, 'status')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($statusData, 'count')) ?>,
            backgroundColor: ['#6366f1', '#94a3b8', '#10b981', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        cutout: '82%',
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
</script>