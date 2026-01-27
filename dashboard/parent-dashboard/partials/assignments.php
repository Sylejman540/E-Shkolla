<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

    // Marrim studentin dhe klasën (JOIN me classes për të shmangur gabimin e kaluar)
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, c.grade, c.id AS class_id
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        JOIN classes c ON s.class_id = c.id
        WHERE ps.parent_id = ? AND s.school_id = ?
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$children) die('Nuk ka fëmijë të lidhur');

    $studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

    $currentStudent = null;
    foreach ($children as $c) {
        if ((int)$c['student_id'] === $studentId) {
            $currentStudent = $c;
            break;
        }
    }
    
    if (!$currentStudent) die('Akses i paautorizuar');
    $classId = (int) $currentStudent['class_id'];

    /* =========================
       4. FETCH ASSIGNMENTS (KORRIGJIM)
       Heqim JOIN me subjects nëse subject_id nuk ekziston
    ========================= */
    $assignments = [];
    if ($classId) {
        // Provoni këtë Query pa JOIN me subjects për të parë nëse punon
        $stmt = $pdo->prepare("
            SELECT title, description, due_date
            FROM assignments
            WHERE class_id = ? AND school_id = ?
            ORDER BY due_date DESC
        ");
        $stmt->execute([$classId, $schoolId]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("<div class='p-6 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-12 px-4 text-sm font-normal text-slate-600">
    
    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-indigo-50 rounded-full opacity-40"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Detyrat e Shtëpisë 📚</h2>
                <p class="text-slate-400 text-xs mt-0.5">
                    Klasa: <span class="font-bold text-slate-600"><?= htmlspecialchars($currentStudent['grade']) ?></span> | 
                    Nxënësi: <span class="font-bold text-indigo-500"><?= htmlspecialchars($currentStudent['name']) ?></span>
                </p>
            </div>
            
            <?php if (count($children) > 1): ?>
            <div class="flex gap-1.5 p-1 bg-slate-50 rounded-xl border border-slate-100">
                <?php foreach ($children as $child): ?>
                    <a href="?student_id=<?= $child['student_id'] ?>" 
                       class="px-4 py-1.5 rounded-lg text-[10px] font-bold transition-all <?= (int)$child['student_id'] === $studentId ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-400 hover:text-slate-600' ?>">
                        <?= htmlspecialchars($child['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <?php if (!empty($assignments)): ?>
            <?php foreach ($assignments as $task): 
                $dueDate = strtotime($task['due_date']);
                $isLate = $dueDate < strtotime(date('Y-m-d'));
            ?>
                <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm flex flex-col justify-between group">
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="px-2 py-1 bg-slate-50 text-slate-500 text-[9px] font-bold uppercase rounded-lg border border-slate-100">
                                Detyrë
                            </span>
                            <div class="h-1.5 w-1.5 rounded-full <?= $isLate ? 'bg-rose-500' : 'bg-emerald-500' ?>"></div>
                        </div>

                        <h4 class="font-bold text-slate-800 text-sm leading-snug"><?= htmlspecialchars($task['title']) ?></h4>
                        <p class="text-slate-400 text-[11px] mt-2 line-clamp-3 leading-relaxed italic">
                            <?= htmlspecialchars($task['description']) ?>
                        </p>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-50 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 italic">Mbaron: <?= date('d M, Y', $dueDate) ?></span>
                        <span class="text-[9px] font-bold uppercase <?= $isLate ? 'text-rose-500' : 'text-emerald-500' ?>">
                            <?= $isLate ? 'E kaluar' : 'Aktive' ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-16 bg-white rounded-[32px] border border-dashed border-slate-200 text-center">
                <p class="text-slate-400 text-xs italic">Nuk u gjet asnjë detyrë në sistem.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>