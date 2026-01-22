<?php
declare(strict_types=1);

/* =====================================================
   1. BACKEND LOGIC
===================================================== */
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
            (SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = s.student_id) as avg_grade
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE ps.parent_id = ? AND s.school_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("<div class='p-10 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-12 pb-20 px-6">
    
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Fëmijët e Mi</h1>
        <p class="text-slate-500 font-normal">Menaxhoni progresin akademik të fëmijëve tuaj</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (!empty($children)): ?>
            <?php foreach ($children as $child): 
                $initials = strtoupper(substr($child['student_name'], 0, 1));
            ?>
                <div class="group bg-white rounded-[24px] border border-slate-200/60 shadow-sm hover:shadow-md transition-all duration-200">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-16 w-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl transition-colors group-hover:bg-indigo-600 group-hover:text-white">
                                <?= $initials ?>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">
                                    <?= htmlspecialchars($child['student_name']) ?>
                                </h3>
                                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">
                                    Klasa: <?= htmlspecialchars($child['class_name']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-4 border-y border-slate-50">
                            <div>
                                <p class="text-[11px] font-medium text-slate-400 uppercase italic">Mesatarja</p>
                                <p class="text-lg font-bold text-slate-700"><?= $child['avg_grade'] ?: '—' ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] font-medium text-slate-400 uppercase italic">Gjendja</p>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full <?= $child['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                                    <span class="text-sm font-semibold text-slate-600">
                                        <?= $child['status'] === 'active' ? 'Aktive' : 'Jo Aktive' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a href="/E-Shkolla/parent-dashboard?student_id=<?= $child['student_id'] ?>" 
                               class="flex items-center justify-center gap-2 w-full py-3 bg-slate-50 text-slate-700 rounded-xl font-semibold text-sm hover:bg-indigo-600 hover:text-white transition-all">
                                Shiko Panelin 
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>