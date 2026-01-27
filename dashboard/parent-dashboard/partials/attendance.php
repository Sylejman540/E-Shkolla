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

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

try {
    // 1. Marrim emrin e prindit (zgjidhja për "root")
    $stmt = $pdo->prepare("SELECT id, name FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentId = (int)($parentRow['id'] ?? 0);
    $parentRealName = $parentRow['name'] ?? 'Prind';

    if (!$parentId) die('Profili nuk u gjet');

    // 2. Marrim listën e fëmijëve
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

    /* ==========================================================
       LOGJIKA E ARKIVIMIT (Vetëm rekordet e ditëve të kaluara)
       Shtojmë: AND a.created_at < CURDATE()
    ========================================================== */
    $stmt = $pdo->prepare("
        SELECT a.present, a.created_at as date, sub.subject_name, u.name AS teacher_name
        FROM attendance a
        JOIN subjects sub ON sub.id = a.subject_id
        JOIN teachers t ON t.id = a.teacher_id
        JOIN users u ON u.id = t.user_id
        WHERE a.student_id = ? 
          AND a.school_id = ? 
          AND a.created_at < CURDATE() 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$studentId, $schoolId]);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistikat bazuar në arkivë
    $totalLessons = count($attendanceRecords);
    $absences     = count(array_filter($attendanceRecords, fn($r) => (int)$r['present'] === 0));
    $presenceRate = $totalLessons > 0 ? round((($totalLessons - $absences) / $totalLessons) * 100) : 100;

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 text-sm'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-10 px-4 text-sm font-normal text-slate-600">
    
    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-indigo-50 rounded-full opacity-40"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Arkiva e Prezencës</h2>
                <p class="text-slate-400 text-xs mt-0.5 italic">Shfaqen vetëm të dhënat e ditëve të përfunduara.</p>
            </div>
            
            <?php if (count($children) > 1): ?>
            <div class="flex gap-1.5 p-1 bg-slate-50 rounded-xl border border-slate-100">
                <?php foreach ($children as $child): ?>
                    <a href="?student_id=<?= $child['student_id'] ?>" 
                       class="px-4 py-1.5 rounded-lg text-[11px] font-bold transition-all <?= $child['student_id'] === $studentId ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-400 hover:text-slate-600' ?>">
                        <?= htmlspecialchars($child['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
        <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Pjesëmarrja (Arkivë)</p>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-bold text-slate-800"><?= $presenceRate ?>%</span>
                <div class="flex-1 bg-slate-100 h-1 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full" style="width: <?= $presenceRate ?>%"></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Mungesa Totale</p>
                <span class="text-2xl font-bold text-slate-800"><?= $absences ?></span>
            </div>
            <div class="w-10 h-10 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center text-xs font-bold">❌</div>
        </div>

        <div class="bg-slate-900 rounded-[20px] p-5 shadow-sm text-white relative">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Informacion</p>
            <p class="text-[11px] mt-2 leading-relaxed opacity-80">Regjistrimet e ditës së sotme do të shfaqen në panel pasi të kalojë mesnata.</p>
        </div>
    </div>

    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Data</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Lënda</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Mësuesi</th>
                    <th class="px-6 py-4 text-center text-[10px] font-bold uppercase text-slate-400">Statusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if ($attendanceRecords): foreach ($attendanceRecords as $record): ?>
                    <tr class="hover:bg-slate-50/30 transition-all">
                        <td class="px-6 py-4 font-bold text-slate-700 text-xs"><?= date('d M, Y', strtotime($record['date'])) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[10px] font-bold uppercase"><?= htmlspecialchars($record['subject_name']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?= htmlspecialchars($record['teacher_name']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ((int)$record['present'] === 1): ?>
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase bg-emerald-50 text-emerald-600 border border-emerald-100">Prezent</span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase bg-rose-50 text-rose-500 border border-rose-100">Mungesë</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center">
                            <p class="text-slate-400 text-xs italic">Nuk ka të dhëna të arkivuara (mungesat e sotme shfaqen nesër).</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>