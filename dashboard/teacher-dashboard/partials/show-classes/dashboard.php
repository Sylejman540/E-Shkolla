<?php
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Merr ID-të nga URL (?class_id=11&subject_id=29)
$classIdFromUrl = $_GET['class_id'] ?? null;
$subjectIdFromUrl = $_GET['subject_id'] ?? null;

$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null;

if (!$schoolId || !$teacherId || !$classIdFromUrl) {
    die('Aksesi i mohuar: Mungon klasa ose të dhënat e mësuesit.');
}

/* =====================================================
   TEACHER & CLASS SPECIFIC DATA
===================================================== */

// Emri i mësuesit
$stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacherName = $stmt->fetchColumn() ?: 'Mësues';

// Emri i klasës specifike (p.sh. 12/2)
$stmt = $pdo->prepare("SELECT grade FROM classes WHERE id = ? AND school_id = ?");
$stmt->execute([$classIdFromUrl, $schoolId]);
$currentClassName = $stmt->fetchColumn();

// Emri i lëndës specifike
$stmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
$stmt->execute([$subjectIdFromUrl]);
$currentSubjectName = $stmt->fetchColumn();

// 2. Numri i nxënësve vetëm në këtë klasë
$stmt = $pdo->prepare("
    SELECT COUNT(sc.student_id)
    FROM student_class sc
    JOIN students s ON s.student_id = sc.student_id
    WHERE sc.class_id = ? AND s.school_id = ?
");
$stmt->execute([$classIdFromUrl, $schoolId]);
$myTotalStudents = (int) $stmt->fetchColumn();

/* =====================================================
   TODAY'S ATTENDANCE (Filtered by Class & Subject)
===================================================== */
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT
        SUM(present) AS present,
        SUM(missing) AS missing
    FROM attendance
    WHERE class_id = ? 
    AND subject_id = ?
    AND DATE(created_at) = ?
");
$stmt->execute([$classIdFromUrl, $subjectIdFromUrl, $today]);
$attendanceToday = $stmt->fetch(PDO::FETCH_ASSOC);

$presentToday = (int) ($attendanceToday['present'] ?? 0);
$missingToday = (int) ($attendanceToday['missing'] ?? 0);

/* =====================================================
   ATTENDANCE TREND (7 Days for this Class/Subject)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) AS day,
        SUM(present) AS present,
        COUNT(student_id) AS total_logs
    FROM attendance
    WHERE class_id = ? 
    AND subject_id = ?
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$stmt->execute([$classIdFromUrl, $subjectIdFromUrl]);
$trendRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceDates = [];
$attendanceRates = [];

foreach ($trendRows as $row) {
    $attendanceDates[] = date('D', strtotime($row['day']));
    $attendanceRates[] = $row['total_logs'] > 0
        ? round(($row['present'] / $row['total_logs']) * 100)
        : 0;
}

ob_start();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .dashboard-container {
        font-family: 'Inter', sans-serif;
        -webkit-font-smoothing: antialiased;
    }
</style>

<div class="dashboard-container max-w-6xl mx-auto px-4 pb-10 text-slate-700">

    <div class="mb-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                Klasa <?= htmlspecialchars($currentClassName) ?>
            </h1>
            <p class="text-[13px] text-slate-500">
                Lënda: <span class="font-medium text-indigo-600"><?= htmlspecialchars($currentSubjectName) ?></span>
            </p>
        </div>

        <button onclick="window.print()"
            class="no-print inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-600 shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Printo Raportin
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nxënës në klasë</p>
            <div class="mt-1 text-2xl font-bold text-slate-800">
                <?= $myTotalStudents ?>
            </div>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Prezenca sot</p>
            <div class="mt-1 text-2xl font-bold text-emerald-600">
                <?= ($presentToday + $missingToday > 0)
                    ? round(($presentToday / ($presentToday + $missingToday)) * 100)
                    : 0 ?>%
            </div>
        </div>
        
        <div class="hidden lg:block"></div>
        <div class="hidden lg:block"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-[14px] font-semibold text-slate-800">Pjesëmarrja Sot</h2>
                <p class="text-[11px] text-slate-400">Statistikat aktuale për <?= htmlspecialchars($currentSubjectName) ?></p>
            </div>

            <div class="h-[220px] flex items-center justify-center">
                <?php if ($presentToday + $missingToday > 0): ?>
                    <canvas id="teacherAttendanceChart"></canvas>
                <?php else: ?>
                    <div class="text-[12px] text-slate-400 italic">Nuk ka të dhëna për sot.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-[14px] font-semibold text-slate-800">Trendi i Pjesëmarrjes</h2>
                <p class="text-[11px] text-slate-400">7 ditët e fundit</p>
            </div>

            <div class="h-[220px]">
                <canvas id="teacherTrendChart"></canvas>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart.js global defaults for smaller UI
Chart.defaults.font.size = 11;
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#94a3b8';

const ctx1 = document.getElementById('teacherAttendanceChart');
if (ctx1) {
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Prezent', 'Mungon'],
            datasets: [{
                data: [<?= $presentToday ?>, <?= $missingToday ?>],
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                cutout: '80%' // Thinner ring for more elegance
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'bottom',
                    labels: { boxWidth: 8, padding: 15, usePointStyle: true }
                } 
            }
        }
    });
}

new Chart(document.getElementById('teacherTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($attendanceDates) ?>,
        datasets: [{
            data: <?= json_encode($attendanceRates) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,.05)',
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            borderWidth: 2
        }]
    },
    options: {
        maintainAspectRatio: false,
        scales: { 
            y: { 
                beginAtZero: true, 
                max: 100,
                grid: { display: false },
                ticks: { stepSize: 25 }
            },
            x: { grid: { display: false } }
        },
        plugins: { legend: { display: false } }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>
