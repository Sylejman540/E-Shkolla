<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $classId  = (int) ($_POST['class_id'] ?? 0);
    $schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
    $userId   = (int) ($_SESSION['user']['id'] ?? 0);

    if (!$classId || !$schoolId || !$userId) {
        die('Invalid context');
    }

    /* ✅ GET REAL TEACHER ID */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM teachers 
        WHERE user_id = ? AND school_id = ?
    ");
    $stmt->execute([$userId, $schoolId]);
    $teacherId = (int) $stmt->fetchColumn();

    if (!$teacherId) {
        die('Teacher not found');
    }

    /* FORM DATA */
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate     = $_POST['due_date'] ?? null;
    $statusInput = $_POST['status'] ?? 'active';

    if ($title === '') {
        die('Title required');
    }

    $status      = $statusInput === 'completed' ? 'completed' : 'active';
    $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;

    /* INSERT */
    $stmt = $pdo->prepare("
        INSERT INTO assignments
        (class_id, teacher_id, school_id, title, description, due_date, status, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $classId,
        $teacherId,
        $schoolId,
        $title,
        $description,
        $dueDate,
        $status,
        $completedAt
    ]);

    header("Location: /E-Shkolla/class-assignments?class_id={$classId}");
    exit;
}
?>
<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
    <div class="w-full max-w-3xl px-4">
        <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Shto detyrë të ri</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plotësoni të dhënat për të krijuar detyrë.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('addSchoolForm').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
            </div>

            <form action="" method="post">
                <input type="hidden" name="class_id" value="<?= (int) ($_GET['class_id'] ?? 0) ?>">

                <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6 border-b border-gray-900/10 pb-8 dark:border-white/10">
                    
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Titulli</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($_SESSION['old']['title'] ?? '') ?>" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Data e dorëzimit</label>
                        <input type="date" name="due_date" required value="<?= htmlspecialchars($_SESSION['old']['due_date'] ?? '') ?>" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Statusi</label>
                        <select name="status" class="mt-2 border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-gray-800 dark:text-white">
                            <option value="active" <?= (isset($_SESSION['old']['status']) && $_SESSION['old']['status'] == 'active') ? 'selected' : '' ?>>Aktive</option>
                            <option value="inactive" <?= (isset($_SESSION['old']['status']) && $_SESSION['old']['status'] == 'inactive') ? 'selected' : '' ?>>Joaktive</option>
                        </select>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Përshkrimi i detyrës</label>
                        <textarea name="description" rows="2" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white"><?= htmlspecialchars($_SESSION['old']['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-x-4">
                    <button type="button" onclick="document.getElementById('addSchoolForm').classList.add('hidden')" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Anulo</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-indigo-600">Ruaj Mësuesin</button>
                </div>
            </form>

        </div>
    </div>
</div>