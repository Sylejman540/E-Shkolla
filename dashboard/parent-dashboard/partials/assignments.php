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

    <div class="grid grid-cols-1 gap-4">
        <?php if (!empty($assignments)): ?>
            <?php foreach ($assignments as $task): 
                $isLate = $task['status'] === 'late';
                $dueDate = strtotime($task['due_date']);
            ?>
                <div class="group bg-white p-6 rounded-[24px] border border-slate-100 shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 shrink-0 rounded-2xl flex items-center justify-center text-xl <?= $isLate ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-emerald-500' ?>">
                            <?= $isLate ? '⚠️' : '📖' ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-lg leading-tight"><?= htmlspecialchars($task['title']) ?></h4>
                            <p class="text-slate-500 text-sm mt-1 line-clamp-1 italic"><?= htmlspecialchars($task['description']) ?></p>
                            
                            <div class="flex items-center gap-3 mt-3">
                                <span class="flex items-center gap-1 text-xs font-bold text-slate-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Afati: <?= date('d M, Y', $dueDate) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between md:justify-end gap-4 border-t md:border-t-0 pt-4 md:pt-0">
                        <?php if ($isLate): ?>
                            <span class="px-4 py-1.5 bg-red-50 text-red-600 text-[10px] font-black uppercase rounded-full border border-red-100">
                                Afati ka kaluar
                            </span>
                        <?php else: ?>
                            <span class="px-4 py-1.5 bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase rounded-full border border-emerald-100">
                                Aktive
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-white rounded-[32px] p-20 border border-slate-100 shadow-sm text-center">
                <div class="text-5xl mb-4">🎉</div>
                <h3 class="text-xl font-bold text-slate-800">Nuk ka detyra!</h3>
                <p class="text-slate-500">Për momentin nuk ka asnjë detyrë të regjistruar për këtë klasë.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';