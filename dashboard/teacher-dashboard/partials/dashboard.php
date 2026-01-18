<?php
require_once __DIR__ . '../index.php';
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId   = (int) ($_SESSION['user']['id'] ?? 0);
$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);

if (!$userId || !$schoolId) {
    header('Location: /login.php');
    exit();
}

/* ================= 1. TEACHER INFO ================= */
$stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) { 
    die("Profile mësuesi nuk u gjet."); 
}

$teacherId   = (int) $teacher['id'];
$teacherName = $teacher['name'];

/* ================= 2. KPI CALCULATIONS ================= */
// Classes
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT class_id) FROM class_schedule WHERE teacher_id = ? AND school_id = ?");
$stmt->execute([$teacherId, $schoolId]);
$myClasses = (int) $stmt->fetchColumn();

// Students
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT sc.student_id) FROM student_class sc JOIN class_schedule cs ON cs.class_id = sc.class_id WHERE cs.teacher_id = ? AND cs.school_id = ?");
$stmt->execute([$teacherId, $schoolId]);
$totalStudents = (int) $stmt->fetchColumn();

// Assignments Status
$stmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as done,
    COUNT(CASE WHEN completed_at IS NULL AND due_date >= CURDATE() THEN 1 END) as pending,
    COUNT(CASE WHEN completed_at IS NULL AND due_date < CURDATE() THEN 1 END) as overdue
    FROM assignments WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$assignStats = $stmt->fetch(PDO::FETCH_ASSOC);
$assignmentsCompleted = (int)$assignStats['done'];
$assignmentsPending   = (int)$assignStats['pending'];
$assignmentsOverdue   = (int)$assignStats['overdue'];
$pendingTasks         = $assignmentsPending;

