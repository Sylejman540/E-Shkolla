<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

/* =========================
   AUTH
========================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = (int)$_SESSION['user']['school_id'];
$userId   = (int)$_SESSION['user']['id'];

/* =========================
   HANDLE INSERT
/* =========================
   HANDLE INSERT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $day      = $_POST['day'] ?? '';
    $period   = (int)($_POST['period_number'] ?? 0);
    $subject  = (int)($_POST['subject_id'] ?? 0);
    $teacher  = (int)($_POST['teacher_id'] ?? 0);

    if (!$classId || !$day || !$period || !$subject || !$teacher) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Ju lutem plotësoni të gjitha fushat.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // Check if slot is taken (Same class, same day, same period)
    $checkSlot = $pdo->prepare("SELECT id FROM class_schedule WHERE school_id=? AND class_id=? AND day=? AND period_number=? LIMIT 1");
    $checkSlot->execute([$schoolId, $classId, $day, $period]);

    // NEW: Check if teacher is busy elsewhere (Same teacher, same day, same period)
    $checkTeacher = $pdo->prepare("SELECT id FROM class_schedule WHERE school_id=? AND teacher_id=? AND day=? AND period_number=? LIMIT 1");
    $checkTeacher->execute([$schoolId, $teacher, $day, $period]);

    if ($checkSlot->fetch()) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Ky orar (klasa, dita, ora) është i zënë.'];
    } elseif ($checkTeacher->fetch()) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Mësimdhënësi është i zënë në një klasë tjetër gjatë kësaj ore.'];
    } else {
        /* INSERT */
        $stmt = $pdo->prepare("INSERT INTO class_schedule (school_id, user_id, class_id, day, period_number, subject_id, teacher_id) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$schoolId, $userId, $classId, $day, $period, $subject, $teacher]);
        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Orari u ruajt me sukses!'];
    }

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

/* =========================
   FETCH DATA FOR FORM
========================= */
$days = [
    'monday'    => 'E Hënë',
    'tuesday'   => 'E Martë',
    'wednesday' => 'E Mërkurë',
    'thursday'  => 'E Enjte',
    'friday'    => 'E Premte'
];

$periods = [
    1 => 'Ora 1',
    2 => 'Ora 2',
    3 => 'Ora 3',
    4 => 'Ora 4',
    5 => 'Ora 5',
    6 => 'Ora 6',
    7 => 'Ora 7'
];

$stmtClasses = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id=?");
$stmtClasses->execute([$schoolId]);

$stmtSubjects = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id=?");
$stmtSubjects->execute([$schoolId]);

$stmtTeachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id=?");
$stmtTeachers->execute([$schoolId]);
?>

<!-- =========================
     ADD SCHEDULE MODAL
========================= -->
<div id="addScheduleForm" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl bg-white rounded-2xl shadow-xl border">

        <div class="flex justify-between items-center p-5 border-b">
            <h2 class="text-lg font-bold">Shto Orar të Ri</h2>
            <button onclick="document.getElementById('addScheduleForm').remove()" class="text-xl">&times;</button>
        </div>

        <form method="POST" class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="sm:col-span-2">
                <label class="text-sm font-bold">Klasa</label>
                <select name="class_id" required class="w-full p-3 border rounded-lg">
                    <?php foreach ($stmtClasses as $c): ?>
                        <option value="<?= $c['id'] ?>">Klasa <?= htmlspecialchars($c['grade']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold">Dita</label>
                <select name="day" required class="w-full p-3 border rounded-lg">
                    <?php foreach ($days as $k=>$v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold">Ora</label>
                <select name="period_number" required class="w-full p-3 border rounded-lg">
                    <?php foreach ($periods as $k=>$v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold">Lënda</label>
                <select name="subject_id" required class="w-full p-3 border rounded-lg">
                    <?php foreach ($stmtSubjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold">Mësimdhënësi</label>
                <select name="teacher_id" required class="w-full p-3 border rounded-lg">
                    <?php foreach ($stmtTeachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-2 flex justify-end gap-3 pt-4 border-t">
                <button type="button"
                        onclick="document.getElementById('addScheduleForm').remove()"
                        class="px-5 py-2 rounded-lg border font-bold">
                    Anulo
                </button>
                <button type="submit"
                        class="px-6 py-2 rounded-lg bg-indigo-600 text-white font-bold">
                    Ruaj Orarin
                </button>
            </div>
        </form>
    </div>
</div>
