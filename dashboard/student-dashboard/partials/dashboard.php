<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$studentId = $_SESSION['user']['student_id'] ?? null;
$schoolId  = $_SESSION['user']['school_id'] ?? null;

if (!$studentId || !$schoolId) {
    die('Session expired. Please log in.');
}

try {
    /* =====================================================
       1. STUDENT & CLASS INFO 
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name AS student_name, s.class_name,
               c.id AS class_id, t.name AS teacher_name
        FROM students s
        LEFT JOIN classes c ON c.grade = s.class_name AND c.school_id = s.school_id
        LEFT JOIN teachers t ON t.user_id = c.user_id
        WHERE s.student_id = ? AND s.school_id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) die('Student not found.');

    $studentName = htmlspecialchars($student['student_name']);
    $className   = htmlspecialchars($student['class_name']);
    $teacherName = $student['teacher_name'] ?? 'Not assigned';
    $classId     = $student['class_id'];

    /* =====================================================
       2. ATTENDANCE & STATS
    ===================================================== */
    $stmt = $pdo->prepare("SELECT present, missing FROM attendance WHERE student_id = ? AND DATE(created_at) = CURDATE() LIMIT 1");
    $stmt->execute([$studentId]);
    $todayAtt = $stmt->fetch(PDO::FETCH_ASSOC);

   /* =====================================================
    2. ATTENDANCE & STATS (Përkthyer në Shqip)
===================================================== */
    $stmt = $pdo->prepare("SELECT present, missing FROM attendance WHERE student_id = ? AND DATE(created_at) = CURDATE() LIMIT 1");
    $stmt->execute([$studentId]);
    $todayAtt = $stmt->fetch(PDO::FETCH_ASSOC);

    $attendanceStatus = ($todayAtt['present'] ?? 0) == 1 ? 'Prezant' : (($todayAtt['missing'] ?? 0) == 1 ? 'Mungon' : 'Pa Regjistruar');
    $attendanceColor  = ($todayAtt['present'] ?? 0) == 1 ? 'text-green-600 bg-green-100' : (($todayAtt['missing'] ?? 0) == 1 ? 'text-red-600 bg-red-100' : 'text-yellow-600 bg-yellow-100');

    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(present) as p FROM attendance WHERE student_id = ? AND MONTH(created_at) = MONTH(CURDATE())");
    $stmt->execute([$studentId]);
    $mStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $attendanceRate = ($mStats['total'] ?? 0) > 0 ? round(($mStats['p'] / $mStats['total']) * 100) : 0;

    /* =====================================================
       3. UPCOMING ASSIGNMENTS (FIXED GROUP BY ERROR)
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.due_date, ANY_VALUE(sub.subject_name) as subject_name
        FROM assignments a
        LEFT JOIN class_subject cs ON cs.class_id = a.class_id
        LEFT JOIN subjects sub ON sub.id = cs.subject_id
        WHERE a.class_id = ? AND a.due_date >= CURDATE()
        GROUP BY a.id, a.title, a.due_date
        ORDER BY a.due_date ASC LIMIT 5
    ");
    $stmt->execute([$classId]);
    $upcomingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =====================================================
       4. CHART DATA: ATTENDANCE TREND
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, present FROM attendance 
        WHERE student_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        ORDER BY date ASC
    ");
    $stmt->execute([$studentId]);
    $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $trendDates = [];
    $trendStatus = [];
    foreach ($trendData as $day) {
        $trendDates[] = date('M j', strtotime($day['date']));
        $trendStatus[] = (int)$day['present'];
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="px-4 py-8 max-w-7xl mx-auto">
    <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Welcome, <?= $studentName ?>!</h1>
            <p class="text-slate-500 font-medium mt-1"><?= $className ?> • Class Teacher: <?= $teacherName ?></p>
        </div>
        <div class="px-6 py-3 rounded-2xl font-bold shadow-sm <?= $attendanceColor ?>">
            Status: <?= $attendanceStatus ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <div class="bg-white p-8 rounded-[32px] border shadow-sm flex flex-col items-center">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-6">Attendance Rate</h3>
            <div class="relative w-48 h-24">
                <canvas id="attendanceGauge"></canvas>
                <div class="absolute inset-0 flex items-end justify-center">
                    <span class="text-3xl font-black mb-[-5px]"><?= $attendanceRate ?>%</span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white p-8 rounded-[32px] border shadow-sm">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-4">14-Day Activity</h3>
            <div class="h-32">
                <canvas id="attendanceTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bg-white p-8 rounded-[32px] border shadow-sm">
        <h3 class="text-xl font-bold mb-6">Upcoming Assignments</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if ($upcomingAssignments): foreach ($upcomingAssignments as $a): ?>
                <div class="flex justify-between items-center p-5 bg-slate-50 rounded-2xl hover:bg-slate-100 transition-colors">
                    <div>
                        <h4 class="font-bold text-slate-800"><?= htmlspecialchars($a['title']) ?></h4>
                        <p class="text-sm text-slate-500 font-medium"><?= htmlspecialchars($a['subject_name'] ?? 'General') ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400 font-bold uppercase">Due Date</p>
                        <p class="text-blue-600 font-black"><?= date('d M', strtotime($a['due_date'])) ?></p>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="col-span-2 py-10 text-center text-slate-400 italic">No pending assignments found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. GAUGE CHART (Attendance Rate)
    const ctxG = document.getElementById('attendanceGauge').getContext('2d');
    new Chart(ctxG, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?= $attendanceRate ?>, <?= 100 - $attendanceRate ?>],
                backgroundColor: ['#2563eb', '#f1f5f9'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
            }]
        },
        options: { cutout: '85%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // 2. LINE CHART (Attendance Trend)
    const ctxT = document.getElementById('attendanceTrendChart').getContext('2d');
    new Chart(ctxT, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendDates) ?>,
            datasets: [{
                data: <?= json_encode($trendStatus) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.4,
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