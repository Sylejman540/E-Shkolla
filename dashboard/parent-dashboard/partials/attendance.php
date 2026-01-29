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
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int) $stmt->fetchColumn();

    if (!$parentId) die('Profili i prindit nuk u gjet');

    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name 
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE ps.parent_id = ? AND s.school_id = ?
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$children) die('Nuk keni fëmijë të lidhur');

    $studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

    /* ==========================================================
        LOGJIKA E PAGINIMIT & ARKIVIMIT (Vonesa 24 orë)
    ========================================================== */
    $limit = 10; 
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Count total for pagination (Archived only)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id = ? AND school_id = ? AND created_at < CURDATE()");
    $countStmt->execute([$studentId, $schoolId]);
    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $stmt = $pdo->prepare("
        SELECT g.grade, g.created_at, sub.subject_name, u.name AS teacher_name
        FROM grades g
        JOIN subjects sub ON sub.id = g.subject_id
        JOIN teachers t ON t.id = g.teacher_id
        JOIN users u ON u.id = t.user_id
        WHERE g.student_id = ? 
        AND g.school_id = ?
        AND g.created_at < CURDATE()
        ORDER BY g.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$studentId, $schoolId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mesatarja llogaritet mbi të gjitha notat e arkivuara (jo vetëm faqen aktuale)
    $stmtAvg = $pdo->prepare("SELECT AVG(grade) FROM grades WHERE student_id = ? AND school_id = ? AND created_at < CURDATE()");
    $stmtAvg->execute([$studentId, $schoolId]);
    $averageGrade = round((float)$stmtAvg->fetchColumn(), 2);

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 text-sm'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-10 px-4 text-sm font-normal text-slate-600">
    
    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm p-6 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1.5 h-full bg-indigo-500"></div>
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 relative z-10">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Notat & Rezultatet</h2>
                <p class="text-slate-400 text-xs mt-0.5 italic">Arkiva akademike për: <span class="font-bold text-indigo-500"><?php 
                    $currentChild = array_values(array_filter($children, fn($c) => (int)$c['student_id'] === $studentId))[0] ?? $children[0];
                    echo htmlspecialchars($currentChild['name']); 
                ?></span></p>
            </div>

            <div class="flex items-center gap-4 bg-slate-50 px-5 py-3 rounded-2xl border border-slate-100">
                <div class="text-right">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Mesatarja</p>
                    <p class="text-2xl font-bold text-indigo-600 leading-none"><?= number_format($averageGrade, 2) ?></p>
                </div>
                <div class="h-10 w-10 bg-white rounded-xl flex items-center justify-center shadow-sm text-lg">🏆</div>
            </div>
        </div>

        <?php if (count($children) > 1): ?>
        <div class="mt-4 flex gap-1.5">
            <?php foreach ($children as $child): ?>
                <a href="?student_id=<?= $child['student_id'] ?>" 
                    class="px-4 py-1.5 rounded-lg text-[10px] font-bold transition-all <?= (int)$child['student_id'] === $studentId ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-50 text-slate-400 hover:bg-slate-100' ?>">
                    <?= htmlspecialchars($child['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
            <h3 class="text-sm font-bold text-slate-700">Historiku i Vlerësimeve</h3>
            <span class="text-[10px] font-bold text-slate-400 uppercase">Totali: <?= $totalRecords ?> Nota</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Lënda</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Nota</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Mësuesi</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-400">Data</th>
                        <th class="px-6 py-4 text-right text-[10px] font-bold uppercase tracking-widest text-slate-400">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!empty($grades)): ?>
                        <?php foreach ($grades as $row): ?>
                            <tr class="group hover:bg-slate-50/30 transition-all">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-700 text-xs"><?= htmlspecialchars($row['subject_name']) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg font-bold <?= $row['grade'] >= 4 ? 'text-emerald-500' : ($row['grade'] >= 2 ? 'text-amber-500' : 'text-rose-500') ?>">
                                            <?= $row['grade'] ?>
                                        </span>
                                        <div class="w-12 h-1 bg-slate-100 rounded-full overflow-hidden hidden sm:block">
                                            <div class="h-full <?= $row['grade'] >= 4 ? 'bg-emerald-400' : ($row['grade'] >= 2 ? 'bg-amber-400' : 'bg-rose-400') ?>" 
                                                 style="width: <?= ($row['grade'] / 5) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 font-medium"><?= htmlspecialchars($row['teacher_name']) ?></td>
                                <td class="px-6 py-4 text-xs text-slate-400"><?= date('d M, Y', strtotime($row['created_at'])) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <?php 
                                        $label = $row['grade'] == 5 ? 'Shkëlqyeshëm' : ($row['grade'] >= 4 ? 'Shumë mirë' : ($row['grade'] >= 2 ? 'Kalues' : 'Mbetës'));
                                        $color = $row['grade'] == 5 ? 'bg-indigo-50 text-indigo-500 border-indigo-100' : ($row['grade'] >= 4 ? 'bg-emerald-50 text-emerald-500 border-emerald-100' : ($row['grade'] >= 2 ? 'bg-amber-50 text-amber-500 border-amber-100' : 'bg-rose-50 text-rose-500 border-rose-100'));
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-bold uppercase border <?= $color ?>">
                                        <?= $label ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-slate-400 text-xs italic">
                                Nuk ka nota të arkivuara (vlerësimet e sotme shfaqen pas 24 orësh).
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

    <div class="bg-slate-900 rounded-[24px] p-6 text-white shadow-lg flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-center md:text-left">
            <h4 class="text-lg font-bold mb-1">Pse nuk shoh notën e sotme?</h4>
            <p class="text-slate-400 text-xs leading-relaxed max-w-md">
                Për të inkurajuar komunikimin direkt mes nxënësit dhe prindit, sistemi arkivon vlerësimet dhe i shfaq ato me një vonesë prej 24 orësh.
            </p>
        </div>
        <div class="bg-white/5 backdrop-blur px-4 py-3 rounded-xl border border-white/10 text-center min-w-[160px]">
            <p class="text-[9px] font-bold uppercase text-slate-500 mb-1">Statusi Aktual</p>
            <span class="text-sm font-bold">
                <?= $averageGrade >= 4.5 ? '🎖️ Ekselent' : ($averageGrade >= 3 ? '✅ Sukses' : '📈 Në Progres') ?>
            </span>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>