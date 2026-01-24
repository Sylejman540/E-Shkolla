<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

/*
|--------------------------------------------------------------------------
| HANDLE POST (CREATE OR LINK PARENT)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId  = $_SESSION['user']['school_id'] ?? null;
    $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;

    try {
        if (!$schoolId)  throw new Exception('Sesioni ka skaduar.');
        if (!$studentId) throw new Exception('ID e nxënësit mungon.');

        $name     = trim($_POST['name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $relation = $_POST['relation'] ?? 'other';
        $status   = $_POST['status'] ?? 'active';

        if (!$name || !$email) throw new Exception('Emri dhe email janë të detyrueshme.');

        /* |--------------------------------------------------------------------------
        | SERVER-SIDE VALIDATION LOGIC
        |--------------------------------------------------------------------------
        */

        // 1. Basic Empty Checks
        if (empty($name)) throw new Exception('Emri dhe mbiemri duhet të plotësohen.');
        if (empty($email)) throw new Exception('Email-i duhet të plotësohet.');

        // 2. Email Format Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format i pasaktë i email-it.');
        }

        // 3. Phone Number Validation (Regex for common formats)
        if (!empty($phone) && !preg_match('/^[0-9+ ]{8,15}$/', $phone)) {
            throw new Exception('Numri i telefonit duhet të jetë vetëm me numra (8-15 shifra).');
        }

        // 4. Password Strength (Only for new users)
        // Check if user doesn't exist yet
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if (!$stmtCheck->fetch()) {
            if (strlen($password) < 8) {
                throw new Exception('Fjalëkalimi duhet të jetë së paku 8 karaktere.');
            }
            if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                throw new Exception('Fjalëkalimi duhet të përmbajë të paktën një shkronjë të madhe dhe një numër.');
            }
        }

        // 5. Relation Validation
        $allowedRelations = ['father', 'mother', 'guardian', 'other'];
        if (!in_array($relation, $allowedRelations)) {
            $relation = 'other'; // Fallback
        }

        $pdo->beginTransaction();

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            $userId = $existingUser['id'];
            $stmtP = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
            $stmtP->execute([$userId, $schoolId]);
            $parentProfile = $stmtP->fetch();

            if (!$parentProfile) {
                $stmtParent = $pdo->prepare("INSERT INTO parents (school_id, user_id, name, phone, email, relation, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtParent->execute([$schoolId, $userId, $name, $phone, $email, $relation, $status]);
                $parentId = $pdo->lastInsertId();
            } else {
                $parentId = $parentProfile['id'];
            }

            $stmtLink = $pdo->prepare("INSERT IGNORE INTO parent_student (school_id, parent_id, student_id) VALUES (?, ?, ?)");
            $stmtLink->execute([$schoolId, $parentId, $studentId]);
        } else {
            if (empty($password)) throw new Exception('Fjalëkalimi është i detyrueshëm për llogari të reja.');
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'parent', ?)");
            $stmtUser->execute([$schoolId, $name, $email, $hashedPassword, $status]);
            $userId = $pdo->lastInsertId();

            $stmtParent = $pdo->prepare("INSERT INTO parents (school_id, user_id, name, phone, email, relation, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtParent->execute([$schoolId, $userId, $name, $phone, $email, $relation, $status]);
            $parentId = $pdo->lastInsertId();

            $stmtLink = $pdo->prepare("INSERT INTO parent_student (school_id, parent_id, student_id) VALUES (?, ?, ?)");
            $stmtLink->execute([$schoolId, $parentId, $studentId]);
        }

        $pdo->commit();
        unset($_SESSION['old_parent']); // Clear old data on success
        $_SESSION['success'] = "Veprimi u krye me sukses.";
        header("Location: /E-Shkolla/parents");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: /E-Shkolla/parents?open_form=1&student_id=$studentId");
        exit;
    }
}

$shouldOpen = isset($_GET['open_form']) || isset($_SESSION['error']);
$studentId = $_GET['student_id'] ?? null;
?>

<div id="addParentForm" class="<?= $shouldOpen ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto pt-10">
    <div class="w-full max-w-2xl px-4 pb-10">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl p-8 ring-1 ring-gray-200 dark:ring-white/10">
            
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Shto Prind të Ri</h2>
                    <p class="text-sm text-gray-500">Lidhni një prind me nxënësin.</p>
                </div>
                <button type="button" onclick="closeAddParentForm()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
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
                    <button type="button" onclick="closeAddParentForm()" class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400">Anulo</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 transition">Ruaj Prindin</button>
                </div>
            </form>
        </div>
    </div>
</div>