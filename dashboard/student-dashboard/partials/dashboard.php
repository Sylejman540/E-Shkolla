<?php
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = $_SESSION['user']['student_id'] ?? null;
if (!$studentId) {
    die('Student ID missing');
}

/* =====================================================
   BASIC STUDENT INFO
===================================================== */

// Student details
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        c.grade AS class_name,
        c.section,
        t.first_name AS teacher_first_name,
        t.last_name AS teacher_last_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN teachers t ON t.id = c.teacher_id
    WHERE s.student_id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found');
}

$studentName = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$className = $student['class_name'] . ' ' . ($student['section'] ?? '');
$teacherName = $student['teacher_first_name'] . ' ' . $student['teacher_last_name'];

/* =====================================================
   TODAY'S ATTENDANCE STATUS
===================================================== */
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        present,
        missing,
        reason,
        created_at
    FROM attendance 
    WHERE student_id = ?
    AND DATE(created_at) = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$studentId, $today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

$isPresentToday = ($todayAttendance['present'] ?? 0) == 1;
$attendanceStatus = $isPresentToday ? 'Present' : ($todayAttendance['missing'] ? 'Absent' : 'Not Recorded');
$attendanceColor = $isPresentToday ? 'text-green-600 bg-green-100' : 
                  ($todayAttendance['missing'] ? 'text-red-600 bg-red-100' : 'text-yellow-600 bg-yellow-100');

