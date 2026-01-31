<?php
/* ===============================
    CONFIGURATION
================================ */
define('ATTENDANCE_LOCK_MINUTES', 30); 

/* ===============================
    SESSION + SECURITY
================================ */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../../../db.php';
require_once __DIR__ . '/../../../../../helpers/ParentEmails.php';
require_once __DIR__ . '/../../../../../helpers/Mailer.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    http_response_code(403);
    exit('Unauthorized');
}

/* ===============================
    CONTEXT
================================ */
$schoolId  = (int) $_SESSION['user']['school_id'];
$teacherId = (int) $_SESSION['user']['teacher_id'];
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

if (!$schoolId || !$teacherId || !$classId || !$subjectId) {
    http_response_code(400);
    exit('Missing parameters');
}

$lessonDate = $_GET['lesson_date'] ?? date('Y-m-d');
$lessonTime = $_GET['lesson_start_time'] ?? '08:00:00';

/* ===============================
    POST ACTIONS (AJAX)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $studentId  = (int) ($_POST['student_id'] ?? 0);
    $lessonTime = $_POST['lesson_start_time'] ?? null;

    if (!$lessonTime) {
        echo json_encode(['status' => 'missing_time']);
        exit;
    }

    if ($action === 'mark_all_present') {
        $stmt = $pdo->prepare("
            INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at)
            SELECT ?, sc.student_id, ?, ?, ?, ?, ?, 1, 0, NOW()
            FROM student_class sc
            LEFT JOIN attendance a ON a.student_id = sc.student_id AND a.lesson_date = ? AND a.lesson_start_time = ? AND a.subject_id = ?
            WHERE sc.class_id = ? AND (a.updated_at IS NULL OR a.updated_at < DATE_SUB(NOW(), INTERVAL " . ATTENDANCE_LOCK_MINUTES . " MINUTE))
            ON DUPLICATE KEY UPDATE present = 1, missing = 0, updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime, $lessonDate, $lessonTime, $subjectId, $classId]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'save' && $studentId) {
        $status  = $_POST['status'] ?? '';
        $present = ($status === 'present') ? 1 : 0;
        $missing = ($status === 'missing') ? 1 : 0;

        $lockStmt = $pdo->prepare("SELECT updated_at FROM attendance WHERE student_id = ? AND class_id = ? AND subject_id = ? AND lesson_date = ? AND lesson_start_time = ? LIMIT 1");
        $lockStmt->execute([$studentId, $classId, $subjectId, $lessonDate, $lessonTime]);
        $lastUpdate = $lockStmt->fetchColumn();

        if ($lastUpdate && (time() - strtotime($lastUpdate)) < (ATTENDANCE_LOCK_MINUTES * 60)) {
            echo json_encode(['status' => 'locked']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, lesson_date, lesson_start_time, present, missing, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE present = VALUES(present), missing = VALUES(missing), updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $lessonDate, $lessonTime, $present, $missing]);

        if ($missing) {
            $nameStmt = $pdo->prepare("SELECT name FROM students WHERE student_id = ?");
            $nameStmt->execute([$studentId]);
            $studentName = $nameStmt->fetchColumn() ?: 'Nxenes';
            $emails = getParentEmailsByStudent($studentId, $pdo);
            if (!empty($emails)) {
                // The delay happens here
                sendSchoolEmail($emails, 'Njoftim mungese', "Nxenesi $studentName mungon ne oren $lessonTime ($lessonDate)");
            }
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'reset' && $studentId) {
        $pdo->prepare("DELETE FROM attendance WHERE student_id = ? AND class_id = ? AND subject_id = ? AND lesson_date = ? AND lesson_start_time = ?")
            ->execute([$studentId, $classId, $subjectId, $lessonDate, $lessonTime]);
        echo json_encode(['status' => 'reset']);
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, a.present, a.missing, a.updated_at
FROM student_class sc
JOIN students s 
    ON s.student_id = sc.student_id
   AND s.status = 'active'
LEFT JOIN attendance a 
    ON a.student_id = s.student_id 
   AND a.class_id = ? 
   AND a.subject_id = ? 
   AND a.lesson_date = ? 
   AND a.lesson_start_time = ?
WHERE sc.class_id = ?

");
$stmt->execute([$classId, $subjectId, $lessonDate, $lessonTime, $classId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = ['total' => count($rows), 'present' => 0, 'missing' => 0];
foreach($rows as $r) {
    if ($r['present']) $counts['present']++;
    if ($r['missing']) $counts['missing']++;
}

ob_start();
?>

<div class="p-6 bg-gray-50 min-h-screen font-sans text-gray-900">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold">Prezenca e Nxënësve</h1>
            <p class="text-gray-500 text-sm">Regjistrimi për: <span class="font-semibold"><?= $lessonDate ?></span> | Ora: <span class="font-semibold"><?= substr($lessonTime, 0, 5) ?></span></p>
        </div>
        <button onclick="markAllPresent()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-all shadow-sm active:scale-95">
            + Shëno të gjithë prezent
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Totali</p>
            <p class="text-2xl font-bold"><?= $counts['total'] ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Prezent</p>
            <p class="text-2xl font-bold text-indigo-600"><?= $counts['present'] ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Mungojnë</p>
            <p class="text-2xl font-bold text-red-500"><?= $counts['missing'] ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Statusi</p>
            <p class="text-sm font-semibold text-gray-600 mt-2 italic">Aktiv për modifikim</p>
        </div>
    </div>

    <div class="relative max-w-md mb-6">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </span>
        <input type="text" id="studentSearch" onkeyup="filterStudents()" placeholder="Kërko nxënësin..." 
               class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent sm:text-sm transition-all">
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="attendanceTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Nxënësi</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Statusi</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-gray-400 uppercase tracking-wider">Veprimet</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($rows as $r): 
                    $isLocked = false;
                    if (!empty($r['updated_at'])) {
                        $isLocked = (time() - strtotime($r['updated_at'])) < (ATTENDANCE_LOCK_MINUTES * 60);
                    }
                ?>
                <tr class="student-row hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap student-name font-semibold text-gray-800"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($r['missing']): ?>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-red-50 text-red-600 uppercase">Mungon</span>
                        <?php elseif ($r['present']): ?>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-indigo-50 text-indigo-600 uppercase">Prezent</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-gray-100 text-gray-400 uppercase tracking-tighter">Pa regjistruar</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end items-center gap-3">
                            <?php if ($isLocked): ?>
                                <span class="text-[10px] text-gray-300 font-bold uppercase mr-2">🔒 Kyçur</span>
                            <?php endif; ?>

                            <button onclick="save(<?= $r['student_id'] ?>,'present', this)" 
                                    <?= $isLocked ? 'disabled' : '' ?>
                                    class="bg-white border border-gray-200 text-gray-700 px-4 py-1.5 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-red-50 disabled:hover:bg-red-50">
                                Prezent
                            </button>
                            
                            <button onclick="save(<?= $r['student_id'] ?>,'missing', this)" 
                                    <?= $isLocked ? 'disabled' : '' ?>
                                    class="bg-white border border-gray-200 text-gray-700 px-4 py-1.5 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-red-50 disabled:hover:bg-red-50">
                                Mungon
                            </button>

                            <button onclick="resetA(<?= $r['student_id'] ?>)" class="text-gray-300 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const lessonTime = "<?= $lessonTime ?>";

function filterStudents() {
    const filter = document.getElementById('studentSearch').value.toLowerCase();
    document.querySelectorAll('.student-row').forEach(row => {
        const name = row.querySelector('.student-name').innerText.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
    });
}

function post(body, btn) {
    // Visual feedback for slow email sending
    if(btn) {
        btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span>...';
        btn.parentElement.querySelectorAll('button').forEach(b => b.disabled = true);
    }

    fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body
    }).then(() => location.reload());
}

function save(id, status, btn) {
    post(`action=save&student_id=${id}&status=${status}&lesson_start_time=${lessonTime}`, btn);
}

function resetA(id) {
    if(confirm('Resetoni prezencën?')) post(`action=reset&student_id=${id}&lesson_start_time=${lessonTime}`);
}

function markAllPresent() {
    if(confirm('Shëno të gjithë prezent?')) post(`action=mark_all_present&lesson_start_time=${lessonTime}`);
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';