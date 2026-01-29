<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../../../db.php';
require_once __DIR__ . '/../../../../../helpers/ParentEmails.php';
require_once __DIR__ . '/../../../../../helpers/Mailer.php';

/* ===============================
   SESSION + PARAMS
================================ */
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['teacher_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$view      = $_GET['view'] ?? 'live';

if (!$schoolId || !$teacherId || !$classId || !$subjectId) {
    http_response_code(400);
    exit('Missing parameters');
}

$lessonDate = $_GET['lesson_date'] ?? date('Y-m-d');
$lessonTime = $_GET['lesson_start_time'] ?? date('H:i:00');

/* ===============================
   POST ACTIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $studentId = (int) ($_POST['student_id'] ?? 0);

    // 1. MARK ALL PRESENT (BULK)
    if ($action === 'mark_all_present') {
        // We only insert/update students who:
        // A) Have no record OR B) Were updated more than 30 mins ago
        $stmt = $pdo->prepare("
            INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at)
            SELECT ?, s.student_id, ?, ?, ?, ?, ?, 1, 0, NOW()
            FROM student_class sc
            JOIN students s ON s.student_id = sc.student_id
            LEFT JOIN attendance a ON a.student_id = s.student_id 
                AND a.lesson_date = ? 
                AND a.lesson_start_time = ?
                AND a.subject_id = ?
            WHERE sc.class_id = ?
              AND (a.updated_at IS NULL OR a.updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
            ON DUPLICATE KEY UPDATE 
                present = 1, 
                missing = 0, 
                updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime, $lessonDate, $lessonTime, $subjectId, $classId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 2. SAVE INDIVIDUAL (PRESENT/MISSING)
    if ($action === 'save' && $studentId) {
        $status  = $_POST['status'] ?? '';
        $present = ($status === 'present') ? 1 : 0;
        $missing = ($status === 'missing') ? 1 : 0;

        // Verify lock before saving manually
        $check = $pdo->prepare("SELECT updated_at FROM attendance WHERE student_id = ? AND lesson_date = ? AND lesson_start_time = ? AND subject_id = ?");
        $check->execute([$studentId, $lessonDate, $lessonTime, $subjectId]);
        $lastUpdate = $check->fetchColumn();

        if ($lastUpdate && (time() - strtotime($lastUpdate)) < (30 * 60)) {
            echo json_encode(['status' => 'locked']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE present = VALUES(present), missing = VALUES(missing), updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime, $present, $missing]);

        if ($missing === 1) {
            // Optional: Email logic here
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 3. RESET (UNLOCK & DELETE)
    if ($action === 'reset' && $studentId) {
        $pdo->prepare("
            DELETE FROM attendance 
            WHERE student_id = ? AND class_id = ? AND subject_id = ? AND lesson_date = ? AND lesson_start_time = ?
        ")->execute([$studentId, $classId, $subjectId, $lessonDate, $lessonTime]);
        echo json_encode(['status' => 'reset']);
        exit;
    }
}

/* ===============================
   FETCH DATA
================================ */
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, a.present, a.missing, a.updated_at
    FROM student_class sc
    JOIN students s ON s.student_id = sc.student_id
    LEFT JOIN attendance a ON a.student_id = s.student_id 
        AND a.class_id = ? AND a.subject_id = ? AND a.lesson_date = ? AND a.lesson_start_time = ?
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$classId, $subjectId, $lessonDate, $lessonTime, $classId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="attendance-view px-4 py-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-end mb-6 pb-5 border-b border-slate-100">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Regjistrimi i Prezencës</h1>
            <p class="text-xs text-slate-500"><?= $lessonDate ?> | <?= substr($lessonTime, 0, 5) ?></p>
        </div>
        <button onclick="markAllPresentBulk()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:bg-indigo-700 transition-all">
            Marko të gjithë Prezent (jo të bllokuar)
        </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase">Nxënësi</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase text-center">Veprimet</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-slate-400 uppercase text-right">Statusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($rows as $r): 
                    $isLocked = false;
                    if (!empty($r['updated_at'])) {
                        // LOCK FOR 30 MINUTES
                        $isLocked = (time() - strtotime($r['updated_at'])) < (30 * 60);
                    }
                ?>
                <tr class="<?= $isLocked ? 'bg-slate-50/30' : '' ?>">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium <?= $isLocked ? 'text-slate-400' : 'text-slate-700' ?>">
                                <?= htmlspecialchars($r['name']) ?>
                            </span>
                            <?php if($isLocked): ?>
                                <span title="E bllokuar për 30min" class="text-amber-500">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="save(<?= $r['student_id'] ?>,'present')" <?= $isLocked ? 'disabled' : '' ?> class="p-2 bg-emerald-50 text-emerald-600 rounded-md hover:bg-emerald-600 hover:text-white disabled:opacity-30 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                            </button>
                            <button onclick="save(<?= $r['student_id'] ?>,'missing')" <?= $isLocked ? 'disabled' : '' ?> class="p-2 bg-rose-50 text-rose-600 rounded-md hover:bg-rose-600 hover:text-white disabled:opacity-30 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <button onclick="resetA(<?= $r['student_id'] ?>)" class="p-2 bg-slate-100 text-slate-500 rounded-md hover:bg-slate-200 hover:text-slate-700 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-right">
                        <?php if ($r['present']): ?>
                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Prezent</span>
                        <?php elseif ($r['missing']): ?>
                            <span class="px-2 py-1 bg-rose-100 text-rose-700 text-[10px] font-bold rounded uppercase">Mungon</span>
                        <?php else: ?>
                            <span class="text-[10px] text-slate-300 italic">Pa regjistruar</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function handleAction(body) {
    await fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    });
    location.reload();
}

function save(id, status) {
    handleAction(`action=save&student_id=${id}&status=${status}`);
}

function resetA(id) {
    if(!confirm("Restart do të fshijë rekordin dhe do të zhbllokojë regjistrimin. Vazhdo?")) return;
    handleAction(`action=reset&student_id=${id}`);
}

function markAllPresentBulk() {
    if(!confirm("Shëno të gjithë si prezent? (Nxënësit e bllokuar nuk do të ndryshohen)")) return;
    handleAction(`action=mark_all_present`);
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>