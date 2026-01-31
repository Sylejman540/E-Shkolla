<?php
declare(strict_types=1);

/* =====================================================
    1. BACKEND LOGIC
===================================================== */
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'parent') {
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int)$_SESSION['user']['id'];
$schoolId = (int)$_SESSION['user']['school_id'];

try {
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int)$stmt->fetchColumn();

    if (!$parentId) throw new Exception("Profili i prindit nuk u gjet.");

    $stmt = $pdo->prepare("
        SELECT 
            s.student_id, 
            s.name AS student_name, 
            s.class_name, 
            s.status,
            (SELECT ROUND(AVG(grade), 2) 
            FROM grades 
            WHERE student_id = s.student_id) AS avg_grade
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE 
            ps.parent_id = ?
            AND s.school_id = ?
            AND s.status = 'active'
        ORDER BY s.name ASC
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 font-medium text-sm'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-8 pb-10 px-6 text-sm">
    
    <div class="space-y-0.5">
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Fëmijët e Mi</h1>
        <p class="text-slate-400 font-medium text-xs">Menaxhoni progresin akademik të fëmijëve tuaj</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (!empty($children)): ?>
            <?php foreach ($children as $child): 
                $initials = strtoupper(substr($child['student_name'] ?? 'S', 0, 1));
            ?>
                <div class="group bg-white rounded-[20px] border border-slate-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center font-bold text-lg transition-colors group-hover:bg-indigo-600 group-hover:text-white">
                                <?= $initials ?>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-700 leading-tight">
                                    <?= htmlspecialchars($child['student_name']) ?>
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                                    <?= htmlspecialchars($child['class_name']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-3 border-y border-slate-50">
                            <div>
                                <p class="text-[9px] font-bold text-slate-300 uppercase tracking-tighter">Mesatarja</p>
                                <p class="text-base font-bold text-slate-600"><?= $child['avg_grade'] ?: '—' ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold text-slate-300 uppercase tracking-tighter">Gjendja</p>
                                <div class="flex items-center justify-end gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $child['status'] === 'active' ? 'bg-emerald-400' : 'bg-slate-300' ?>"></span>
                                    <span class="text-xs font-semibold text-slate-500">
                                        <?= $child['status'] === 'active' ? 'Aktive' : 'Jo Aktive' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5">
                            <a href="/E-Shkolla/parent-dashboard?student_id=<?= $child['student_id'] ?>" 
                               class="flex items-center justify-center gap-2 w-full py-2.5 bg-slate-50 border border-slate-100 text-slate-600 rounded-xl font-bold text-xs hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all">
                                Shiko Panelin 
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-12 text-center bg-white rounded-3xl border border-dashed border-slate-200">
                <p class="text-slate-400 text-sm italic">Nuk u gjet asnjë fëmijë i lidhur me llogarinë tuaj.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>