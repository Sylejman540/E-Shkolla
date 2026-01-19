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
    // 1. Gjejmë parent_id real nga tabela parents
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int)$stmt->fetchColumn();

    if (!$parentId) throw new Exception('Profili i prindit nuk u gjet.');

    // 2. Identifikojmë cilin fëmijë po shikojmë
    $studentId = (int)($_GET['student_id'] ?? 0);
    if (!$studentId) {
        $stmt = $pdo->prepare("SELECT student_id FROM parent_student WHERE parent_id = ? LIMIT 1");
        $stmt->execute([$parentId]);
        $studentId = (int)$stmt->fetchColumn();
    }

    if (!$studentId) throw new Exception('Nuk keni asnjë fëmijë të lidhur me llogarinë tuaj.');

    // 3. Verifikojmë pronësinë dhe marrim info për studentin
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.class_name
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE ps.parent_id = ? AND s.student_id = ? AND s.school_id = ?
    ");
    $stmt->execute([$parentId, $studentId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) throw new Exception('Akses i paautorizuar.');

    // 4. KPI: Statistikat (Prezenca, Mesatarja, Detyrat)
    // Prezenca
    $stmt = $pdo->prepare("SELECT SUM(present = 1) as p, COUNT(*) as t FROM attendance WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $att = $stmt->fetch();
    $attendancePercent = $att['t'] > 0 ? round(($att['p'] / $att['t']) * 100) : 0;

    // Mesatarja
    $stmt = $pdo->prepare("SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $averageGrade = (float)($stmt->fetchColumn() ?: 0);

    // Detyrat aktive (Gjejmë klasën dhe pastaj detyrat)
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
    $stmt->execute([$student['class_name'], $schoolId]);
    $classId = (int)$stmt->fetchColumn();

    $pendingAssignments = 0;
    if ($classId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE class_id = ? AND school_id = ? AND due_date >= CURDATE()");
        $stmt->execute([$classId, $schoolId]);
        $pendingAssignments = (int)$stmt->fetchColumn();
    }

} catch (Exception $e) {
    die("<div class='p-10 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

$parentName = 'Prind';
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $parentName = $stmt->fetchColumn() ?: 'Prind';
} catch (PDOException $e) {
    error_log("Database Error in Layout: " . $e->getMessage());
}
ob_start();
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-8 rounded-[32px] border border-slate-100 shadow-sm">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                Përshëndetje, <?= htmlspecialchars($parentName)?>! 👋
            </h1>
            <p class="text-slate-500 font-medium mt-1">
                Po shikoni progresin për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($student['name']) ?></span> (<?= htmlspecialchars($student['class_name']) ?>)
            </p>
        </div>
        <a href="/E-Shkolla/parent-children" class="px-5 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all">
            Ndërro fëmijën
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
            <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl">📅</div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Prezenca</p>
                <p class="text-2xl font-black text-slate-900"><?= $attendancePercent ?>%</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">📈</div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Mesatarja</p>
                <p class="text-2xl font-black text-slate-900"><?= $averageGrade ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm flex items-center gap-5">
            <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl">📝</div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Detyra Aktive</p>
                <p class="text-2xl font-black text-slate-900"><?= $pendingAssignments ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-sm">
            <h3 class="text-xl font-black text-slate-900 mb-6">Notat e fundit</h3>
            <div class="text-center py-10 text-slate-400 italic">Së shpejti: Këtu do të shfaqen notat e fundit të fëmijës.</div>
        </div>

        <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-sm">
            <h3 class="text-xl font-black text-slate-900 mb-6">Aktiviteti i fundit</h3>
            <div class="text-center py-10 text-slate-400 italic">Nuk ka njoftime të reja për këtë fëmijë.</div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
// Thirret Layout-i i Prindit që rregulluam më parë
require_once __DIR__ . '/../index.php';