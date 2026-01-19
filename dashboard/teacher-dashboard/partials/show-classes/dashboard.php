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

<div class="px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Klasa <?= htmlspecialchars($currentClassName) ?></h1>
            <p class="text-slate-500">Lënda: <span class="font-semibold text-blue-600"><?= htmlspecialchars($currentSubjectName) ?></span></p>
        </div>
        
        <button onclick="window.print()" class="no-print inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Printo Raportin
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-10">
        <div class="bg-white p-6 rounded-[24px] border shadow-sm border-blue-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Nxënësit në këtë klasë</h3>
            <p class="text-3xl font-black mt-1 text-blue-600"><?= $myTotalStudents ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm border-emerald-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Prezenca Sot (%)</h3>
            <p class="text-3xl font-black mt-1 text-emerald-600">
                <?= ($presentToday + $missingToday > 0) ? round(($presentToday / ($presentToday + $missingToday)) * 100) : 0 ?>%
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4 text-slate-800">Pjesëmarrja Sot (<?= htmlspecialchars($currentSubjectName) ?>)</h2>
            <div class="h-[260px]">
                <?php if ($presentToday + $missingToday > 0): ?>
                    <canvas id="teacherAttendanceChart"></canvas>
                <?php else: ?>
                    <div class="h-full flex items-center justify-center text-slate-400 italic text-center">
                        Nuk ka të dhëna për sot për këtë lëndë. <br>
                        Ju lutem shënoni prezencën së pari.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4 text-slate-800">Trendi i Pjesëmarrjes (7 Ditë)</h2>
            <div class="h-[260px]">
                <canvas id="teacherTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx1 = document.getElementById('teacherAttendanceChart');
if(ctx1) {
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Prezent', 'Mungon'],
            datasets: [{
                data: [<?= $presentToday ?>, <?= $missingToday ?>],
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}

new Chart(document.getElementById('teacherTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($attendanceDates) ?>,
        datasets: [{
            label: 'Pjesëmarrja %',
            data: <?= json_encode($attendanceRates) ?>,
            borderColor: '#6366f1',
            tension: 0.4,
            fill: true,
            backgroundColor: 'rgba(99, 102, 241, 0.1)'
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
require_once __DIR__ . '/index.php';
?>