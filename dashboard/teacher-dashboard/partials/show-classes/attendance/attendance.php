<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../../db.php';

/* ===============================
    1. SESSION + PARAMS
================================ */
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['teacher_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$view      = $_GET['view'] ?? 'live'; 

$page    = (int)($_GET['page'] ?? 1);
$perPage = 25;
$offset  = ($page - 1) * $perPage;

if (!$schoolId || !$teacherId || !$classId || !$subjectId) die('Missing parameters');

/* =====================================================
    2. STABLE LESSON CONTEXT (INTEGRATED LOGIC)
===================================================== */
$lessonDate = $_GET['lesson_date'] ?? null;
$lessonTime = $_GET['lesson_start_time'] ?? null;

// Fallback: If parameters are lost, fetch the last recorded lesson for this context
if (!$lessonDate || !$lessonTime) {
    $stmt = $pdo->prepare("
        SELECT lesson_date, lesson_start_time 
        FROM attendance 
        WHERE class_id = ? AND subject_id = ? 
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$classId, $subjectId]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    $lessonDate = $last['lesson_date'] ?? date('Y-m-d');
    $lessonTime = $last['lesson_start_time'] ?? date('H:i:00');
}

/* ===============================
    3. ACTIONS
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
    4. DATA FETCH
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .attendance-view { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
    .status-badge { letter-spacing: 0.025em; }
</style>

<div class="attendance-view px-4 sm:px-6 lg:px-8 py-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 gap-4 border-b border-slate-100 pb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                <?= $view === 'history' ? 'Historia e Mungesave' : 'Regjistrimi i Prezencës' ?>
            </h1>
            <div class="flex items-center gap-2 mt-1 text-[12px] text-slate-500 font-medium">
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= htmlspecialchars($lessonDate) ?>
                </span>
                <span class="text-slate-300">•</span>
                <span class="flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= date('H:i', strtotime($lessonTime)) ?>
                </span>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <?php if ($view === 'live'): ?>
                <button onclick="markAllPresentBulk()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white text-[11px] font-bold rounded-lg hover:bg-indigo-700 transition-all shadow-sm active:scale-95">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Marko të gjithë Prezent
                </button>
            <?php endif; ?>

            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <a href="?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>&view=live" class="px-3 py-1 text-[11px] font-bold rounded-md transition-all <?= $view === 'live' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">LIVE</a>
                <a href="?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>&view=history" class="px-3 py-1 text-[11px] font-bold rounded-md transition-all <?= $view === 'history' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">HISTORIA</a>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                    <?php if ($view === 'history'): ?>
                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Data & Ora</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Nxënësi</th>
                    <?php if ($view === 'live'): ?>
                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-center">Veprimet</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-right pr-8">Statusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="4" class="px-5 py-12 text-center text-slate-400 italic text-sm">Nuk u gjet asnjë regjistrim.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): 
                    $isLocked = false;
                    if ($view === 'live' && !empty($r['updated_at'])) {
                        $isLocked = (time() - strtotime($r['updated_at'])) < (45 * 60);
                    }
                ?>
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                    <?php if ($view === 'history'): ?>
                        <td class="px-5 py-3">
                            <div class="text-[13px] font-semibold text-slate-700 dark:text-slate-300"><?= date('d.m.Y', strtotime($r['lesson_date'])) ?></div>
                            <div class="text-[10px] text-slate-400"><?= substr($r['lesson_start_time'], 0, 5) ?></div>
                        </td>
                    <?php endif; ?>

                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($r['name'] ?? 'I panjohur') ?></span>
                            <?php if($isLocked): ?> 
                                <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            <?php endif; ?>
                        </div>
                    </td>

                    <?php if ($view === 'live'): ?>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="save(<?= $r['student_id'] ?>,'present')" 
                                        <?= $isLocked ? 'disabled' : '' ?> 
                                        class="p-1.5 bg-emerald-50 text-emerald-600 rounded-md hover:bg-emerald-600 hover:text-white transition-all disabled:opacity-30 disabled:cursor-not-allowed" title="Prezent">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                                </button>
                                
                                <button onclick="save(<?= $r['student_id'] ?>,'missing')" 
                                        <?= $isLocked ? 'disabled' : '' ?> 
                                        class="p-1.5 bg-rose-50 text-rose-600 rounded-md hover:bg-rose-600 hover:text-white transition-all disabled:opacity-30 disabled:cursor-not-allowed" title="Mungon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                
                                <button onclick="resetA(<?= $r['student_id'] ?>)" 
                                        class="p-1.5 bg-slate-100 text-slate-500 rounded-md hover:bg-slate-200 hover:text-slate-700 transition-all" title="Rifillo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                            </div>
                        </td>
                    <?php endif; ?>

                    <td class="px-5 py-3 text-right pr-8">
                        <?php if (!empty($r['present']) && $r['present'] == 1): ?>
                            <span class="status-badge px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Prezent</span>
                        <?php elseif (!empty($r['missing']) && $r['missing'] == 1): ?>
                            <span class="status-badge px-2 py-0.5 bg-rose-100 text-rose-700 text-[10px] font-bold rounded uppercase">Mungon</span>
                        <?php else: ?>
                            <span class="text-[10px] font-medium text-slate-300 italic uppercase">Pa regjistruar</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
require_once __DIR__ . '/../index.php';
?>