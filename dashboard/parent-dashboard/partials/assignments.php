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
   OWNERSHIP CHECK & CLASS
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

// Gjejmë ID-në e klasës për të marrë detyrat
$stmt = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
$stmt->execute([$student['class_name'], $schoolId]);
$classId = (int) $stmt->fetchColumn();

/* =========================
   FETCH ASSIGNMENTS
   ========================= */
$assignments = [];
if ($classId) {
    $stmt = $pdo->prepare("
        SELECT title, description, due_date,
        CASE WHEN due_date < CURDATE() THEN 'late' ELSE 'active' END AS status
        FROM assignments
        WHERE class_id = ? AND school_id = ?
        ORDER BY due_date ASC
    ");
    $stmt->execute([$classId, $schoolId]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   FRONTEND - CONTENT
   ========================= */
ob_start();
?>

<div class="space-y-6">
    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm p-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-900 tracking-tight">Detyrat e Shtëpisë 📚</h2>
            <p class="text-slate-500 font-medium mt-1">
                Monitoroni detyrat për: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($student['student_name']) ?></span>
            </p>
        </div>
        <div class="flex items-center gap-3 bg-indigo-50 px-5 py-3 rounded-2xl border border-indigo-100">
            <span class="text-indigo-700 font-bold text-sm"><?= count($assignments) ?> Detyra në total</span>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php if (!empty($assignments)): ?>
        <?php foreach ($assignments as $task): 
            $isLate = $task['status'] === 'late';
            $dueDate = strtotime($task['due_date']);
        ?>
            <div class="bg-white rounded-[24px] border border-slate-100 p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between h-[200px]">
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="px-2 py-1 bg-slate-50 text-slate-500 text-[10px] font-black uppercase tracking-wider rounded-md border border-slate-100">
                            <?= htmlspecialchars($task['subject_name'] ?? 'DETYRË') ?>
                        </span>
                        <div class="h-2 w-2 rounded-full <?= $isLate ? 'bg-red-500' : 'bg-emerald-500' ?>"></div>
                    </div>

                    <div class="pt-1">
                        <h4 class="font-bold text-slate-900 text-sm leading-tight line-clamp-2">
                            <?= htmlspecialchars($task['title']) ?>
                        </h4>
                        <p class="text-slate-400 text-[11px] mt-1.5 line-clamp-2 leading-relaxed">
                            <?= htmlspecialchars($task['description']) ?>
                        </p>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-50 flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-400">
                        <?= date('d M, Y', $dueDate) ?>
                    </span>
                    <?php if ($isLate): ?>
                        <span class="text-[9px] font-black text-red-500 uppercase tracking-tighter">I kaluar</span>
                    <?php else: ?>
                        <span class="text-[9px] font-black text-emerald-500 uppercase tracking-tighter">Aktiv</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-full p-10 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 text-center text-slate-400 text-sm">
            Nuk ka detyra për momentin.
        </div>
    <?php endif; ?>
</div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';