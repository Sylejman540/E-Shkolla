<?php
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is a teacher
$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null; // Assumes teacher_id is stored in session

if (!$schoolId || !$teacherId) {
    die('Aksesi i mohuar: Të dhënat e mësuesit mungojnë.');
}

$teacherId = $_SESSION['user']['teacher_id'] ?? null;

// IF MISSING: Try to fetch the first available teacher ID for this school (Only for testing!)
if (!$teacherId && $schoolId) {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE school_id = ? LIMIT 1");
    $stmt->execute([$schoolId]);
    $teacherId = $stmt->fetchColumn();
    
    // Optional: Save it to session so it works on next refresh
    if($teacherId) { $_SESSION['user']['teacher_id'] = $teacherId; }
}

if (!$teacherId) {
    die('Aksesi i mohuar: Nuk u gjet asnjë llogari mësuesi për këtë shkollë.');
}
/* =====================================================
   TEACHER SPECIFIC KPIs
===================================================== */

// Get Teacher Name
$stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacherName = $stmt->fetchColumn() ?: 'Mësues';

// Total Students taught by this teacher (distinct students across all their classes)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sc.student_id)
    FROM teacher_class tc
    JOIN student_class sc ON sc.class_id = tc.class_id
    JOIN students s ON s.student_id = sc.student_id
    WHERE tc.teacher_id = ?
      AND s.school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$myTotalStudents = (int) $stmt->fetchColumn();

// Total Classes assigned to this teacher
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT class_id) FROM class_schedule WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$myTotalClasses = (int) $stmt->fetchColumn();

// Total Subjects taught
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) FROM class_schedule WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$myTotalSubjects = (int) $stmt->fetchColumn();

/* =====================================================
   TODAY'S ATTENDANCE (Only for this teacher's classes)
===================================================== */
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT
        SUM(a.present) AS present,
        SUM(a.missing) AS missing
    FROM attendance a
    JOIN class_schedule cs ON cs.class_id = a.class_id
    WHERE cs.teacher_id = ? 
    AND DATE(a.created_at) = ?
");
$stmt->execute([$teacherId, $today]);
$attendanceToday = $stmt->fetch(PDO::FETCH_ASSOC);

$presentToday = (int) ($attendanceToday['present'] ?? 0);
$missingToday = (int) ($attendanceToday['missing'] ?? 0);

/* =====================================================
   ATTENDANCE TREND (Last 7 Days for Teacher's Classes)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.created_at) AS day,
        SUM(a.present) AS present,
        COUNT(a.student_id) AS total_logs
    FROM attendance a
    JOIN class_schedule cs ON cs.class_id = a.class_id
    WHERE cs.teacher_id = ?
    AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.created_at)
    ORDER BY DATE(a.created_at)
");
$stmt->execute([$teacherId]);
$trendRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceDates = [];
$attendanceRates = [];

foreach ($trendRows as $row) {
    $attendanceDates[] = date('D', strtotime($row['day']));
    $attendanceRates[] = $row['total_logs'] > 0
        ? round(($row['present'] / $row['total_logs']) * 100)
        : 0;
}

/* =====================================================
   MY CLASSES PERFORMANCE (Today)
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        c.grade AS class_name,
        SUM(a.present) AS present,
        COUNT(a.student_id) AS total
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    JOIN class_schedule cs ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
    AND DATE(a.created_at) = CURDATE()
    GROUP BY c.id
");
$stmt->execute([$teacherId]);
$myClassesAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900">Mirëseerdhe, Prof. <?= htmlspecialchars($teacherName) ?></h1>
        <p class="text-slate-500">Përmbledhja e angazhimit tuaj mësimor për sot.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <div class="bg-white p-6 rounded-[24px] border shadow-sm border-blue-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Nxënësit e mi</h3>
            <p class="text-3xl font-black mt-1 text-blue-600"><?= $myTotalStudents ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm border-purple-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Klasat e caktuara</h3>
            <p class="text-3xl font-black mt-1 text-purple-600"><?= $myTotalClasses ?></p>
        </div>
        <div class="bg-white p-6 rounded-[24px] border shadow-sm border-emerald-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase">Lëndët që jap</h3>
            <p class="text-3xl font-black mt-1 text-emerald-600"><?= $myTotalSubjects ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-8 rounded-[32px] border">
            <h2 class="font-bold mb-4 text-slate-800">Pjesëmarrja e Nxënësve të mi (Sot)</h2>
            <div class="h-[260px]">
                <?php if ($presentToday + $missingToday > 0): ?>
                    <canvas id="teacherAttendanceChart"></canvas>
                <?php else: ?>
                    <div class="h-full flex items-center justify-center text-slate-400 italic">Nuk ka të dhëna për sot</div>
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

    <div class="bg-white p-6 rounded-[24px] border mb-8">
        <h3 class="font-bold mb-4">Raporti sipas Klasave</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm text-slate-500 border-b">
                        <th class="pb-3">Klasa</th>
                        <th class="pb-3">Prezent</th>
                        <th class="pb-3">Mungesa</th>
                        <th class="pb-3">Përqindja</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($myClassesAttendance as $row): 
                        $rate = round(($row['present'] / $row['total']) * 100);
                    ?>
                    <tr>
                        <td class="py-4 font-bold text-slate-700">Klasa <?= htmlspecialchars($row['class_name']) ?></td>
                        <td class="py-4 text-emerald-600 font-medium"><?= $row['present'] ?></td>
                        <td class="py-4 text-red-500 font-medium"><?= $row['total'] - $row['present'] ?></td>
                        <td class="py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-blue-500 h-full" style="width: <?= $rate ?>%"></div>
                                </div>
                                <span class="text-xs font-bold"><?= $rate ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Doughnut Chart
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

// Trend Chart
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