<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'parent') {
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int)$_SESSION['user']['id'];
$schoolId = (int)$_SESSION['user']['school_id'];

try {
    // Marrim emrin e prindit direkt nga tabela parents (jo users) për të shmangur "root"
    $stmt = $pdo->prepare("SELECT id, name FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $parentId = (int)($parentRow['id'] ?? 0);
    $parentName = $parentRow['name'] ?? 'Prind'; // Emri real

    if (!$parentId) throw new Exception('Profili nuk u gjet.');

    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) {
        $stmt = $pdo->prepare("SELECT student_id FROM parent_student WHERE parent_id = ? LIMIT 1");
        $stmt->execute([$parentId]);
        $studentId = (int)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.class_name, c.id as class_id
        FROM students s 
        JOIN parent_student ps ON s.student_id = ps.student_id 
        LEFT JOIN classes c ON s.class_name = c.grade AND s.school_id = c.school_id
        WHERE ps.parent_id = ? AND s.student_id = ?
    ");
    $stmt->execute([$parentId, $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistikat
    $stmt = $pdo->prepare("SELECT SUM(present=1) as p, COUNT(*) as t FROM attendance WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $att = $stmt->fetch();
    $attendanceRate = $att['t'] > 0 ? round(($att['p'] / $att['t']) * 100) : 0;

    $stmt = $pdo->prepare("SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $averageGrade = (float)($stmt->fetchColumn() ?: 0);

    // Grafiku
    $stmt = $pdo->prepare("SELECT grade, created_at FROM grades WHERE student_id = ? ORDER BY created_at ASC LIMIT 10");
    $stmt->execute([$studentId]);
    $gradeHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = []; $chartValues = [];
    foreach ($gradeHistory as $gh) {
        $chartLabels[] = date('d M', strtotime($gh['created_at']));
        $chartValues[] = (float)$gh['grade'];
    }

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 text-sm'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6 max-w-7xl mx-auto pb-6 px-4 text-sm font-normal text-slate-600">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-[24px] border border-slate-100 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-12 -mt-12 w-48 h-48 bg-indigo-50 rounded-full opacity-40"></div>
        <div class="relative z-10">
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Përshëndetje, <?= htmlspecialchars($parentName)?>! 👋</h1>
            <p class="text-slate-500 text-xs mt-1">Ecuria e: <span class="font-semibold text-indigo-600"><?= $student['name'] ?></span> • <?= $student['class_name'] ?></p>
        </div>
        <a href="/E-Shkolla/parent-children" class="relative z-10 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-xl font-semibold text-xs hover:bg-slate-50 transition-all">Ndërro fëmijën</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="space-y-4">
            <div class="bg-white p-5 rounded-[24px] border border-slate-100 shadow-sm">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Prezenca</p>
                <div class="flex items-center justify-between gap-4">
                    <p class="text-2xl font-bold text-slate-800"><?= $attendanceRate ?>%</p>
                    <div class="flex-1 bg-slate-100 h-1 rounded-full overflow-hidden">
                        <div class="bg-blue-500 h-full" style="width: <?= $attendanceRate ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-[24px] border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Mesatarja</p>
                    <p class="text-2xl font-bold text-slate-800"><?= $averageGrade ?></p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-lg">📈</div>
            </div>

            <div class="bg-slate-900 p-5 rounded-[24px] shadow-sm text-white">
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Orari i sotëm</p>
                <p class="text-lg font-bold mt-1"><?= date('l, d M') ?></p>
                <p class="text-[10px] text-slate-400 mt-1 italic">Kontrollo modulin e orarit për detaje.</p>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white p-6 rounded-[24px] border border-slate-100 shadow-sm">
            <h3 class="text-base font-bold text-slate-800">Ecuria Akademike</h3>
            <div class="h-[220px] mt-4">
                <canvas id="gradeTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-[24px] border border-slate-100 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-4">Notat e fundit</h3>
            <div class="space-y-2">
                <?php 
                $stmt = $pdo->prepare("SELECT g.grade, sub.subject_name, g.created_at FROM grades g JOIN subjects sub ON g.subject_id = sub.id WHERE g.student_id = ? ORDER BY g.created_at DESC LIMIT 4");
                $stmt->execute([$studentId]);
                $recentGrades = $stmt->fetchAll();
                if ($recentGrades): foreach ($recentGrades as $rg): ?>
                <div class="flex items-center justify-between p-3 bg-slate-50/50 border border-slate-100 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-white border rounded-lg flex items-center justify-center text-[10px] font-bold text-indigo-500 shadow-sm"><?= substr($rg['subject_name'], 0, 1) ?></div>
                        <div>
                            <h4 class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($rg['subject_name']) ?></h4>
                            <p class="text-[9px] text-slate-400 font-medium"><?= date('d M', strtotime($rg['created_at'])) ?></p>
                        </div>
                    </div>
                    <span class="text-base font-bold <?= $rg['grade'] >= 4 ? 'text-emerald-500' : 'text-amber-500' ?>"><?= $rg['grade'] ?></span>
                </div>
                <?php endforeach; else: echo "<p class='text-xs text-slate-400 italic'>Nuk ka nota të fundit.</p>"; endif; ?>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[24px] border border-slate-100 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-4">Detyrat e fundit</h3>
            <div class="space-y-3">
                <?php 
                if (isset($student['class_id'])):
                    $stmt = $pdo->prepare("SELECT title, due_date FROM assignments WHERE class_id = ? ORDER BY created_at DESC LIMIT 3");
                    $stmt->execute([$student['class_id']]);
                    $assignments = $stmt->fetchAll();
                    if ($assignments): foreach ($assignments as $task): ?>
                    <div class="flex items-start gap-3 p-3 bg-indigo-50/30 rounded-xl border border-indigo-100/50">
                        <div class="mt-1 text-indigo-500">📎</div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-800"><?= htmlspecialchars($task['title']) ?></h4>
                            <p class="text-[9px] font-medium text-indigo-600">Afati: <?= date('d M', strtotime($task['due_date'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; else: echo "<p class='text-xs text-slate-400 italic'>Nuk ka detyra të reja.</p>"; endif;
                endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('gradeTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                data: <?= json_encode($chartValues) ?>,
                borderColor: '#6366f1',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(99, 102, 241, 0.03)',
                pointRadius: 4,
                pointBackgroundColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 1, max: 5, grid: { color: '#f8fafc' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>