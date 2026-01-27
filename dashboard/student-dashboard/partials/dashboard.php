<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = $_SESSION['user']['student_id'] ?? null;
$schoolId  = $_SESSION['user']['school_id'] ?? null;

if (!$studentId || !$schoolId) {
    die("Session expired. Ju lutem hyni përsëri.");
}

try {
    // 1. Student & Class Info
    $stmt = $pdo->prepare("
        SELECT s.name as s_name, c.grade as c_name, c.id as c_id, u.name as t_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON c.class_header = u.id
        WHERE s.student_id = ? AND s.school_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$studentId, $schoolId]);
    $student = $stmt->fetch();

    if (!$student) throw new Exception("Student not found.");

    $classId = (int)$student['c_id'];

    // 2. Today's Attendance
    $stmt = $pdo->prepare("
        SELECT SUM(present) as p, SUM(missing) as m, SUM(excused) as e
        FROM attendance 
        WHERE student_id = ? AND school_id = ? AND lesson_date = CURDATE() AND archived_at IS NULL
    ");
    $stmt->execute([$studentId, $schoolId]);
    $today = $stmt->fetch();

    $statusLabel = 'Pa regjistruar';
    $statusColor = 'bg-slate-50 text-slate-400 border-slate-100';
    if ($today && ($today['p'] + $today['m'] + $today['e']) > 0) {
        if ($today['m'] > 0) { 
            $statusLabel = 'Mungon'; 
            $statusColor = 'bg-red-50 text-red-500 border-red-100'; 
        } elseif ($today['e'] > 0) { 
            $statusLabel = 'Arsyetuar'; 
            $statusColor = 'bg-amber-50 text-amber-500 border-amber-100'; 
        } else { 
            $statusLabel = 'Prezent'; 
            $statusColor = 'bg-emerald-50 text-emerald-500 border-emerald-100'; 
        }
    }

    // 3. Monthly Rate
    $stmt = $pdo->prepare("
        SELECT (SUM(present)/COUNT(*))*100 as rate 
        FROM attendance 
        WHERE student_id = ? AND school_id = ? AND MONTH(lesson_date) = MONTH(CURDATE()) AND archived_at IS NULL
    ");
    $stmt->execute([$studentId, $schoolId]);
    $attendanceRate = (int)($stmt->fetch()['rate'] ?? 0);

    // Trendi 14-ditor: Llogarit përqindjen mesatare për çdo ditë
    $stmt = $pdo->prepare("
        SELECT 
            lesson_date, 
            (SUM(present) / COUNT(*)) * 100 as daily_rate
        FROM attendance
        WHERE student_id = ? 
        AND school_id = ? 
        AND lesson_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
        AND archived_at IS NULL
        GROUP BY lesson_date 
        ORDER BY lesson_date ASC
    ");
    $stmt->execute([$studentId, $schoolId]);
    $trendData = $stmt->fetchAll();

    $trendLabels = []; 
    $trendValues = [];

    foreach ($trendData as $t) {
        $trendLabels[] = date('d M', strtotime($t['lesson_date']));
        $trendValues[] = round((float)$t['daily_rate'], 1);
    }

    // 5. Assignments
    $stmt = $pdo->prepare("
        SELECT title, due_date FROM assignments 
        WHERE class_id = ? AND school_id = ? AND due_date >= CURDATE() AND status = 'active'
        ORDER BY due_date ASC LIMIT 4
    ");
    $stmt->execute([$classId, $schoolId]);
    $assignments = $stmt->fetchAll();

} catch (Exception $e) {
    die("Gabim: " . $e->getMessage());
}

ob_start();
?>

<div class="max-w-6xl mx-auto px-4 py-8 font-sans antialiased text-slate-700">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                Mirësevini, <?= htmlspecialchars($student['s_name']) ?>
            </h1>
            <p class="text-[13px] text-slate-400 font-medium mt-0.5">
                Klasa <?= htmlspecialchars($student['c_name']) ?> • Kujdestari: <?= htmlspecialchars($student['t_name'] ?? 'Pa caktuar') ?>
            </p>
        </div>
        <div class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-semibold uppercase tracking-wider border <?= $statusColor ?>">
            <?= $statusLabel ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col items-center">
            <h3 class="text-slate-400 text-[9px] font-bold uppercase tracking-[0.15em] mb-6">Pjesëmarrja Mujore</h3>
            <div class="relative w-40 h-20">
                <canvas id="gaugeChart"></canvas>
                <div class="absolute inset-0 flex items-end justify-center">
                    <span class="text-lg font-bold text-slate-800 mb-[-2px]"><?= $attendanceRate ?>%</span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <h3 class="text-slate-400 text-[9px] font-bold uppercase tracking-[0.15em] mb-6">Aktiviteti 14-Ditor</h3>
            <div class="h-28">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Detyrat e Ardhshme</h3>
            <a href="/E-Shkolla/student-assignments" class="text-[10px] font-bold text-indigo-500 hover:text-indigo-600 uppercase tracking-widest transition-colors">
                Gjithçka →
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php if ($assignments): foreach ($assignments as $a): ?>
                <div class="group flex justify-between items-center p-4 bg-slate-50/50 rounded-2xl border border-transparent hover:border-slate-100 hover:bg-white transition-all duration-200">
                    <div>
                        <h4 class="text-[13px] font-semibold text-slate-700 group-hover:text-indigo-600 transition-colors">
                            <?= htmlspecialchars($a['title']) ?>
                        </h4>
                        <p class="text-[10px] text-slate-400 font-medium mt-0.5">Lënda: Akademike</p>
                    </div>
                    <div class="text-right">
                        <span class="block text-[8px] font-bold text-slate-300 uppercase tracking-tighter">Afati</span>
                        <span class="text-[12px] font-bold text-slate-600"><?= date('d M', strtotime($a['due_date'])) ?></span>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-span-2 py-10 text-center border border-dashed border-slate-100 rounded-2xl">
                    <p class="text-slate-400 text-[12px] font-medium italic">Nuk ka detyra aktive.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Gauge
    new Chart(document.getElementById('gaugeChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?= $attendanceRate ?>, <?= 100 - $attendanceRate ?>],
                backgroundColor: ['#6366f1', '#f8fafc'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
                borderRadius: 4
            }]
        },
        options: {
            cutout: '88%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });

    // Trend
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#cbd5e1', // Softer slate color
                backgroundColor: 'transparent',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointRadius: 0,
                hoverRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#94a3b8' } },
                y: { min: 0, max: 100, display: false }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?> 