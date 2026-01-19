<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   1. AUTH GUARD
========================= */
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

/* =========================
   2. IDENTIFIKIMI I PRINDIT DHE FËMIJËVE
========================= */
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) die('Profili i prindit nuk u gjet');

// Marrja e listës së fëmijëve për switcher-in
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name 
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ? AND s.school_id = ?
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$children) die('Nuk keni fëmijë të lidhur me këtë llogari');

// Përcaktimi i studentit që po shikohet
$studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

// Ownership Check (Siguria)
$currentStudent = null;
foreach ($children as $c) {
    if ($c['student_id'] === $studentId) {
        $currentStudent = $c;
        break;
    }
}
if (!$currentStudent) die('Akses i paautorizuar');

/* =========================
   3. MARRJA E NOTAVE DHE MESATARJA
========================= */
// 3.1 Lista e notave të fundit
$stmt = $pdo->prepare("
    SELECT g.grade, g.created_at, sub.name AS subject_name, u.name AS teacher_name
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    JOIN teachers t ON t.id = g.teacher_id
    JOIN users u ON u.id = t.user_id
    WHERE g.student_id = ? AND g.school_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$studentId, $schoolId]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3.2 Llogaritja e mesatares
$stmt = $pdo->prepare("SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = ?");
$stmt->execute([$studentId]);
$averageGrade = (float) ($stmt->fetchColumn() ?: 0);

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-8 pb-12">
    
    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-2 h-full bg-indigo-600"></div>
        
        <div class="relative">
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">Notat & Rezultatet 📝</h2>
            <p class="text-slate-500 font-medium mt-1">
                Po shikoni progresin akademik për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($currentStudent['name']) ?></span>
            </p>
            
            <?php if (count($children) > 1): ?>
            <div class="mt-4 flex gap-2">
                <?php foreach ($children as $child): ?>
                    <a href="?student_id=<?= $child['student_id'] ?>" 
                       class="px-4 py-1.5 rounded-xl text-[11px] font-black uppercase tracking-wider transition-all <?= $child['student_id'] === $studentId ? 'bg-indigo-600 text-white shadow-md' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
                        <?= htmlspecialchars($child['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-5 bg-indigo-50/50 px-8 py-5 rounded-[32px] border border-indigo-100 min-w-[240px]">
            <div class="text-right">
                <p class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.2em]">Mesatarja</p>
                <p class="text-4xl font-black text-indigo-700 leading-none"><?= number_format($averageGrade, 2) ?></p>
            </div>
            <div class="h-14 w-14 bg-white rounded-2xl flex items-center justify-center text-2xl shadow-sm border border-indigo-100">🏆</div>
        </div>
    </div>

    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-8 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800 tracking-tight">Historiku i Vlerësimeve</h3>
            <span class="text-xs font-bold text-slate-400 bg-slate-50 px-3 py-1 rounded-lg border border-slate-100">
                Totali: <?= count($grades) ?> Nota
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/30">
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Lënda</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Vlerësimi</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Mësuesi</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Data</th>
                        <th class="px-8 py-5 text-right text-xs font-bold uppercase tracking-widest text-slate-400">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!empty($grades)): ?>
                        <?php foreach ($grades as $row): ?>
                            <tr class="group hover:bg-slate-50/50 transition-all">
                                <td class="px-8 py-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 text-base"><?= htmlspecialchars($row['subject_name']) ?></span>
                                        <span class="text-[10px] text-slate-400 uppercase font-bold tracking-tighter">Vlerësim Akademik</span>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <span class="text-2xl font-black <?= $row['grade'] >= 4 ? 'text-emerald-600' : ($row['grade'] >= 2 ? 'text-amber-500' : 'text-rose-500') ?>">
                                            <?= $row['grade'] ?>
                                        </span>
                                        <div class="hidden sm:block w-20 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full <?= $row['grade'] >= 4 ? 'bg-emerald-400' : ($row['grade'] >= 2 ? 'bg-amber-400' : 'bg-rose-400') ?>" 
                                                 style="width: <?= ($row['grade'] / 5) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-sm font-bold text-slate-600"><?= htmlspecialchars($row['teacher_name']) ?></span>
                                </td>
                                <td class="px-8 py-6 text-slate-500 text-sm font-medium">
                                    <?= date('d M, Y', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <?php 
                                        $label = $row['grade'] == 5 ? 'Shkëlqyeshëm' : ($row['grade'] >= 4 ? 'Shumë mirë' : ($row['grade'] >= 2 ? 'Kalues' : 'Mbetës'));
                                        $color = $row['grade'] == 5 ? 'bg-indigo-50 text-indigo-600 border-indigo-100' : ($row['grade'] >= 4 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : ($row['grade'] >= 2 ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-rose-50 text-rose-600 border-rose-100'));
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border <?= $color ?>">
                                        <?= $label ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-8 py-24 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl">📄</div>
                                    <div class="space-y-1">
                                        <p class="text-slate-900 font-bold text-lg">Nuk ka nota për momentin</p>
                                        <p class="text-slate-400 text-sm">Vlerësimet e mësuesve do të shfaqen këtu menjëherë pas publikimit.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-gradient-to-r from-indigo-600 to-violet-700 rounded-[40px] p-10 text-white shadow-xl shadow-indigo-100 flex flex-col md:flex-row items-center justify-between gap-8">
        <div class="max-w-xl text-center md:text-left">
            <h4 class="text-2xl font-black tracking-tight mb-2 italic">"Rruga drejt suksesit fillon me punë!"</h4>
            <p class="text-indigo-100 font-medium opacity-90 leading-relaxed">
                Monitorimi i rregullt i notave ndihmon në identifikimin e hershëm të vështirësive. 
                Mesatarja aktuale është <?= number_format($averageGrade, 2) ?>. Synoni gjithmonë më lart!
            </p>
        </div>
        <div class="shrink-0">
            <div class="bg-white/10 backdrop-blur-xl rounded-[32px] p-6 border border-white/20 text-center">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-200 mb-1">Statusi Aktual</p>
                <span class="text-xl font-black">
                    <?= $averageGrade >= 4.5 ? '🎖️ Student Ekselent' : ($averageGrade >= 3 ? '✅ Sukses' : '📈 Nevojitet Punë') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>