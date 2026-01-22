<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../../../db.php';

/* ===============================
    1. SESSION + PARAMS
================================ */
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['teacher_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

$lessonDate = $_GET['lesson_date'] ?? date('Y-m-d');
$lessonTime = $_GET['lesson_start_time'] ?? date('H:i:00');
$view = $_GET['view'] ?? 'live'; 

$page    = (int)($_GET['page'] ?? 1);
$perPage = 25;
$offset  = ($page - 1) * $perPage;

if (!$schoolId || !$teacherId || !$classId || !$subjectId) die('Missing parameters');

/* ===============================
    2. ACTIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_present') {
        $stmt = $pdo->prepare("SELECT student_id FROM student_class WHERE class_id = ?");
        $stmt->execute([$classId]);
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($studentIds as $sId) {
            $stmt = $pdo->prepare("INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())
                ON DUPLICATE KEY UPDATE present = 1, missing = 0, teacher_id = VALUES(teacher_id), updated_at = NOW()");
            $stmt->execute([$schoolId, $sId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime]);
        }
        echo json_encode(['status' => 'success']); exit;
    }

    $studentId = (int) ($_POST['student_id'] ?? 0);
    if ($action === 'save') {
        $status = $_POST['status'];
        $present = ($status === 'present') ? 1 : 0;
        $missing = ($status === 'missing') ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                               ON DUPLICATE KEY UPDATE present = VALUES(present), missing = VALUES(missing), updated_at = NOW()");
        $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime, $present, $missing]);
        echo json_encode(['status' => 'success']); exit;
    }

    if ($action === 'reset') {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ? AND class_id = ? AND subject_id = ? AND lesson_date = ? AND lesson_start_time = ?");
        $stmt->execute([$studentId, $classId, $subjectId, $lessonDate, $lessonTime]);
        echo json_encode(['status' => 'success']); exit;
    }
}

/* ===============================
    3. DATA FETCH
================================ */
if ($view === 'history') {
    $stmt = $pdo->prepare("SELECT a.*, s.name FROM attendance a JOIN students s ON s.student_id = a.student_id 
                           WHERE a.class_id = ? AND a.subject_id = ? 
                           ORDER BY a.lesson_date DESC, a.lesson_start_time DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute([$classId, $subjectId]);
} else {
    $stmt = $pdo->prepare("SELECT s.student_id, s.name, a.present, a.missing, a.updated_at FROM student_class sc
                           JOIN students s ON s.student_id = sc.student_id
                           LEFT JOIN attendance a ON a.student_id = s.student_id AND a.class_id = ? AND a.subject_id = ? AND a.lesson_date = ? AND a.lesson_start_time = ?
                           WHERE sc.class_id = ? ORDER BY s.name ASC");
    $stmt->execute([$classId, $subjectId, $lessonDate, $lessonTime, $classId]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $view === 'history' ? 'Historia e Mungesave' : 'Regjistrimi i Prezencës' ?></h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 font-medium italic"><?= htmlspecialchars($lessonDate) ?> • <?= date('H:i', strtotime($lessonTime)) ?></p>
        </div>
        
        <div class="mt-4 sm:mt-0 flex items-center gap-4">
            <?php if ($view === 'live'): ?>
                <button onclick="markAllPresentBulk()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 text-sm font-bold rounded-xl border border-blue-200 hover:bg-blue-600 hover:text-white transition-all active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Marko të gjithë Prezent
                </button>
            <?php endif; ?>

            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                <a href="?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>&view=live" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $view === 'live' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">Live</a>
                <a href="?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>&view=history" class="px-4 py-2 text-sm font-semibold rounded-lg transition-all <?= $view === 'history' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">Historia</a>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                <tr>
                    <?php if ($view === 'history'): ?>
                        <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500">Data & Ora</th>
                    <?php endif; ?>
                    <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500">Nxënësi</th>
                    <?php if ($view === 'live'): ?>
                        <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500 text-center">Veprimet</th>
                    <?php endif; ?>
                    <th class="px-6 py-4 text-xs font-bold uppercase text-slate-500 text-right pr-10">Statusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                <?php foreach ($rows as $r): 
                    $isLocked = false;
                    if ($view === 'live' && !empty($r['updated_at'])) {
                        $isLocked = (time() - strtotime($r['updated_at'])) < (45 * 60);
                    }
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                    <?php if ($view === 'history'): ?>
                        <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                            <span class="font-medium text-slate-900 dark:text-white"><?= date('d/m/Y', strtotime($r['lesson_date'])) ?></span>
                            <span class="block text-xs opacity-60"><?= substr($r['lesson_start_time'],0,5) ?></span>
                        </td>
                    <?php endif; ?>

                    <td class="px-6 py-4 text-sm font-semibold text-slate-900 dark:text-white uppercase tracking-tight">
                        <div class="flex items-center gap-2">
                            <?= htmlspecialchars($r['name']) ?>
                            <?php if($isLocked): ?> <span class="text-amber-500 text-xs">🔒</span> <?php endif; ?>
                        </div>
                    </td>

                    <?php if ($view === 'live'): ?>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="save(<?= $r['student_id'] ?>,'present')" <?= $isLocked ? 'disabled' : '' ?> class="w-8 h-8 flex items-center justify-center bg-blue-600 text-white text-[11px] font-bold rounded-lg hover:bg-blue-700 transition active:scale-95 disabled:opacity-20 shadow-sm">P</button>
                                <button onclick="save(<?= $r['student_id'] ?>,'missing')" <?= $isLocked ? 'disabled' : '' ?> class="w-8 h-8 flex items-center justify-center bg-rose-600 text-white text-[11px] font-bold rounded-lg hover:bg-rose-700 transition active:scale-95 disabled:opacity-20 shadow-sm">M</button>
                                <button onclick="resetA(<?= $r['student_id'] ?>)" class="w-8 h-8 flex items-center justify-center bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-[11px] font-bold rounded-lg hover:bg-rose-50 dark:hover:bg-rose-500/20 hover:text-rose-600 transition">R</button>
                            </div>
                        </td>
                    <?php endif; ?>

                    <td class="px-6 py-4 text-right pr-10">
                        <?php if ($r['present']): ?>
                            <span class="text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Prezent</span>
                        <?php elseif ($r['missing']): ?>
                            <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-widest">Mungon</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold text-slate-300 dark:text-slate-700 italic">Pa regjistruar</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function save(id, status) {
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=save&student_id=${id}&status=${status}`
    }).then(() => location.reload());
}
function resetA(id) {
    if(!confirm("Restart do të fshijë rekordit dhe do të zhbllokojë regjistrimin. Vazhdo?")) return;
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=reset&student_id=${id}`
    }).then(() => location.reload());
}
function markAllPresentBulk() {
    if(!confirm("Shëno të gjithë si prezent?")) return;
    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=mark_all_present`
    }).then(() => location.reload());
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>