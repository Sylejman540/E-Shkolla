<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

// Marrja e ID-ve nga sesioni dhe URL
$classId   = (int) ($_GET['class_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);
$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$classId || !$teacherId || !$schoolId) {
        die('Kontekst i pavlefshëm. Sigurohuni që jeni brenda një klase.');
    }

    $title       = filter_var(trim($_POST['title'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS);
    $description = filter_var(trim($_POST['description'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS);
    $due_date    = $_POST['due_date'] ?? null;
    $status      = $_POST['status_type'] ?? 'active';

    $completedAt = ($status === 'done') ? date('Y-m-d H:i:s') : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO assignments (class_id, teacher_id, school_id, title, description, due_date, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$classId, $teacherId, $schoolId, $title, $description, $due_date, $completedAt]);

        // Ridrejtimi në faqen e detyrave të klasës
        header("Location: " . $_SERVER['HTTP_REFERER']); 
        exit;
    } catch (PDOException $e) {
        $error = "Gabim gjatë ruajtjes: " . $e->getMessage();
    }
}
?>

<div id="addParentForm" class="<?= $shouldOpen ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto pt-10">
    <div class="w-full max-w-2xl px-4 pb-10">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl p-8 ring-1 ring-gray-200 dark:ring-white/10">
            
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Shto Prind të Ri</h2>
                    <p class="text-sm text-gray-500">Lidhni një prind me nxënësin.</p>
                </div>
                <button type="button" onclick="window.location.href='/E-Shkolla/parents'" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="p-4 mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/parent/form.php" method="POST" class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Emri dhe Mbiemri</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($old['name'] ?? '') ?>" required
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Fjalëkalimi</label>
                    <input type="password" name="password" placeholder="Min. 8 karaktere" required
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Numri i telefonit</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:border-white/10 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Lidhja (Relacioni)</label>
                    <select name="relation" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="mother" <?= ($old['relation'] ?? '') === 'mother' ? 'selected' : '' ?>>Nëna</option>
                        <option value="father" <?= ($old['relation'] ?? '') === 'father' ? 'selected' : '' ?>>Babai</option>
                        <option value="guardian" <?= ($old['relation'] ?? '') === 'guardian' ? 'selected' : '' ?>>Kujdestar</option>
                        <option value="other" <?= ($old['relation'] ?? '') === 'other' ? 'selected' : '' ?>>Tjetër</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium dark:text-gray-300">Statusi</label>
                    <select name="status" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="active" <?= ($old['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktive</option>
                        <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Joaktive</option>
                    </select>
                </div>

                <div class="sm:col-span-6 flex justify-end gap-x-4 mt-4">
                    <button type="button" onclick="window.location.href='/E-Shkolla/parents'" class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400">Anulo</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 transition">Ruaj Prindin</button>
                </div>
            </form>
        </div>
    </div>
</div>