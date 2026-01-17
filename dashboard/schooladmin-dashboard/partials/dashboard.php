<?php
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) {
    die('School ID missing');
}

/* =====================================================
   BASIC KPIs
===================================================== */

// School name
$stmt = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$schoolName = $stmt->fetchColumn() ?: 'Your School';

// Totals
$totalStudents = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
$totalStudents->execute([$schoolId]);
$totalStudents = (int) $totalStudents->fetchColumn();

$totalTeachers = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = ?");
$totalTeachers->execute([$schoolId]);
$totalTeachers = (int) $totalTeachers->fetchColumn();

$totalParents = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ?");
$totalParents->execute([$schoolId]);
$totalParents = (int) $totalParents->fetchColumn();

$totalClasses = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$totalClasses->execute([$schoolId]);
$totalClasses = (int) $totalClasses->fetchColumn();

/* =====================================================
   STUDENT STATUS
===================================================== */
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) 
    FROM students 
    WHERE school_id = ?
    GROUP BY status
");
$stmt->execute([$schoolId]);
$studentsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* =====================================================
   TODAY'S ATTENDANCE
===================================================== */
$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT a.student_id) AS total_students,
        SUM(a.present) AS present,
        SUM(a.missing) AS missing
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND DATE(a.created_at) = ?
");
$stmt->execute([$schoolId, $today]);
$attendanceToday = $stmt->fetch(PDO::FETCH_ASSOC);

$presentToday = (int) ($attendanceToday['present'] ?? 0);
$missingToday = (int) ($attendanceToday['missing'] ?? 0);
$totalToday   = (int) ($attendanceToday['total_students'] ?? 0);

/* =====================================================
   MONTHLY ATTENDANCE
===================================================== */
$stmt = $pdo->prepare("
    SELECT 'Present' AS label, SUM(a.present) AS total
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND MONTH(a.created_at) = MONTH(CURDATE())
    AND YEAR(a.created_at) = YEAR(CURDATE())

    UNION ALL

    SELECT 'Missing', SUM(a.missing)
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND MONTH(a.created_at) = MONTH(CURDATE())
    AND YEAR(a.created_at) = YEAR(CURDATE())
");
$stmt->execute([$schoolId, $schoolId]);
$monthlyAttendanceData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* =====================================================
   ATTENDANCE TREND (LAST 7 DAYS)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.created_at) AS day,
        SUM(a.present) AS present,
        SUM(a.missing) AS missing,
        COUNT(DISTINCT a.student_id) AS total_students
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.created_at)
    ORDER BY DATE(a.created_at)
");
$stmt->execute([$schoolId]);
$trendRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceDates = [];
$presentTrend = [];
$missingTrend = [];
$attendanceRates = [];

foreach ($trendRows as $row) {
    $attendanceDates[] = date('D', strtotime($row['day']));
    $presentTrend[] = (int) $row['present'];
    $missingTrend[] = (int) $row['missing'];
    $attendanceRates[] = $row['total_students'] > 0
        ? round(($row['present'] / $row['total_students']) * 100)
        : 0;
}

/* =====================================================
   CLASS-WISE ATTENDANCE (TODAY)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        c.grade AS class_name,
        SUM(a.present) AS present,
        SUM(a.missing) AS missing
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND DATE(a.created_at) = CURDATE()
    GROUP BY c.id, c.grade
    ORDER BY present DESC
    LIMIT 5
");
$stmt->execute([$schoolId]);
$classAttendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   GRADES DISTRIBUTION (LAST 30 DAYS)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        CASE
            WHEN g.grade >= 9 THEN 'A'
            WHEN g.grade >= 8 THEN 'B'
            WHEN g.grade >= 7 THEN 'C'
            WHEN g.grade >= 6 THEN 'D'
            ELSE 'F'
        END AS grade_range,
        COUNT(*) AS total
    FROM grades g
    JOIN students s ON s.student_id = g.student_id
    WHERE s.school_id = ?
    AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY grade_range
");
$stmt->execute([$schoolId]);
$gradesDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


ob_start();
?>

<!-- =======================
     DASHBOARD HTML
======================= -->

<div class="px-4 sm:px-6 lg:px-8">

    <h1 class="text-3xl font-bold text-slate-900 mb-8">
        <?= htmlspecialchars($schoolName) ?> Dashboard
    </h1>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Total Students</h3>
            <p class="text-3xl font-black mt-1"><?= $totalStudents ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Total Teachers</h3>
            <p class="text-3xl font-black mt-1"><?= $totalTeachers ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Total Parents</h3>
            <p class="text-3xl font-black mt-1"><?= $totalParents ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Total Classes</h3>
            <p class="text-3xl font-black mt-1"><?= $totalClasses ?></p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4">Today's Attendance</h2>
            <div class="h-[260px]">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4">Attendance Rate (7 Days)</h2>
            <div class="h-[260px]">
                <canvas id="attendanceRateChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>

<!-- =======================
     CHART.JS
======================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('attendanceChart'), {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Missing'],
        datasets: [{
            data: [<?= $presentToday ?>, <?= $missingToday ?>],
            backgroundColor: ['#10b981', '#ef4444'],
            cutout: '80%'
        }]
    },
    options: { plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('attendanceRateChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($attendanceDates) ?>,
        datasets: [{
            data: <?= json_encode($attendanceRates) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.15)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { callback: v => v + '%' }
            }
        },
        plugins: { legend: { display: false } }
    }
});
</script>
