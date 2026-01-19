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

if (!$parentId) die('Profili i prindit nuk u gjet');

/* =========================
   RESOLVE student_id
========================= */
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ? AND s.school_id = ?
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$children) die('Nuk ka fëmijë të lidhur');

$studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

/* =========================
   OWNERSHIP CHECK
========================= */
$student = null;
foreach ($children as $c) {
    if ($c['student_id'] === $studentId) {
        $student = $c;
        break;
    }
}
if (!$student) die('Akses i paautorizuar');

/* =========================
   ATTENDANCE DATA & STATS
========================= */
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.created_at) AS date,
        sub.name AS subject_name,
        t.name AS teacher_name,
        a.present
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN teachers t ON t.id = a.teacher_id
    WHERE a.student_id = ? AND a.school_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$studentId, $schoolId]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kalkulimi i statistikave për këtë fëmijë
$totalHours = count($attendance);
$absences = count(array_filter($attendance, fn($row) => !$row['present']));
$presenceRate = $totalHours > 0 ? round((($totalHours - $absences) / $totalHours) * 100) : 100;

/* =========================
   FRONTEND - CONTENT
========================= */
ob_start();
?>

<div class="space-y-6">
    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tight">Prezenca 📅</h2>
            <p class="text-slate-500 font-medium">Po shikoni regjistrin për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($student['name']) ?></span></p>
        </div>
        
        <?php if (count($children) > 1): ?>
        <div class="flex gap-2">
            <?php foreach ($children as $child): ?>
                <a href="?student_id=<?= $child['student_id'] ?>" 
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $child['student_id'] === $studentId ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <?= htmlspecialchars($child['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Përqindja</p>
            <p class="text-3xl font-black text-indigo-600"><?= $presenceRate ?>%</p>
        </div>
        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm border-l-4 border-l-red-400">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Mungesa Totale</p>
            <p class="text-3xl font-black text-slate-900"><?= $absences ?> <span class="text-sm text-slate-400 font-normal">orë</span></p>
        </div>
        <div class="bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm border-l-4 border-l-emerald-400">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Prezencë</p>
            <p class="text-3xl font-black text-slate-900"><?= $totalHours - $absences ?> <span class="text-sm text-slate-400 font-normal">orë</span></p>
        </div>
    </div>

    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-50">
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Data</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Lënda</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Mësuesi</th>
                        <th class="px-8 py-5 text-center text-xs font-bold uppercase tracking-widest text-slate-400">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if ($attendance): ?>
                        <?php foreach ($attendance as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5 font-semibold text-slate-700">
                                    <?= date('d/m/Y', strtotime($row['date'])) ?>
                                </td>
                                <td class="px-8 py-5">
                                    <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-bold">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-5 text-slate-600 font-medium">
                                    <?= htmlspecialchars($row['teacher_name']) ?>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <?php if ($row['present']): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-600 border border-emerald-100">
                                            I pranishëm
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-600 border border-red-100">
                                            Mungesë
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="text-5xl">📅</span>
                                    <p class="text-slate-400 font-bold italic tracking-tight">Nuk ka të dhëna për prezencën e fëmijës.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';