<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$schoolId  = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null;

if (!$schoolId || !$teacherId) {
    die('Akses i mohuar');
}

/* ======================
   TEACHER INFO
====================== */
$stmt = $pdo->prepare("
    SELECT name FROM teachers 
    WHERE id = ? AND school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$teacherName = $stmt->fetchColumn() ?: 'Mësues';

/* ======================
   KPI: STUDENTS
====================== */
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT sc.student_id)
    FROM teacher_class tc
    JOIN student_class sc ON sc.class_id = tc.class_id
    JOIN students s ON s.student_id = sc.student_id
    WHERE tc.teacher_id = ? AND s.school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);
$myTotalStudents = (int)$stmt->fetchColumn();

/* ======================
   KPI: CLASSES
====================== */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM teacher_class 
    WHERE teacher_id = ?
");
$stmt->execute([$teacherId]);
$myTotalClasses = (int)$stmt->fetchColumn();

/* ======================
   KPI: SUBJECTS
====================== */
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT cs.subject_id)
    FROM class_subject cs
    JOIN teacher_class tc ON tc.class_id = cs.class_id
    WHERE tc.teacher_id = ?
");
$stmt->execute([$teacherId]);
$myTotalSubjects = (int)$stmt->fetchColumn();

/* ======================
   TODAY ATTENDANCE
====================== */
$stmt = $pdo->prepare("
    SELECT 
        SUM(a.present) AS present,
        SUM(a.missing) AS missing
    FROM attendance a
    JOIN teacher_class tc ON tc.class_id = a.class_id
    WHERE tc.teacher_id = ?
      AND a.lesson_date = CURDATE()
");
$stmt->execute([$teacherId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$presentToday = (int)($row['present'] ?? 0);
$missingToday = (int)($row['missing'] ?? 0);

/* ======================
   ATTENDANCE TREND (7 DAYS)
====================== */
$stmt = $pdo->prepare("
    SELECT 
        a.lesson_date AS day,
        SUM(a.present) AS present,
        COUNT(*) AS total
    FROM attendance a
    JOIN teacher_class tc ON tc.class_id = a.class_id
    WHERE tc.teacher_id = ?
      AND a.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY a.lesson_date
    ORDER BY a.lesson_date
");
$stmt->execute([$teacherId]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceDates = [];
$attendanceRates = [];

foreach ($trend as $t) {
    $attendanceDates[] = date('D', strtotime($t['day']));
    $attendanceRates[] = $t['total'] > 0
        ? round(($t['present'] / $t['total']) * 100)
        : 0;
}

/* ======================
   PER CLASS (TODAY)
====================== */
$stmt = $pdo->prepare("
    SELECT 
        c.grade AS class_name,
        SUM(a.present) AS present,
        COUNT(*) AS total
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    JOIN teacher_class tc ON tc.class_id = c.id
    WHERE tc.teacher_id = ?
      AND a.lesson_date = CURDATE()
    GROUP BY c.id
");
$stmt->execute([$teacherId]);
$myClassesAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- ================= DASHBOARD ================= -->
<div class="px-4 sm:px-6 lg:px-8">

    <!-- HEADER -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
                Mirëseerdhe, Prof. <?= htmlspecialchars($teacherName) ?>
            </h1>
            <p class="text-sm text-slate-500">
                Përmbledhja e angazhimit tuaj mësimor për sot.
            </p>
        </div>

        <button onclick="window.print()"
            class="no-print inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            🖨️ Printo Raportin
        </button>
    </div>

    <!-- KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">

        <div class="relative overflow-hidden bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
            <div class="absolute right-4 top-4 text-blue-500/20 text-4xl">👨‍🎓</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Nxënësit e mi</p>
            <span class="block mt-2 text-3xl font-black text-blue-600"><?= $myTotalStudents ?></span>
        </div>

        <div class="relative overflow-hidden bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
            <div class="absolute right-4 top-4 text-purple-500/20 text-4xl">🏫</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Klasat</p>
            <span class="block mt-2 text-3xl font-black text-purple-600"><?= $myTotalClasses ?></span>
        </div>

        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-2xl shadow-lg text-white sm:col-span-2 lg:col-span-1">
            <div class="absolute right-4 top-4 text-white/30 text-4xl">📘</div>
            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80">Lëndët</p>
            <span class="block mt-2 text-3xl font-black"><?= $myTotalSubjects ?></span>
        </div>

    </div>

    <!-- CHARTS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">

        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-1">Pjesëmarrja Sot</h2>
            <p class="text-xs text-slate-500 mb-4">Gjendja aktuale</p>

            <?php if ($presentToday + $missingToday > 0): ?>
                <canvas id="teacherAttendanceChart" height="260"></canvas>
            <?php else: ?>
                <div class="h-[260px] flex items-center justify-center text-slate-400 italic">
                    Nuk ka të dhëna për sot
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-1">Trendi (7 Ditë)</h2>
            <p class="text-xs text-slate-500 mb-4">Përqindja e pjesëmarrjes</p>
            <canvas id="teacherTrendChart" height="260"></canvas>
        </div>

    </div>

    <!-- TABLE -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mb-8">
        <h3 class="font-bold mb-4">Raporti sipas Klasave (Sot)</h3>

        <div class="overflow-x-auto -mx-2 sm:mx-0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="pb-3">Klasa</th>
                        <th class="pb-3">Prezent</th>
                        <th class="pb-3">Mungon</th>
                        <th class="pb-3">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($myClassesAttendance as $row):
                        $rate = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100) : 0;
                    ?>
                        <tr>
                            <td class="py-3 font-semibold">Klasa <?= htmlspecialchars($row['class_name']) ?></td>
                            <td class="py-3 text-emerald-600"><?= $row['present'] ?></td>
                            <td class="py-3 text-red-500"><?= $row['total'] - $row['present'] ?></td>
                            <td class="py-3 font-bold"><?= $rate ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
if (document.getElementById('teacherAttendanceChart')) {
    new Chart(document.getElementById('teacherAttendanceChart'), {
        type: 'doughnut',
        data: {
            labels: ['Prezent', 'Mungon'],
            datasets: [{
                data: [<?= $presentToday ?>, <?= $missingToday ?>],
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                cutout: '70%'
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
            data: <?= json_encode($attendanceRates) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,.1)',
            tension: .4,
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
require_once __DIR__ . '/index.php';
?>
