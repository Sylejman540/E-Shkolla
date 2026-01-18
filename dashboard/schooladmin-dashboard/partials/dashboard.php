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

/* =====================================================
   NEW CHARTS ADDED:
   1. STUDENT STATUS DISTRIBUTION
   2. TOP PERFORMING CLASSES BY ATTENDANCE (TODAY)
===================================================== */

// 1. Student Status Distribution
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(status, 'Unknown') AS status,
        COUNT(*) AS count
    FROM students 
    WHERE school_id = ?
    GROUP BY status
    ORDER BY count DESC
");
$stmt->execute([$schoolId]);
$studentStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentStatusLabels = [];
$studentStatusValues = [];
$studentStatusColors = [
    'active' => '#10b981',
    'inactive' => '#f59e0b',
    'graduated' => '#6366f1',
    'suspended' => '#ef4444',
    'transferred' => '#8b5cf6',
    'Unknown' => '#6b7280'
];

foreach ($studentStatusData as $row) {
    $studentStatusLabels[] = ucfirst($row['status']);
    $studentStatusValues[] = (int) $row['count'];
}

// 2. Top Performing Classes by Attendance (Today)
$stmt = $pdo->prepare("
    SELECT 
        c.grade AS class_name,
        COUNT(DISTINCT a.student_id) AS total_students,
        SUM(a.present) AS present_count,
        ROUND(
            (SUM(a.present) * 100.0 / COUNT(DISTINCT a.student_id)), 
            1
        ) AS attendance_percentage
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
    AND DATE(a.created_at) = CURDATE()
    AND c.grade IS NOT NULL
    GROUP BY c.id, c.grade
    HAVING total_students > 0
    ORDER BY attendance_percentage DESC
    LIMIT 6
");
$stmt->execute([$schoolId]);
$topClassesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$topClassLabels = [];
$topClassPercentages = [];
$topClassColors = [];

// Generate colors based on performance
foreach ($topClassesData as $row) {
    $topClassLabels[] = $row['class_name'];
    $topClassPercentages[] = (float) $row['attendance_percentage'];
    
    // Color coding based on attendance percentage
    if ($row['attendance_percentage'] >= 90) {
        $topClassColors[] = '#10b981'; // Green for excellent
    } elseif ($row['attendance_percentage'] >= 75) {
        $topClassColors[] = '#f59e0b'; // Yellow/Orange for good
    } else {
        $topClassColors[] = '#ef4444'; // Red for poor
    }
}

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

    <!-- First Row of Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
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

    <!-- Second Row of Charts (NEWLY ADDED) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Student Status Distribution Chart -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Student Status Distribution</h2>
                <span class="text-sm text-slate-500">Total: <?= $totalStudents ?> students</span>
            </div>
            <div class="h-[260px]">
                <canvas id="studentStatusChart"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2">
                <?php foreach ($studentStatusData as $status): ?>
                    <div class="flex items-center text-sm">
                        <div class="w-3 h-3 rounded-full mr-2" 
                             style="background-color: <?= $studentStatusColors[strtolower($status['status'])] ?? '#6b7280' ?>"></div>
                        <span class="text-slate-700"><?= ucfirst($status['status']) ?>:</span>
                        <span class="font-bold ml-1"><?= $status['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Performing Classes Chart -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Top Classes by Attendance Today</h2>
                <span class="text-sm text-slate-500"><?= date('M j, Y') ?></span>
            </div>
            <div class="h-[260px]">
                <canvas id="topClassesChart"></canvas>
            </div>
            <?php if (!empty($topClassesData)): ?>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ($topClassesData as $index => $class): ?>
                        <div class="text-center p-2 bg-slate-50 rounded-lg">
                            <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($class['class_name']) ?></div>
                            <div class="text-lg font-black mt-1" style="color: <?= $topClassColors[$index] ?>">
                                <?= $class['attendance_percentage'] ?>%
                            </div>
                            <div class="text-xs text-slate-500">
                                <?= $class['present_count'] ?>/<?= $class['total_students'] ?> students
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mt-4 text-center text-slate-500 italic">
                    No attendance data available for today
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Data Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Grades Distribution -->
        <div class="bg-white p-6 rounded-[24px] border">
            <h3 class="font-bold mb-4">Grades Distribution (Last 30 Days)</h3>
            <div class="space-y-3">
                <?php 
                $gradeLabels = ['5', '4', '3', '2', '1'];
                $totalGrades = array_sum($gradesDistribution);
                foreach ($gradeLabels as $grade): 
                    $count = $gradesDistribution[$grade] ?? 0;
                    $percentage = $totalGrades > 0 ? round(($count / $totalGrades) * 100) : 0;
                ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium">Grade <?= $grade ?></span>
                            <span><?= $percentage ?>% (<?= $count ?>)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="h-2 rounded-full" 
                                 style="width: <?= $percentage ?>%; 
                                        background-color: <?= 
                                            $grade === '5' ? '#10b981' : 
                                            ($grade === '4' ? '#f59e0b' : 
                                            ($grade === '3' ? '#6366f1' : 
                                            ($grade === '2' ? '#8b5cf6' : '#ef4444'))) ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Class Attendance Today -->
        <div class="bg-white p-6 rounded-[24px] border">
            <h3 class="font-bold mb-4">Class Attendance Today</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-slate-500 border-b">
                            <th class="pb-2">Class</th>
                            <th class="pb-2">Present</th>
                            <th class="pb-2">Missing</th>
                            <th class="pb-2">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($classAttendanceData)): ?>
                            <?php foreach ($classAttendanceData as $class): 
                                $total = $class['present'] + $class['missing'];
                                $rate = $total > 0 ? round(($class['present'] / $total) * 100) : 0;
                            ?>
                                <tr class="text-sm border-b border-slate-100 last:border-0">
                                    <td class="py-3 font-medium"><?= htmlspecialchars($class['class_name']) ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                            <?= $class['present'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">
                                            <?= $class['missing'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 font-bold <?= $rate >= 90 ? 'text-green-600' : ($rate >= 75 ? 'text-yellow-600' : 'text-red-600') ?>">
                                        <?= $rate ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500 italic">
                                    No attendance recorded today
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
// Chart 1: Today's Attendance Doughnut
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
    options: { 
        plugins: { 
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) label += ': ';
                        if (context.parsed !== null) {
                            label += context.parsed + ' students';
                        }
                        return label;
                    }
                }
            }
        }
    }
});