/* ================= 3. ATTENDANCE & TRENDS ================= */
$stmt = $pdo->prepare("SELECT COALESCE(SUM(present), 0) AS present, COALESCE(SUM(missing), 0) AS missing FROM attendance WHERE teacher_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$teacherId]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);
$presentToday = (int)$att['present'];
$missingToday = (int)$att['missing'];
$attendanceRate = ($presentToday + $missingToday) > 0 ? round(($presentToday / ($presentToday + $missingToday)) * 100) : 0;

// 7 Day Trend
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as d, SUM(present) as p, SUM(missing) as m 
    FROM attendance WHERE teacher_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    GROUP BY DATE(created_at) ORDER BY d ASC
");
$stmt->execute([$teacherId]);
$trendRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$trendDates = []; $trendRates = [];
foreach($trendRaw as $r) {
    $trendDates[] = date('d M', strtotime($r['d']));
    $total = $r['p'] + $r['m'];
    $trendRates[] = ($total > 0) ? round(($r['p'] / $total) * 100) : 0;
}

/* ================= 4. GRADES CHART ================= */
$stmt = $pdo->prepare("
    SELECT c.grade as name, COALESCE(AVG(g.grade), 0) as avg 
    FROM classes c 
    JOIN class_schedule cs ON c.id = cs.class_id 
    LEFT JOIN grades g ON g.class_id = c.id 
    WHERE cs.teacher_id = ? AND c.school_id = ?
    GROUP BY c.id LIMIT 6
");
$stmt->execute([$teacherId, $schoolId]);
$gradesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$gradeLabels = array_column($gradesRaw, 'name');
$gradeValues = array_map(fn($v) => round($v, 1), array_column($gradesRaw, 'avg'));

/* ================= 5. SCHEDULE & ANNOUNCEMENTS ================= */
$stmt = $pdo->prepare("
    SELECT cs.day, cs.start_time, c.grade, s.subject_name
    FROM class_schedule cs
    JOIN classes c ON c.id = cs.class_id
    JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.teacher_id = ? AND cs.school_id = ?
    ORDER BY FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    LIMIT 5
");
$stmt->execute([$teacherId, $schoolId]);
$upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$todayName = strtolower(date('l'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM class_schedule WHERE teacher_id = ? AND LOWER(day) = ?");
$stmt->execute([$teacherId, $todayName]);
$todayLessons = (int)$stmt->fetchColumn();

// Announcements
$stmt = $pdo->prepare("SELECT title, created_at FROM announcements WHERE school_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$schoolId]);
$recentAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paneli i Mësuesit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-200">

    <main class="min-h-screen md:ml-64 transition-all duration-300">
        
        <header class="p-6 lg:p-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Mirë se vini, <?= htmlspecialchars($teacherName) ?>!
                    </h1>
                    <p class="text-gray-500 mt-1">Sot është <?= date('l, d F Y') ?> | Ora: <span id="currentTime" class="font-mono text-indigo-600"></span></p>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white dark:bg-gray-900 px-4 py-2 rounded-lg shadow-sm border dark:border-gray-800 flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-sm font-medium">Sistemi Online</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-10">
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500 text-sm font-medium uppercase">Klasat</span>
                        <i class="fas fa-chalkboard text-blue-500"></i>
                    </div>
                    <div class="text-3xl font-bold"><?= $myClasses ?></div>
                </div>

                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500 text-sm font-medium uppercase">Nxënës</span>
                        <i class="fas fa-users text-green-500"></i>
                    </div>
                    <div class="text-3xl font-bold"><?= $totalStudents ?></div>
                </div>

                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500 text-sm font-medium uppercase">Orë Sot</span>
                        <i class="fas fa-calendar-day text-purple-500"></i>
                    </div>
                    <div class="text-3xl font-bold"><?= $todayLessons ?></div>
                </div>

                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500 text-sm font-medium uppercase">Detyra</span>
                        <i class="fas fa-tasks text-yellow-500"></i>
                    </div>
                    <div class="text-3xl font-bold"><?= $pendingTasks ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Prezenca Sot</h3>
                    <div class="h-64"><canvas id="attendanceChart"></canvas></div>
                </div>
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Statusi i Detyrave</h3>
                    <div class="h-64"><canvas id="assignmentsChart"></canvas></div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mt-8">
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Mesatarja e Notave për Klasë</h3>
                    <div class="h-72"><canvas id="gradesChart"></canvas></div>
                </div>

                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Orari i Radhës</h3>
                    <div class="space-y-4">
                        <?php foreach ($upcomingClasses as $c): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                            <div>
                                <div class="font-bold text-sm"><?= htmlspecialchars($c['grade']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($c['subject_name']) ?></div>
                            </div>
                            <div class="text-xs font-mono bg-white dark:bg-gray-900 px-2 py-1 rounded shadow-sm">
                                <?= substr($c['start_time'],0,5) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8 pb-10">
                <div class="lg:col-span-2 bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Trendi i Prezencës (7 Ditët e Fundit)</h3>
                    <div class="h-64"><canvas id="attendanceTrendChart"></canvas></div>
                </div>
                
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border dark:border-gray-800">
                    <h3 class="font-bold mb-4">Njoftimet e Shkollës</h3>
                    <div class="space-y-4">
                        <?php if ($recentAnnouncements): ?>
                            <?php foreach ($recentAnnouncements as $a): ?>
                                <div class="border-l-4 border-indigo-500 pl-4 py-1">
                                    <p class="text-sm font-bold"><?= htmlspecialchars($a['title']) ?></p>
                                    <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($a['created_at'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm italic text-center py-10">Nuk ka njoftime të reja.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
    </main>

    <script>
        // Digital Clock
        function tick() {
            document.getElementById('currentTime').innerText = new Date().toLocaleTimeString('sq-AL');
        }
        setInterval(tick, 1000); tick();

        // Chart defaults
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui';

        // 1. Attendance Today
        new Chart(document.getElementById('attendanceChart'), {
            type: 'doughnut',
            data: {
                labels: ['Prezente', 'Mungesa'],
                datasets: [{
                    data: [<?= $presentToday ?>, <?= $missingToday ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '70%', maintainAspectRatio: false }
        });

        // 2. Assignments status
        new Chart(document.getElementById('assignmentsChart'), {
            type: 'pie',
            data: {
                labels: ['Dorëzuara', 'Në Pritje', 'Vonuar'],
                datasets: [{
                    data: [<?= $assignmentsCompleted ?>, <?= $assignmentsPending ?>, <?= $assignmentsOverdue ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: { maintainAspectRatio: false }
        });

        // 3. Average Grades
        new Chart(document.getElementById('gradesChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($gradeLabels) ?>,
                datasets: [{
                    label: 'Nota Mesatare',
                    data: <?= json_encode($gradeValues) ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 8
                }]
            },
            options: { 
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 5 } }
            }
        });

        // 4. Trend Line
        new Chart(document.getElementById('attendanceTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trendDates) ?>,
                datasets: [{
                    label: 'Prezenca %',
                    data: <?= json_encode($trendRates) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { 
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    </script>
</body>
</html>