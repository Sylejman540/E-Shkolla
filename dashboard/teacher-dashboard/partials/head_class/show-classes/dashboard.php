<?php
require_once __DIR__ . '/../../../../../db.php';

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


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">

    <!-- HEADER -->
    <div class="mb-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-900">
                Klasa <?= htmlspecialchars($currentClassName) ?>
            </h1>
            <p class="mt-1 text-slate-500">
                Lënda:
                <span class="font-semibold text-indigo-600">
                    <?= htmlspecialchars($currentSubjectName) ?>
                </span>
            </p>
        </div>

        <button onclick="window.print()"
            class="no-print inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
            🖨️ Printo Raportin
        </button>
    </div>

    <!-- KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-12">

        <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">
                Nxënës në klasë
            </p>
            <div class="mt-3 text-4xl font-black text-indigo-600">
                <?= $myTotalStudents ?>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">
                Prezenca sot
            </p>
            <div class="mt-3 text-4xl font-black text-emerald-600">
                <?= ($presentToday + $missingToday > 0)
                    ? round(($presentToday / ($presentToday + $missingToday)) * 100)
                    : 0 ?>%
            </div>
        </div>

    </div>

    <!-- CHARTS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">

        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="mb-2 text-lg font-bold text-slate-800">
                Pjesëmarrja Sot
            </h2>
            <p class="mb-6 text-sm text-slate-500">
                <?= htmlspecialchars($currentSubjectName) ?>
            </p>

            <div class="h-[280px]">
                <?php if ($presentToday + $missingToday > 0): ?>
                    <canvas id="teacherAttendanceChart"></canvas>
                <?php else: ?>
                    <div class="flex h-full items-center justify-center text-center text-slate-400 italic">
                        Nuk ka të dhëna për sot.<br>
                        Shënoni prezencën për të parë grafikun.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="mb-2 text-lg font-bold text-slate-800">
                Trendi i Pjesëmarrjes
            </h2>
            <p class="mb-6 text-sm text-slate-500">
                7 ditët e fundit
            </p>

            <div class="h-[280px]">
                <canvas id="teacherTrendChart"></canvas>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
                cutout: '72%'
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom' } }
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
            backgroundColor: 'rgba(99,102,241,.15)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
