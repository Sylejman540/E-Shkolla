<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
$userId = $_SESSION['user']['id'] ?? null;

$currentMonth = date('n');
$currentYear = date('Y');
$autoYear = ($currentMonth >= 9) ? $currentYear . '/' . ($currentYear + 1) : ($currentYear - 1) . '/' . $currentYear;

$teachers = [];
if ($schoolId) {
    $tStmt = $pdo->prepare("SELECT user_id, name FROM teachers WHERE school_id = ? AND status = 'active' ORDER BY name ASC");
    $tStmt->execute([$schoolId]);
    $teachers = $tStmt->fetchAll(PDO::FETCH_ASSOC);
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $academic_year = $_POST['academic_year'];
        $base_grade    = $_POST['base_grade'];
        $parallel      = trim($_POST['parallel']);
        $max_students  = (int) $_POST['max_students'];
        $status        = $_POST['status'];
        $classHeader   = !empty($_POST['class_header']) ? (int) $_POST['class_header'] : null;

        $full_grade = $base_grade . ($parallel !== '' ? '/' . $parallel : '');

        if ($classHeader) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND class_header = ?");
            $checkStmt->execute([$schoolId, $classHeader]);

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception(
                    'Ky mësues tashmë është kujdestar i një klase tjetër.'
                );
            }
        }

        $stmt = $pdo->prepare("INSERT INTO classes(school_id, user_id, class_header, academic_year, grade, max_students, status)VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$schoolId, $userId, $classHeader, $academic_year, $full_grade, $max_students, $status]);

        $_SESSION['success'] = "Klasa $full_grade u shtua me sukses!";
        header("Location: /E-Shkolla/classes");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = "Gabim: " . $e->getMessage();
        header("Location: /E-Shkolla/classes?open_form=1");
        exit;
    }
}
?>

<div id="addClassForm" class="<?= $shouldOpen ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto pt-10">
    <div class="w-full max-w-2xl px-4 pb-10">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl p-8 ring-1 ring-gray-200 dark:ring-white/10">
            
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Shto Klasë të Re</h2>
                <button type="button" onclick="document.getElementById('addClassForm').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-100 border border-red-200 text-red-700 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-3 rounded-lg bg-green-100 border border-green-200 text-green-700 text-sm">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/form.php" method="POST" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Viti Akademik</label>
                    <input type="text" name="academic_year" value="<?= $autoYear ?>" readonly
                           class="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-gray-400 cursor-not-allowed outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Klasa & Paralelja</label>
                    <div class="flex gap-2 mt-1">
                        <select name="base_grade" required class="block w-24 rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="text-2xl text-gray-400">/</span>
                        <input type="text" name="parallel" placeholder="p.sh. 1 ose A" required
                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">
                        Mësuesi Kujdestar
                    </label>

                    <select name="class_header" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">Pa kujdestar</option>

                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['user_id'] ?>">
                                <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Kapaciteti Max (Nxënës)</label>
                    <input type="number" name="max_students" value="30" min="1"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Statusi i Klasës</label>
                    <select name="status" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="active">Aktive</option>
                        <option value="inactive">Joaktive</option>
                    </select>
                </div>

                <div class="sm:col-span-6 flex justify-end gap-x-4 mt-4">
                    <button type="button" onclick="document.getElementById('addClassForm').classList.add('hidden')" class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-800 transition">Anulo</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 transition focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                        Ruaj Klasën
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>