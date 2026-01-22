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
   2. RESOLVE parent_id & children
========================= */
// Gjejmë profilin e prindit
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) die('Profili i prindit nuk u gjet');

// Gjejmë të gjithë fëmijët e lidhur për të mundësuar ndërrimin (switcher)
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ? AND s.school_id = ?
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$children) die('Nuk ka fëmijë të lidhur me llogarinë tuaj');

// Përcaktojmë cilin fëmijë po shikojmë (default: i pari në listë)
$studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

// Sigurohemi që prindi ka të drejtë të shohë këtë studentId específico
$currentStudent = null;
foreach ($children as $c) {
    if ($c['student_id'] === $studentId) {
        $currentStudent = $c;
        break;
    }
}
if (!$currentStudent) die('Akses i paautorizuar');

/* =========================
   3. FETCH ATTENDANCE DATA
========================= */
$stmt = $pdo->prepare("
    SELECT 
        sub.name AS subject_name,
        u.name AS teacher_name
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN teachers t ON t.id = a.teacher_id
    JOIN users u ON u.id = t.user_id
    WHERE a.student_id = ? 
      AND a.school_id = ?
    ORDER BY a.created_at DESC
");

$stmt->execute([$studentId, $schoolId]);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistikat
$totalLessons = count($attendanceRecords);
$absences     = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'absent'));
$presenceRate = $totalLessons > 0 ? round((($totalLessons - $absences) / $totalLessons) * 100) : 100;

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-12">
    
    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 bg-indigo-50 rounded-full opacity-50"></div>
        
        <div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">Regjistri i Prezencës 📅</h2>
            <p class="text-slate-500 font-medium mt-1">Po shikoni mungesat dhe prezencën për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($currentStudent['name']) ?></span></p>
        </div>
        
        <?php if (count($children) > 1): ?>
        <div class="flex flex-wrap gap-2 p-1.5 bg-slate-50 rounded-2xl border border-slate-100">
            <?php foreach ($children as $child): ?>
                <a href="?student_id=<?= $child['student_id'] ?>" 
                   class="px-5 py-2.5 rounded-xl text-xs font-black transition-all <?= $child['student_id'] === $studentId ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100' ?>">
                    <?= htmlspecialchars($child['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 print:hidden">

        <!-- Presence Rate -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-emerald-500/20 text-4xl select-none">✅</div>

            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                Pjesëmarrja
            </p>

            <div class="mt-2 flex items-end gap-2">
                <span class="text-3xl font-black text-slate-900 dark:text-white leading-none">
                    <?= $presenceRate ?>%
                </span>
            </div>

            <p class="mt-1 text-xs text-slate-500 font-medium">
                Prezencë totale
            </p>
        </div>

        <!-- Total Absences -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-rose-500/20 text-4xl select-none">❌</div>

            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                Mungesa
            </p>

            <span class="mt-2 block text-3xl font-black text-slate-900 dark:text-white leading-none">
                <?= $absences ?>
            </span>

            <p class="mt-1 text-xs text-slate-500 font-medium">
                Orë të humbura
            </p>
        </div>

        <!-- Weakest Subject -->
        <div class="relative overflow-hidden bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-5 shadow-lg text-white">

            <div class="absolute right-4 top-4 text-white/30 text-4xl select-none">📉</div>

            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80">
                Lënda me më shumë mungesa
            </p>

            <div class="mt-2 text-lg font-black leading-tight">
                <?= $weakestSubject ?? '—' ?>
                <?php if (!empty($weakestCount)): ?>
                    <span class="block text-sm font-medium opacity-80">
                        <?= $weakestCount ?> mungesa
                    </span>
                <?php endif; ?>
            </div>
        </div>

    </div>


    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400">Data</th>
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400">Lënda</th>
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400">Mësuesi</th>
                        <th class="px-8 py-6 text-center text-xs font-bold uppercase tracking-widest text-slate-400">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if ($attendanceRecords): ?>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr class="group hover:bg-slate-50/30 transition-all">
                                <td class="px-8 py-6">
                                    <span class="font-bold text-slate-700">
                                        <?= date('d M, Y', strtotime($record['date'])) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="px-4 py-1.5 bg-indigo-50 text-indigo-700 rounded-xl text-[11px] font-black uppercase">
                                        <?= htmlspecialchars($record['subject_name']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-sm font-bold text-slate-600"><?= htmlspecialchars($record['teacher_name']) ?></span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <?php 
                                    $statusClasses = [
                                        'present' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                        'absent'  => 'bg-red-50 text-red-600 border-red-100',
                                        'late'    => 'bg-amber-50 text-amber-600 border-amber-100'
                                    ];
                                    $statusLabels = [
                                        'present' => 'I Pranishëm',
                                        'absent'  => 'Mungesë',
                                        'late'    => 'Vonesë'
                                    ];
                                    $status = $record['status'];
                                    ?>
                                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase border <?= $statusClasses[$status] ?? 'bg-slate-100' ?>">
                                        <?= $statusLabels[$status] ?? $status ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-8 py-24 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl">🍃</div>
                                    <div>
                                        <p class="text-slate-900 font-bold text-lg">Regjistri është i pastër</p>
                                        <p class="text-slate-400 text-sm">Nuk u gjet asnjë regjistrim i prezencës për këtë fëmijë.</p>
                                    </div>
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
?>