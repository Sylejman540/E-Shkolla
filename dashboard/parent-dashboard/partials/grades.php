<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   AUTH GUARD
   ========================= */
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

/* =========================
   RESOLVE parent_id
   ========================= */
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) die('Parent profile not found');

/* =========================
   RESOLVE student_id
   ========================= */
$studentId = (int) ($_GET['student_id'] ?? 0);

if (!$studentId) {
    $stmt = $pdo->prepare("SELECT student_id FROM parent_student WHERE parent_id = ? LIMIT 1");
    $stmt->execute([$parentId]);
    $studentId = (int) $stmt->fetchColumn();
}

if (!$studentId) die('No children linked');

/* =========================
   OWNERSHIP CHECK
   ========================= */
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name AS student_name, s.class_name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ? AND s.student_id = ? AND s.school_id = ?
");
$stmt->execute([$parentId, $studentId, $schoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) die('Unauthorized access');

/* =========================
   FETCH GRADES
   ========================= */
$stmt = $pdo->prepare("
    SELECT g.grade, g.created_at, sub.subject_name
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_id = ? AND g.school_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$studentId, $schoolId]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   AVERAGE GRADE
   ========================= */
$stmt = $pdo->prepare("SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = ?");
$stmt->execute([$studentId]);
$averageGrade = (float) ($stmt->fetchColumn() ?: 0);

/* =========================
   FRONTEND - CONTENT
   ========================= */
ob_start();
?>

<div class="space-y-6">
    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tight">Notat e Nxënësit 📝</h2>
            <p class="text-slate-500 font-medium mt-1">
                Po shikoni progresin akademik për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($student['student_name']) ?></span>
            </p>
        </div>
        <div class="flex items-center gap-4 bg-slate-50 px-6 py-3 rounded-2xl border border-slate-100">
            <div class="text-right">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Mesatarja e Përgjithshme</p>
                <p class="text-2xl font-black text-indigo-600 leading-none"><?= $averageGrade ?></p>
            </div>
            <div class="h-10 w-10 bg-indigo-100 rounded-xl flex items-center justify-center text-xl">📈</div>
        </div>
    </div>

    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-8 border-b border-slate-50">
            <h3 class="text-lg font-bold text-slate-800 tracking-tight">Pasqyra e Notave</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-8 py-4 text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50">Lënda</th>
                        <th class="px-8 py-4 text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50">Nota</th>
                        <th class="px-8 py-4 text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50">Data e Vendosjes</th>
                        <th class="px-8 py-4 text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 text-right">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!empty($grades)): ?>
                        <?php foreach ($grades as $row): ?>
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <td class="px-8 py-5">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($row['subject_name']) ?></span>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xl font-black <?= $row['grade'] >= 4 ? 'text-emerald-600' : 'text-red-500' ?>">
                                            <?= htmlspecialchars((string)$row['grade']) ?>
                                        </span>
                                        <div class="hidden sm:block w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full <?= $row['grade'] >= 4 ? 'bg-emerald-400' : 'bg-red-400' ?>" 
                                                 style="width: <?= ($row['grade'] / 5) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-slate-500 text-sm font-medium">
                                    <?= date('d M, Y', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="px-8 py-5 text-right">
                                    <?php if ($row['grade'] == 5): ?>
                                        <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[10px] font-black uppercase rounded-lg border border-amber-100">Shkëlqyeshëm</span>
                                    <?php elseif ($row['grade'] >= 4): ?>
                                        <span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded-lg border border-emerald-100">Kalues</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-50 text-red-600 text-[10px] font-black uppercase rounded-lg border border-red-100">Dobët</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="text-5xl">📄</span>
                                    <p class="text-slate-400 font-bold italic">Nuk ka asnjë notë të regjistruar për këtë periudhë.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-indigo-600 rounded-[32px] p-8 text-white flex flex-col md:flex-row items-center justify-between shadow-lg shadow-indigo-100">
        <div class="space-y-2 text-center md:text-left">
            <h4 class="text-xl font-bold italic">"Edukimi është arma më e fuqishme!"</h4>
            <p class="text-indigo-100 text-sm opacity-80 font-medium">Inkurajoni fëmijën tuaj të përmirësojë rezultatet në lëndët ku ka sfida.</p>
        </div>
        <div class="mt-6 md:mt-0 px-6 py-3 bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 font-bold text-sm">
            Mesatarja: <?= $averageGrade ?> / 5.0
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';