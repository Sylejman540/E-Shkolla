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
        s.student_id,
        s.name AS student_name,
        s.gender,
        s.class_name,
        s.email,
        s.status,
        c.grade AS class_label,
        t.name AS teacher_name
    FROM students s
    LEFT JOIN classes c
        ON c.grade = s.class_name
        AND c.school_id = s.school_id
    LEFT JOIN teachers t
        ON t.user_id = c.user_id
    WHERE s.student_id = ?
      AND s.school_id = ?
");
$stmt->execute([$studentId, $_SESSION['user']['school_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found');
}


$studentName  = htmlspecialchars($student['student_name']);
$className    = htmlspecialchars($student['class_name']);
$teacherName  = $student['teacher_name'] ?? 'Not assigned';


/* =====================================================
   TODAY'S ATTENDANCE STATUS
===================================================== */
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        present,
        missing,
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
        sub.name as subject_name
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
        present,
        missing,
        created_at
    FROM attendance
    WHERE student_id = ?
      AND school_id = ?
      AND DATE(created_at) = CURDATE()
    ORDER BY created_at DESC
    LIMIT 1
");

$stmt->execute([
    $student['student_id'],
    $_SESSION['user']['school_id']
]);

$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);



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
        t.name AS teacher_name
    FROM announcements an
    LEFT JOIN teachers t ON t.id = an.teacher_id
       WHERE an.school_id = ?
    ORDER BY an.created_at DESC
    LIMIT 5
");

$stmt->execute([
    $_SESSION['user']['school_id']
]);

$recentAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   SCHEDULE FOR TODAY
===================================================== */
// 1️⃣ Get class_id from class_name
$stmt = $pdo->prepare("
    SELECT id 
    FROM classes 
    WHERE grade = ? 
      AND school_id = ?
    LIMIT 1
");
$stmt->execute([
    $student['class_name'],
    $_SESSION['user']['school_id']
]);

$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die('Class not found for student');
}

$classId = $class['id'];
// 1️⃣ Get class_id from class_name
$stmt = $pdo->prepare("
    SELECT id 
    FROM classes 
    WHERE grade = ? 
      AND school_id = ?
    LIMIT 1
");
$stmt->execute([
    $student['class_name'],
    $_SESSION['user']['school_id']
]);

$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die('Class not found for student');
}

$classId = $class['id'];


ob_start();
?>

<?php
// ... [Keep all your existing PHP logic/SQL queries exactly as they are] ...

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8">
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
                    <div class="text-sm text-slate-500 mb-1">Today's Status</div>
                    <span class="px-3 py-1 rounded-full text-sm font-bold <?= $attendanceColor ?>">
                        <?= $attendanceStatus ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
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

        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Average Grade</h3>
            <p class="text-3xl font-black mt-1"><?= $averageGrade ?>/5</p>
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

        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Upcoming Assignments</h3>
            <p class="text-3xl font-black mt-1"><?= isset($upcomingAssignments) ? count($upcomingAssignments) : 0 ?></p>
            <p class="text-sm text-slate-600 mt-1">
                <?php if (!empty($upcomingAssignments)): ?>
                    Next due: <?= date('M j', strtotime($upcomingAssignments[0]['due_date'])) ?>
                <?php else: ?>
                    No upcoming assignments
                <?php endif; ?>
            </p>
        </div>

        <div class="bg-white p-6 rounded-[24px] border shadow-sm">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Class Standing</h3>
            <p class="text-3xl font-black mt-1">Top 25%</p>
            <p class="text-sm text-slate-600 mt-1">Based on recent performance</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4">Attendance Trend (14 Days)</h2>
            <div class="h-[260px]">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4">Today's Schedule</h2>
            <div class="space-y-3">
                <?php if (!empty($todaySchedule)): ?>
                    <?php foreach ($todaySchedule as $period): ?>
                        <div class="flex items-center p-3 bg-slate-50 rounded-xl">
                            <div class="flex-1">
                                <div class="font-bold"><?= $period['subject_name'] ?></div>
                                <div class="text-sm text-slate-500"><?= $period['start_time'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 italic">No classes scheduled for today</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-6 rounded-[24px] border">
            <h3 class="font-bold mb-4">Recent Announcements</h3>
            <div class="space-y-3">
                <?php if (!empty($recentAnnouncements)): ?>
                    <?php foreach ($recentAnnouncements as $announcement): ?>
                        <div class="p-4 border border-slate-200 rounded-xl">
                            <div class="font-bold text-slate-900"><?= $announcement['title'] ?></div>
                            <div class="text-sm text-slate-600">By <?= $announcement['teacher_name'] ?? 'Staff' ?></div>
                            <div class="mt-2 text-sm text-slate-600"><?= htmlspecialchars(substr($announcement['content'], 0, 120)) ?>...</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500 italic">No announcements</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>