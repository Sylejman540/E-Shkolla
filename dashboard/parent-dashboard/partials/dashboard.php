<?php
declare(strict_types=1);

/* =====================================================
   1. BACKEND LOGIC (Përpunimi i të dhënave)
===================================================== */
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth Guard
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int)$_SESSION['user']['id'];
$schoolId = (int)$_SESSION['user']['school_id'];

try {
    // 1. Gjejmë parent_id real
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int)$stmt->fetchColumn();

    if (!$parentId) throw new Exception('Profili i prindit nuk u gjet.');

    // 2. Identifikojmë studentin
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) {
        $stmt = $pdo->prepare("SELECT student_id FROM parent_student WHERE parent_id = ? LIMIT 1");
        $stmt->execute([$parentId]);
        $studentId = (int)$stmt->fetchColumn();
    }

    if (!$studentId) throw new Exception('Nuk keni asnjë fëmijë të lidhur.');

    // 3. Info për studentin
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.class_name
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE ps.parent_id = ? AND s.student_id = ? AND s.school_id = ?
    ");
    $stmt->execute([$parentId, $studentId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) throw new Exception('Akses i paautorizuar.');

    // 4. DEFINIMI I VARIABLAVE (KPIs)
    
    // Prezenca
    $stmt = $pdo->prepare("SELECT SUM(present = 1) as p, COUNT(*) as t FROM attendance WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $att = $stmt->fetch();
    $attendanceRate = $att['t'] > 0 ? round(($att['p'] / $att['t']) * 100) : 0;

    // Mesatarja
    $stmt = $pdo->prepare("SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $averageGrade = (float)($stmt->fetchColumn() ?: 0);

    // Detyrat Aktive
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
    $stmt->execute([$student['class_name'], $schoolId]);
    $classId = (int)$stmt->fetchColumn();

    $pendingAssignments = 0;
    if ($classId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE class_id = ? AND school_id = ? AND due_date >= CURDATE()");
        $stmt->execute([$classId, $schoolId]);
        $pendingAssignments = (int)$stmt->fetchColumn();
    }

    // Të dhënat për Grafikun (Chart Labels & Values)
    $stmt = $pdo->prepare("SELECT grade, created_at FROM grades WHERE student_id = ? ORDER BY created_at ASC LIMIT 10");
    $stmt->execute([$studentId]);
    $gradeHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = [];
    $chartValues = [];
    foreach ($gradeHistory as $gh) {
        $chartLabels[] = date('d M', strtotime($gh['created_at']));
        $chartValues[] = (float)$gh['grade'];
    }

    // Emri i prindit për Header
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $parentName = $stmt->fetchColumn() ?: 'Prind';

} catch (Exception $e) {
    die("<div class='p-10 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-8 max-w-7xl mx-auto pb-10 px-4">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-white p-8 rounded-[40px] border border-slate-100 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-indigo-50 rounded-full opacity-50"></div>
        
        <div class="relative z-10">
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">
                Përshëndetje, <?= htmlspecialchars($parentName)?>! 👋
            </h1>
            <div class="flex items-center gap-3 mt-2">
                <span class="px-3 py-1 bg-indigo-600 text-white text-xs font-bold rounded-full uppercase">Student</span>
                <p class="text-slate-500 font-medium">
                    <?= htmlspecialchars($student['name']) ?> • <span class="text-slate-900 font-bold"><?= htmlspecialchars($student['class_name']) ?></span>
                </p>
            </div>
        </div>
        
        <div class="flex gap-3 relative z-10">
            <a href="/E-Shkolla/parent-children" class="flex items-center gap-2 px-6 py-3 bg-white border border-slate-200 text-slate-700 rounded-2xl font-bold text-sm hover:bg-slate-50 transition-all shadow-sm">
                <span>🔄</span> Ndërro fëmijën
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">📅</div>
                    <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase tracking-wider">Mujore</span>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prezenca në mësim</p>
                <div class="flex items-end gap-2">
                    <p class="text-3xl font-black text-slate-900"><?= $attendanceRate ?>%</p>
                    <div class="mb-1 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-blue-600 h-full" style="width: <?= $attendanceRate ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">📈</div>
                    <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg uppercase tracking-wider">Aktuale</span>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Mesatarja e Notave</p>
                <p class="text-3xl font-black text-slate-900"><?= $averageGrade ?></p>
            </div>

            <div class="bg-indigo-600 p-6 rounded-[32px] shadow-lg shadow-indigo-100 relative overflow-hidden">
                <div class="relative z-10 text-white">
                    <p class="text-indigo-100 text-xs font-bold uppercase tracking-widest">Detyra Aktive</p>
                    <p class="text-4xl font-black mt-1"><?= $pendingAssignments ?></p>
                </div>
                <span class="absolute -right-4 -bottom-4 text-8xl opacity-10">📝</span>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white p-8 rounded-[40px] border border-slate-100 shadow-sm">
            <h3 class="text-xl font-black text-slate-900 mb-2">Ecuria e Notave</h3>
            <p class="text-sm text-slate-500 mb-8">Vizualizimi i progresit akademik</p>
            <div class="h-[300px]">
                <canvas id="gradeTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-8 rounded-[40px] border border-slate-100 shadow-sm">
            <h3 class="text-xl font-black text-slate-900 mb-6">Notat e fundit</h3>
            <div class="space-y-4">
                <?php 
                $stmt = $pdo->prepare("SELECT g.grade, sub.subject_name, g.created_at FROM grades g JOIN subjects sub ON g.subject_id = sub.id WHERE g.student_id = ? ORDER BY g.created_at DESC LIMIT 4");
                $stmt->execute([$studentId]);
                $recent = $stmt->fetchAll();
                
                if ($recent): foreach ($recent as $rg): ?>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center font-bold text-indigo-600 shadow-sm">
                            <?= substr($rg['subject_name'], 0, 1) ?>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($rg['subject_name']) ?></h4>
                            <p class="text-[10px] text-slate-400"><?= date('d M, Y', strtotime($rg['created_at'])) ?></p>
                        </div>
                    </div>
                    <span class="text-xl font-black <?= $rg['grade'] >= 4 ? 'text-emerald-500' : 'text-amber-500' ?>">
                        <?= $rg['grade'] ?>
                    </span>
                </div>
                <?php endforeach; else: ?>
                    <p class="text-center text-slate-400 italic py-4">Nuk ka nota të reja.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[40px] border border-slate-100 shadow-sm">
            <h3 class="text-xl font-black text-slate-900 mb-6">Njoftimet</h3>
            <div class="space-y-4">
                <div class="p-4 border-l-4 border-indigo-500 bg-indigo-50/30 rounded-r-2xl">
                    <h4 class="text-sm font-bold text-slate-800 italic">Së shpejti...</h4>
                    <p class="text-xs text-slate-500 mt-1">Këtu do të shfaqen njoftimet nga stafi i shkollës.</p>
                </div>
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
                borderColor: '#4f46e5',
                borderWidth: 4,
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(79, 70, 229, 0.05)',
                pointRadius: 6,
                pointBackgroundColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 1, max: 5, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>