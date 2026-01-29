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

// Pagination Settings
$limit = 10; // Records per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    $stmt = $pdo->prepare("SELECT id, name FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentId = (int)($parentRow['id'] ?? 0);

    if (!$parentId) die('Profili nuk u gjet');

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

    // 1. Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(created_at)) 
        FROM attendance 
        WHERE student_id = ? AND school_id = ? AND created_at < CURDATE()
    ");
    $countStmt->execute([$studentId, $schoolId]);
    $totalDays = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalDays / $limit);

    // 2. Fetch Paginated Records
    $stmt = $pdo->prepare("
        SELECT 
            DATE(a.created_at)               AS day,
            COUNT(*)                         AS total_lessons,
            SUM(CASE WHEN a.present = 1 THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.present = 0 THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.excused = 1 THEN 1 ELSE 0 END) AS excused_count
        FROM attendance a
        WHERE a.student_id = ?
          AND a.school_id  = ?
          AND a.created_at < CURDATE()
        GROUP BY DATE(a.created_at)
        ORDER BY day DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$studentId, $schoolId]);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats calculation (based on current view or you can query total if preferred)
    $grandTotalLessons = 0;
    $grandTotalAbsences = 0;
    foreach($attendanceRecords as $row) {
        $grandTotalLessons += (int)$row['total_lessons'];
        $grandTotalAbsences += (int)$row['absent_count'];
    }
    
    $presenceRate = $grandTotalLessons > 0 
        ? round((($grandTotalLessons - $grandTotalAbsences) / $grandTotalLessons) * 100) 
        : 100;

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
                <p class="text-slate-400 text-xs mt-0.5 italic">Përmbledhje ditore e mungesave (Vetëm ditët e kaluara).</p>
            </div>
            
            <?php if (count($children) > 1): ?>
            <div class="flex gap-1.5 p-1 bg-slate-50 rounded-xl border border-slate-100">
                <?php foreach ($children as $child): ?>
                    <a href="?student_id=<?= $child['student_id'] ?>" 
                       class="px-4 py-1.5 rounded-lg text-[11px] font-bold transition-all <?= (int)$child['student_id'] === $studentId ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-400 hover:text-slate-600' ?>">
                        <?= htmlspecialchars($child['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
        <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Pjesëmarrja (Faqja <?= $page ?>)</p>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-bold text-slate-800"><?= $presenceRate ?>%</span>
                <div class="flex-1 bg-slate-100 h-1 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full" style="width: <?= $presenceRate ?>%"></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Mungesat në këtë faqe</p>
                <span class="text-2xl font-bold text-slate-800"><?= $grandTotalAbsences ?></span>
            </div>
            <div class="w-10 h-10 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center text-xs font-bold">❌</div>
        </div>

        <div class="bg-slate-900 rounded-[20px] p-5 shadow-sm text-white relative">
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Informacion</p>
            <p class="text-[11px] mt-2 leading-relaxed opacity-80">Regjistrimet e ditës së sotme do të shfaqen këtu pasi të kalojë mesnata.</p>
        </div>
    </div>

    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Data</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Totali i Orëve</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Prezent</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Mungesa</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-400">Arsyetuar</th>
                    <th class="px-6 py-4 text-center text-[10px] font-bold uppercase text-slate-400">Statusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php if (empty($attendanceRecords)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-slate-400 italic">Nuk u gjet asnjë regjistrim në arkivë.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendanceRecords as $d): ?>
                <tr class="hover:bg-slate-50/40 transition">
                    <td class="px-6 py-4 text-xs font-bold text-slate-700">
                        <?= date('d M Y', strtotime($d['day'])) ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= $d['total_lessons'] ?> orë
                    </td>
                    <td class="px-6 py-4 text-xs text-emerald-600 font-bold">
                        <?= $d['present_count'] ?> ✓
                    </td>
                    <td class="px-6 py-4 text-xs text-rose-500 font-bold">
                        <?= $d['absent_count'] ?> ✗
                    </td>
                    <td class="px-6 py-4 text-xs text-amber-500 font-bold">
                        <?= $d['excused_count'] ?> ~
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($d['absent_count'] > 0): ?>
                            <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 text-[9px] font-bold">
                                Ka mungesa
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[9px] font-bold">
                                Në rregull
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-6">
        <?php if ($page > 1): ?>
            <a href="?student_id=<?= $studentId ?>&page=<?= $page - 1 ?>" class="p-2 bg-white border border-slate-100 rounded-lg text-slate-400 hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
        <?php endif; ?>

        <div class="flex gap-1 bg-slate-50 p-1 rounded-xl border border-slate-100">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?student_id=<?= $studentId ?>&page=<?= $i ?>" 
                   class="px-3.5 py-1.5 rounded-lg text-[11px] font-bold transition-all <?= $i === $page ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-400 hover:text-slate-600' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ($page < $totalPages): ?>
            <a href="?student_id=<?= $studentId ?>&page=<?= $page + 1 ?>" class="p-2 bg-white border border-slate-100 rounded-lg text-slate-400 hover:text-indigo-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>