/* =====================================================
   MONTHLY ATTENDANCE STATS
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(present) as present_days,
        SUM(missing) as absent_days
    FROM attendance 
    WHERE student_id = ?
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
");
$stmt->execute([$studentId]);
$monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalDays = (int) ($monthlyStats['total_days'] ?? 0);
$presentDays = (int) ($monthlyStats['present_days'] ?? 0);
$absentDays = (int) ($monthlyStats['absent_days'] ?? 0);
$attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100) : 0;

/* =====================================================
   RECENT GRADES (LAST 30 DAYS)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        g.*,
        sub.name as subject_name,
        sub.code as subject_code
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_id = ?
    AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY g.created_at DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$recentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average grade
$stmt = $pdo->prepare("
    SELECT 
        AVG(g.grade) as average_grade,
        COUNT(*) as total_grades
    FROM grades g
    WHERE g.student_id = ?
    AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$studentId]);
$gradeStats = $stmt->fetch(PDO::FETCH_ASSOC);
$averageGrade = round($gradeStats['average_grade'] ?? 0, 1);
$totalGrades = (int) ($gradeStats['total_grades'] ?? 0);

/* =====================================================
   UPCOMING ASSIGNMENTS
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        sub.name as subject_name,
        DATEDIFF(a.due_date, CURDATE()) as days_left
    FROM assignments a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.class_id = ?
    AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->execute([$student['class_id']]);
$upcomingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   ATTENDANCE TREND (LAST 14 DAYS)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(present) as present,
        SUM(missing) as absent
    FROM attendance 
    WHERE student_id = ?
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$stmt->execute([$studentId]);
$attendanceTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$trendDates = [];
$trendStatus = [];
foreach ($attendanceTrend as $day) {
    $trendDates[] = date('M j', strtotime($day['date']));
    $trendStatus[] = $day['present'] ? 1 : 0;
}

/* =====================================================
   SUBJECT-WISE PERFORMANCE
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        sub.name as subject_name,
        ROUND(AVG(g.grade), 1) as average_grade,
        COUNT(g.id) as grade_count,
        MAX(g.grade) as highest_grade,
        MIN(g.grade) as lowest_grade
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_id = ?
    GROUP BY sub.id, sub.name
    ORDER BY average_grade DESC
");
$stmt->execute([$studentId]);
$subjectPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   RECENT ANNOUNCEMENTS
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        an.*,
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name
    FROM announcements an
    JOIN teachers t ON t.id = an.teacher_id
    WHERE an.class_id = ?
    OR an.school_id = ?
    ORDER BY an.created_at DESC
    LIMIT 5
");
$stmt->execute([$student['class_id'], $student['school_id']]);
$recentAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   SCHEDULE FOR TODAY
===================================================== */
$todayOfWeek = date('w'); // 0 = Sunday, 1 = Monday, etc.
$stmt = $pdo->prepare("
    SELECT 
        sc.*,
        sub.name as subject_name,
        sub.code as subject_code,
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name
    FROM schedule sc
    JOIN subjects sub ON sub.id = sc.subject_id
    JOIN teachers t ON t.id = sc.teacher_id
    WHERE sc.class_id = ?
    AND sc.day_of_week = ?
    ORDER BY sc.start_time ASC
");
$stmt->execute([$student['class_id'], $todayOfWeek]);
$todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- =======================
     STUDENT DASHBOARD HTML
======================= -->

<div class="px-4 sm:px-6 lg:px-8">
    <!-- Header with Student Info -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Welcome, <?= $studentName ?>!</h1>
                <p class="text-slate-600 mt-1">
                    <span class="font-medium"><?= $className ?></span> • 
                    Class Teacher: <span class="font-medium"><?= $teacherName ?></span>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-sm text-slate-500">Today's Status</div>
                    <span class="px-3 py-1 rounded-full text-sm font-bold <?= $attendanceColor ?>">
                        <?= $attendanceStatus ?>
                    </span>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-600 font-bold text-lg">
                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <!-- Attendance Rate -->
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">Attendance Rate</h3>
                    <p class="text-3xl font-black mt-1"><?= $attendanceRate ?>%</p>
                    <p class="text-sm text-slate-600 mt-1">
                        <?= $presentDays ?> of <?= $totalDays ?> days
                    </p>
                </div>
                <div class="w-16 h-16">
                    <canvas id="attendanceGauge"></canvas>
                </div>
            </div>
        </div>

        <!-- Average Grade -->
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Average Grade</h3>
            <p class="text-3xl font-black mt-1"><?= $averageGrade ?>/10</p>
            <p class="text-sm text-slate-600 mt-1">
                Based on <?= $totalGrades ?> assignments
            </p>
            <div class="mt-2">
                <div class="w-full bg-slate-200 rounded-full h-2">
                    <div class="h-2 rounded-full bg-blue-600" 
                         style="width: <?= $averageGrade * 10 ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Upcoming Assignments -->
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Upcoming Assignments</h3>
            <p class="text-3xl font-black mt-1"><?= count($upcomingAssignments) ?></p>
            <p class="text-sm text-slate-600 mt-1">
                <?php if (!empty($upcomingAssignments)): ?>
                    Next due: <?= date('M j', strtotime($upcomingAssignments[0]['due_date'])) ?>
                <?php else: ?>
                    No upcoming assignments
                <?php endif; ?>
            </p>
        </div>

        <!-- Class Rank (Placeholder) -->
        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Class Standing</h3>
            <p class="text-3xl font-black mt-1">Top 25%</p>
            <p class="text-sm text-slate-600 mt-1">
                Based on recent performance
            </p>
        </div>
    </div>

    <!-- First Row: Attendance & Schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Attendance Trend -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Attendance Trend (14 Days)</h2>
                <span class="text-sm text-slate-500">
                    Present: <?= $presentDays ?> days
                </span>
            </div>
            <div class="h-[260px]">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
            <div class="mt-4 flex justify-center gap-4">
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                    <span class="text-sm text-slate-600">Present</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                    <span class="text-sm text-slate-600">Absent</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                    <span class="text-sm text-slate-600">No Data</span>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Today's Schedule</h2>
                <span class="text-sm text-slate-500">
                    <?= date('l, F j') ?>
                </span>
            </div>
            <div class="space-y-3">
                <?php if (!empty($todaySchedule)): ?>
                    <?php foreach ($todaySchedule as $period): 
                        $startTime = date('g:i A', strtotime($period['start_time']));
                        $endTime = date('g:i A', strtotime($period['end_time']));
                    ?>
                        <div class="flex items-center p-3 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors">
                            <div class="w-16 text-center">
                                <div class="text-sm font-bold text-slate-900"><?= $startTime ?></div>
                                <div class="text-xs text-slate-500"><?= $endTime ?></div>
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="font-bold text-slate-900"><?= $period['subject_name'] ?></div>
                                <div class="text-sm text-slate-600"><?= $period['teacher_first_name'] ?> <?= $period['teacher_last_name'] ?></div>
                            </div>
                            <div class="text-sm font-medium px-3 py-1 bg-white border rounded-full">
                                Room <?= $period['room_number'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 italic">
                        No classes scheduled for today
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Second Row: Grades & Assignments -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Subject Performance -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Subject Performance</h2>
                <span class="text-sm text-slate-500">
                    Last 30 days
                </span>
            </div>
            <div class="space-y-4">
                <?php foreach ($subjectPerformance as $subject): 
                    $gradeWidth = $subject['average_grade'] * 10;
                    $gradeColor = $subject['average_grade'] >= 8 ? 'bg-green-500' : 
                                 ($subject['average_grade'] >= 6 ? 'bg-yellow-500' : 'bg-red-500');
                ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium"><?= $subject['subject_name'] ?></span>
                            <span class="font-bold <?= $subject['average_grade'] >= 8 ? 'text-green-600' : 
                                                   ($subject['average_grade'] >= 6 ? 'text-yellow-600' : 'text-red-600') ?>">
                                <?= $subject['average_grade'] ?>/10
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-full bg-slate-200 rounded-full h-3">
                                <div class="h-3 rounded-full <?= $gradeColor ?>" 
                                     style="width: <?= $gradeWidth ?>%"></div>
                            </div>
                            <div class="text-xs text-slate-500 whitespace-nowrap">
                                (<?= $subject['grade_count'] ?> grades)
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Upcoming Assignments -->
        <div class="bg-white p-8 rounded-[32px] border">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Upcoming Assignments</h2>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    View all →
                </a>
            </div>
            <div class="space-y-3">
                <?php if (!empty($upcomingAssignments)): ?>
                    <?php foreach ($upcomingAssignments as $assignment): 
                        $daysLeft = (int) $assignment['days_left'];
                        $dueDate = date('M j', strtotime($assignment['due_date']));
                        $priorityClass = $daysLeft <= 1 ? 'border-red-200 bg-red-50' : 
                                        ($daysLeft <= 3 ? 'border-yellow-200 bg-yellow-50' : 'border-slate-200 bg-white');
                    ?>
                        <div class="p-4 border rounded-xl <?= $priorityClass ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-slate-900"><?= $assignment['title'] ?></div>
                                    <div class="text-sm text-slate-600 mt-1"><?= $assignment['subject_name'] ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium <?= $daysLeft <= 1 ? 'text-red-600' : 
                                                                      ($daysLeft <= 3 ? 'text-yellow-600' : 'text-slate-600') ?>">
                                        <?= $dueDate ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <?= $daysLeft ?> day<?= $daysLeft != 1 ? 's' : '' ?> left
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-sm text-slate-600 line-clamp-2">
                                <?= htmlspecialchars(substr($assignment['description'] ?? '', 0, 100)) ?>...
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 italic">
                        No upcoming assignments
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Third Row: Recent Grades & Announcements -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Grades -->
        <div class="bg-white p-6 rounded-[24px] border">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Recent Grades</h3>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    View all →
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-slate-500 border-b">
                            <th class="pb-2">Subject</th>
                            <th class="pb-2">Assignment</th>
                            <th class="pb-2">Grade</th>
                            <th class="pb-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentGrades)): ?>
                            <?php foreach ($recentGrades as $grade): 
                                $gradeColor = $grade['grade'] >= 8 ? 'text-green-600 bg-green-100' : 
                                             ($grade['grade'] >= 6 ? 'text-yellow-600 bg-yellow-100' : 'text-red-600 bg-red-100');
                            ?>
                                <tr class="text-sm border-b border-slate-100 last:border-0">
                                    <td class="py-3 font-medium"><?= $grade['subject_name'] ?></td>
                                    <td class="py-3 text-slate-600"><?= $grade['assignment_title'] ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold <?= $gradeColor ?>">
                                            <?= $grade['grade'] ?>/10
                                        </span>
                                    </td>
                                    <td class="py-3 text-slate-500">
                                        <?= date('M j', strtotime($grade['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500 italic">
                                    No grades recorded recently
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Announcements -->
        <div class="bg-white p-6 rounded-[24px] border">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Recent Announcements</h3>
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    View all →
                </a>
            </div>
            <div class="space-y-3">
                <?php if (!empty($recentAnnouncements)): ?>
                    <?php foreach ($recentAnnouncements as $announcement): ?>
                        <div class="p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-bold text-slate-900"><?= $announcement['title'] ?></div>
                                    <div class="text-sm text-slate-600 mt-1">
                                        By <?= $announcement['teacher_first_name'] ?> <?= $announcement['teacher_last_name'] ?>
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500 whitespace-nowrap ml-2">
                                    <?= date('M j', strtotime($announcement['created_at'])) ?>
                                </div>
                            </div>
                            <div class="mt-2 text-sm text-slate-600 line-clamp-2">
                                <?= htmlspecialchars(substr($announcement['content'], 0, 120)) ?>...
                            </div>
                            <?php if ($announcement['priority'] == 'high'): ?>
                                <div class="mt-2">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full font-medium">
                                        High Priority
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 italic">
                        No announcements
                    </div>
                <?php endif; ?>
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
// Mini Gauge for Attendance Rate
new Chart(document.getElementById('attendanceGauge'), {
    type: 'doughnut',
    data: {
        datasets: [{
            data: [<?= $attendanceRate ?>, 100 - <?= $attendanceRate ?>],
            backgroundColor: [
                <?= $attendanceRate >= 90 ? "'#10b981'" : 
                   ($attendanceRate >= 75 ? "'#f59e0b'" : "'#ef4444'") ?>,
                '#e5e7eb'
            ],
            borderWidth: 0,
            cutout: '75%'
        }]
    },
    options: {
        plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
        },
        rotation: -90,
        circumference: 180,
        maintainAspectRatio: false
    }
});

// Attendance Trend Chart
new Chart(document.getElementById('attendanceTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendDates) ?>,
        datasets: [{
            label: 'Attendance',
            data: <?= json_encode($trendStatus) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: function(context) {
                const value = context.dataset.data[context.dataIndex];
                return value === 1 ? '#10b981' : '#ef4444';
            },
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        scales: {
            y: {
                min: 0,
                max: 1.2,
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return value === 1 ? 'Present' : value === 0 ? 'Absent' : '';
                    }
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
                        return context.parsed.y === 1 ? 'Present' : 'Absent';
                    }
                }
            }
        }
    }
});
</script>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>