// Chart 2: Attendance Rate Trend Line
new Chart(document.getElementById('attendanceRateChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($attendanceDates) ?>,
        datasets: [{
            data: <?= json_encode($attendanceRates) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.15)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#6366f1',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { 
                    callback: v => v + '%',
                    stepSize: 20
                },
                grid: {
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: { 
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + '% attendance';
                    }
                }
            }
        }
    }
});

// Chart 3: Student Status Distribution (NEW)
new Chart(document.getElementById('studentStatusChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($studentStatusLabels) ?>,
        datasets: [{
            data: <?= json_encode($studentStatusValues) ?>,
            backgroundColor: [
                <?php foreach ($studentStatusLabels as $label): ?>
                    '<?= $studentStatusColors[strtolower($label)] ?? '#6b7280' ?>',
                <?php endforeach; ?>
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) label += ': ';
                        if (context.parsed !== null) {
                            label += context.parsed + ' students';
                        }
                        return label;
                    }
                }
            }
        },
        maintainAspectRatio: false
    }
});

// Chart 4: Top Performing Classes Bar Chart (NEW)
new Chart(document.getElementById('topClassesChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($topClassLabels) ?>,
        datasets: [{
            data: <?= json_encode($topClassPercentages) ?>,
            backgroundColor: <?= json_encode($topClassColors) ?>,
            borderWidth: 0,
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: v => v + '%',
                    stepSize: 25
                },
                grid: {
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + '% attendance';
                    }
                }
            }
        }
    }
});
</script>