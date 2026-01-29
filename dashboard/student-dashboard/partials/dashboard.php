<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Student-Only Access Control
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'student') {
    header('Location: /login.php');
    exit;
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

try {
    // 2. Fetch Core Student & Class Info
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name AS s_name, c.grade AS c_name, c.id AS c_id, u.name AS t_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN teachers t ON c.class_header = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE s.user_id = ? AND s.school_id = ? LIMIT 1
    ");
    $stmt->execute([$userId, $schoolId]);
    $student = $stmt->fetch();
    if (!$student) throw new Exception("Student not found.");

    $studentId = (int)$student['student_id'];
    $classId   = (int)$student['c_id'];

    // 3. Attendance Logic (Today & Rate)
    $stmt = $pdo->prepare("
        SELECT SUM(present) as p, SUM(missing) as m, SUM(excused) as e
        FROM attendance 
        WHERE student_id = ? AND school_id = ? AND lesson_date = CURDATE()
    ");
    $stmt->execute([$studentId, $schoolId]);
    $today = $stmt->fetch();

    $statusLabel = 'Pa regjistruar';
    $statusColor = 'bg-slate-50 text-slate-400 border-slate-100';
    if ($today && ($today['p'] + $today['m'] + $today['e']) > 0) {
        if ($today['m'] > 0) { 
            $statusLabel = 'Mungon'; 
            $statusColor = 'bg-rose-50 text-rose-500 border-rose-100'; 
        } elseif ($today['e'] > 0) { 
            $statusLabel = 'Arsyetuar'; 
            $statusColor = 'bg-amber-50 text-amber-500 border-amber-100'; 
        } else { 
            $statusLabel = 'Prezent'; 
            $statusColor = 'bg-emerald-50 text-emerald-500 border-emerald-100'; 
        }
    }

    $stmt = $pdo->prepare("SELECT (SUM(present)/COUNT(*))*100 as rate FROM attendance WHERE student_id = ? AND school_id = ? AND MONTH(lesson_date) = MONTH(CURDATE())");
    $stmt->execute([$studentId, $schoolId]);
    $attendanceRate = (int)($stmt->fetch()['rate'] ?? 0);

    // 4. Grades Logic (Average & Recent)
    $stmtAvg = $pdo->prepare("SELECT AVG(grade) FROM grades WHERE student_id = ? AND school_id = ?");
    $stmtAvg->execute([$studentId, $schoolId]);
    $averageGrade = round((float)$stmtAvg->fetchColumn(), 2);

    $stmtGrades = $pdo->prepare("
        SELECT g.grade, sub.subject_name, g.created_at
        FROM grades g
        JOIN subjects sub ON sub.id = g.subject_id
        WHERE g.student_id = ? AND g.school_id = ?
        ORDER BY g.created_at DESC LIMIT 4
    ");
    $stmtGrades->execute([$studentId, $schoolId]);
    $recentGrades = $stmtGrades->fetchAll();

    // 5. Assignments
    $stmtAss = $pdo->prepare("
        SELECT title, due_date FROM assignments 
        WHERE class_id = ? AND school_id = ? AND due_date >= CURDATE() AND status = 'active'
        ORDER BY due_date ASC LIMIT 3
    ");
    $stmtAss->execute([$classId, $schoolId]);
    $assignments = $stmtAss->fetchAll();

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<?php
// ... [Keep your existing PHP Logic/Auth at the top unchanged] ...
ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-10 space-y-8 font-sans antialiased text-slate-600">
    
    <div class="flex flex-col md:flex-row justify-between items-center bg-white p-7 rounded-[28px] border border-slate-100 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Mirëseerdhe, <?= explode(' ', $student['s_name'])[0] ?>! 👋</h1>
            <p class="text-slate-400 text-sm mt-1 font-normal">
                Klasa: <span class="font-medium text-indigo-500"><?= $student['c_name'] ?></span> • 
                Kujdestari: <span class="text-slate-500"><?= $student['t_name'] ?? 'Pa caktuar' ?></span>
            </p>
        </div>
        <div class="mt-5 md:mt-0 px-5 py-2.5 rounded-2xl border <?= $statusColor ?> flex items-center gap-3">
            <div class="w-2 h-2 rounded-full animate-pulse bg-current"></div>
            <span class="text-[11px] font-semibold uppercase tracking-widest"><?= $statusLabel ?> Sot</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-blue-900 p-7 rounded-[28px] text-white shadow-md relative overflow-hidden">
            <p class="text-[10px] font-semibold uppercase tracking-[0.15em] opacity-60">Mesatarja Përgjithshme</p>
            <div class="flex items-end gap-2 mt-2">
                <h2 class="text-4xl font-medium tracking-tighter"><?= number_format($averageGrade, 2) ?></h2>
                <span class="text-xs mb-2 opacity-50">/ 5.00</span>
            </div>
            <div class="mt-5">
                <span class="text-[10px] bg-white/15 px-3 py-1 rounded-lg font-semibold uppercase tracking-wider">
                    <?= $averageGrade >= 4.5 ? '🎖️ Ekselent' : ($averageGrade >= 3 ? '✅ Sukses' : '📈 Në Progres') ?>
                </span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5">
            <div class="relative w-20 h-10">
                <canvas id="miniGauge"></canvas>
                <div class="absolute inset-0 flex items-center justify-center pt-4">
                    <span class="text-xs font-bold text-slate-700"><?= $attendanceRate ?>%</span>
                </div>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pjesëmarrja</p>
                <p class="text-sm font-medium text-slate-600">Muaji Aktual</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5">
            <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-xl shadow-inner border border-slate-100">⏳</div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Detyra Aktive</p>
                <p class="text-sm font-medium text-slate-600"><?= count($assignments) ?> në pritje</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-white p-8 rounded-[28px] border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-bold text-slate-800 tracking-tight">Vlerësimet e Fundit</h3>
                <a href="/E-Shkolla/grades-archive" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600 transition-colors">Shih Arkivën</a>
            </div>
            <div class="space-y-4">
                <?php foreach ($recentGrades as $g): ?>
                <div class="flex items-center justify-between p-4 bg-slate-50/40 rounded-2xl border border-transparent hover:border-slate-100 hover:bg-white transition-all duration-200">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-white rounded-xl border border-slate-100 flex items-center justify-center text-sm font-bold <?= $g['grade'] >= 4 ? 'text-emerald-500' : 'text-amber-500' ?> shadow-sm">
                            <?= $g['grade'] ?>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($g['subject_name']) ?></p>
                            <p class="text-[11px] text-slate-400 font-medium"><?= date('d M, Y', strtotime($g['created_at'])) ?></p>
                        </div>
                    </div>
                    <div class="h-1.5 w-1.5 rounded-full <?= $g['grade'] >= 4 ? 'bg-emerald-400' : 'bg-amber-400' ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[28px] border border-slate-100 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-bold text-slate-800 tracking-tight">Detyrat e Ardhshme</h3>
                <a href="/E-Shkolla/assignments" class="text-xs font-semibold text-indigo-500 hover:text-indigo-600 transition-colors">Gjithçka</a>
            </div>
            <div class="space-y-4">
                <?php if ($assignments): foreach ($assignments as $a): ?>
                <div class="group flex items-center justify-between p-4 border border-slate-100 rounded-2xl hover:bg-slate-50/50 transition-all">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($a['title']) ?></p>
                        <p class="text-[11px] text-slate-400 font-medium italic mt-0.5">Afati: <?= date('d M', strtotime($a['due_date'])) ?></p>
                    </div>
                    <div class="bg-white border border-slate-200 text-slate-500 px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-tight">
                        E hapur
                    </div>
                </div>
                <?php endforeach; else: ?>
                    <div class="text-center py-10">
                        <p class="text-slate-400 text-sm italic font-medium">Nuk ka detyra aktive 🙌</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('miniGauge').getContext('2d'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?= $attendanceRate ?>, <?= 100 - $attendanceRate ?>],
                backgroundColor: ['#6366f1', '#f1f5f9'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
                borderRadius: 8
            }]
        },
        options: {
            cutout: '82%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>