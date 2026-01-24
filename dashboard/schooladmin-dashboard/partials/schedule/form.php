<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = (int)$_POST['school_id'];
    $class_id  = (int)$_POST['class_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $day = $_POST['day'];
    $period = (int)$_POST['period_number'];

    // If subject_id is 0 or empty, stop the execution
    if ($subject_id <= 0) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Gabim: Lënda nuk është zgjedhur!'];
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    try {
        $sql = "INSERT INTO schedule (school_id, class_id, teacher_id, subject_id, day, period_number) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$school_id, $class_id, $teacher_id, $subject_id, $day, $period]);

        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Orari u ruajt me sukses!'];
    } catch (PDOException $e) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Gabim në bazën e të dhënave: ' . $e->getMessage()];
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
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
