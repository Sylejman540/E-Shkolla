<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
$user_id = $_SESSION['user']['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $subjectId = $_POST['subject_id'];
    $teacherId = $_POST['teacher_id'];    
    $classId = $_POST['class_id'];

    $stmt = $pdo->prepare("INSERT INTO class_schedule (school_id, user_id, class_id, day, start_time, end_time, subject_id, teacher_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $user_id, $classId, $day, $start_time, $end_time, $subjectId, $teacherId, $status]);

    header("Location: /E-Shkolla/schedule");
    exit;
}
?>

<div id="addScheduleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm overflow-y-auto p-4">
    
    <div class="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-xl shadow-2xl ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
        
        <div class="p-6 border-b border-gray-100 dark:border-white/5">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Shto orar të ri</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plotësoni të dhënat për të caktuar orarin e klasës.</p>
        </div>

        <form action="" method="post" class="p-6">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dita</label>
                    <select name="day" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                        <option value="monday">E hënë</option>
                        <option value="tuesday">E martë</option>
                        <option value="wednesday">E mërkurë</option>
                        <option value="thursday">E enjte</option>
                        <option value="friday">E premte</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ora e fillimit</label>
                    <input type="time" name="start_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ora e përfundimit</label>
                    <input type="time" name="end_time" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Klasa</label>
                    <select name="class_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                        <?php
                        $stmt_classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                        $stmt_classes->execute([$schoolId]);
                        foreach ($stmt_classes as $class):
                        ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lënda</label>
                    <select name="subject_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                        <?php
                        $stmt_subjects = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id = ?");
                        $stmt_subjects->execute([$schoolId]);
                        foreach ($stmt_subjects as $subject):
                        ?>
                            <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mësuesi</label>
                    <select name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                        <?php
                        $stmt_teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id = ?");
                        $stmt_teachers->execute([$schoolId]);
                        foreach ($stmt_teachers as $teacher):
                        ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Statusi</label>
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-white p-2 border">
                        <option value="active">Aktive</option>
                        <option value="inactive">Joaktive</option>
                    </select>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-x-4 border-t border-gray-100 dark:border-white/5 pt-6">
                <button type="button" id="closeModal" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Anulo</button>
                <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition">Ruaj Orarin</button>
            </div>
        </form>
    </div>
</div>
