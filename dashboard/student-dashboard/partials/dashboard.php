<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   1. SESSION CHECK
========================= */
$studentId = $_SESSION['user']['student_id'] ?? null;
$schoolId  = $_SESSION['user']['school_id'] ?? null;

if (!$studentId || !$schoolId) {
    die('Session expired. Please log in.');
}

/* =========================
   2. DEFAULTS
========================= */
$studentName = 'Nxënës';
$className = 'N/A';
$teacherName = 'Pa caktuar';
$attendanceStatus = 'Pa regjistruar';
$attendanceColor = 'text-slate-500 bg-slate-100';
$attendanceRate = 0;
$upcomingAssignments = [];
$trendDates = [];
$trendStatus = [];

try {

    /* =====================================================
       3. STUDENT + CLASS + KUJDESTAR
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT
            s.name AS student_name,
            c.grade AS class_name,
            c.id AS class_id,
            u.name AS teacher_name
        FROM students s
        INNER JOIN classes c
            ON c.id = s.class_id
           AND c.school_id = s.school_id
        LEFT JOIN users u
            ON u.id = c.class_header
        WHERE s.student_id = ?
          AND s.school_id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    $studentName = htmlspecialchars($student['student_name']);
    $className   = htmlspecialchars($student['class_name']);
    $teacherName = htmlspecialchars($student['teacher_name'] ?? 'Pa caktuar');
    $classId     = (int)$student['class_id'];

    /* =====================================================
       4. TODAY ATTENDANCE (LESSON-BASED)
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT
            SUM(present) AS p,
            SUM(missing) AS m,
            SUM(excused) AS e
        FROM attendance
        WHERE student_id = ?
          AND school_id = ?
          AND lesson_date = CURDATE()
          AND archived_at IS NULL
    ");
    $stmt->execute([$studentId, $schoolId]);
    $today = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($today && ($today['p'] + $today['m'] + $today['e']) > 0) {
        if ($today['p'] > 0) {
            $attendanceStatus = 'Prezent';
            $attendanceColor = 'text-green-600 bg-green-100';
        } elseif ($today['m'] > 0) {
            $attendanceStatus = 'Mungon';
            $attendanceColor = 'text-red-600 bg-red-100';
        } else {
            $attendanceStatus = 'Arsyetuar';
            $attendanceColor = 'text-yellow-600 bg-yellow-100';
        }
    }

    /* =====================================================
       5. MONTHLY ATTENDANCE RATE (LESSON-BASED)
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(present) AS present_count
        FROM attendance
        WHERE student_id = ?
          AND school_id = ?
          AND MONTH(lesson_date) = MONTH(CURDATE())
          AND YEAR(lesson_date) = YEAR(CURDATE())
          AND archived_at IS NULL
    ");
    $stmt->execute([$studentId, $schoolId]);
    $month = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($month['total'] > 0) {
        $attendanceRate = (int) round(($month['present_count'] / $month['total']) * 100);
    }

    /* =====================================================
       6. UPCOMING ASSIGNMENTS (NO SUBJECT JOIN)
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT title, due_date
        FROM assignments
        WHERE class_id = ?
          AND school_id = ?
          AND due_date >= CURDATE()
          AND status = 'active'
        ORDER BY due_date ASC
        LIMIT 4
    ");
    $stmt->execute([$classId, $schoolId]);
    $upcomingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =====================================================
       7. 14-DAY ATTENDANCE TREND
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT lesson_date AS d, present
        FROM attendance
        WHERE student_id = ?
          AND school_id = ?
          AND lesson_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
          AND archived_at IS NULL
        ORDER BY lesson_date ASC
    ");
    $stmt->execute([$studentId, $schoolId]);
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($trend as $row) {
        $trendDates[]  = date('d M', strtotime($row['d']));
        $trendStatus[] = (int)$row['present'];
    }

} catch (Throwable $e) {
    error_log('[Student Dashboard] ' . $e->getMessage());
}

ob_start();
?>
<div class="px-6 py-6 max-w-6xl mx-auto font-sans antialiased text-slate-800">

    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                Mirësevini, <?= $studentName ?>!
            </h1>
            <p class="text-sm text-slate-500 font-normal mt-0.5">
                <?= $className ?> • Kujdestari: <?= $teacherName ?>
            </p>
        </div>
        <div class="px-4 py-1.5 rounded-full text-[11px] font-bold shadow-sm <?= $attendanceColor ?>">
            STATUSI: <?= mb_strtoupper($attendanceStatus) ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex flex-col items-center">
            <h3 class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-4">
                Pjesëmarrja Mujore
            </h3>
            <div class="relative w-40 h-20">
                <canvas id="attendanceGauge"></canvas>
                <div class="absolute inset-0 flex items-end justify-center">
                    <span class="text-xl font-bold mb-[-2px]"><?= $attendanceRate ?>%</span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-4">
                Aktiviteti 14-Ditor
            </h3>
            <div class="h-24">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-800">Detyrat e Ardhshme</h3>
            <a href="/E-Shkolla/student-assignments"
               class="text-[10px] text-blue-600 font-bold uppercase hover:underline">
                Shiko të gjitha
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php if (!empty($upcomingAssignments)): foreach ($upcomingAssignments as $a): ?>
                <div class="flex justify-between items-center p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition-all border border-transparent hover:border-slate-200">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-800">
                            <?= htmlspecialchars($a['title']) ?>
                        </h4>
                        <p class="text-xs text-slate-500">
                            <?= htmlspecialchars($a['subject_name'] ?? 'Përgjithshme') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] text-slate-400 font-bold uppercase">Afati</p>
                        <p class="text-sm font-bold text-blue-600">
                            <?= date('d M', strtotime($a['due_date'])) ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-span-2 py-8 text-center text-slate-400 text-sm italic border-2 border-dashed border-slate-50 rounded-xl">
                    Nuk ka detyra në pritje.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('attendanceGauge').getContext('2d'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?= $attendanceRate ?>, <?= 100 - $attendanceRate ?>],
                backgroundColor: ['#2563eb', '#f1f5f9'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270
            }]
        },
        options: {
            cutout: '88%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });

    new Chart(document.getElementById('attendanceTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendDates) ?>,
            datasets: [{
                data: <?= json_encode($trendStatus) ?>,
                borderColor: '#2563eb',
                borderWidth: 2,
                backgroundColor: 'rgba(37,99,235,.05)',
                fill: true,
                tension: .4,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
                y: { min: 0, max: 1, display: false }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>
