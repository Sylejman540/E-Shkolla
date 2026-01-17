<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/** =========================
 * CSRF TOKEN
 * ========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

/** =========================
 * HANDLE POST
 * ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    $schoolId = (int)($_POST['school_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $status   = $_POST['status'] ?? '';

    // Validation
    if (!$schoolId || !$name || !$email || !$password || !$role || !$status) {
        $errors[] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    $allowedRoles  = ['super_admin', 'school_admin', 'teacher', 'parent', 'student'];
    $allowedStatus = ['active', 'inactive'];

    if (!in_array($role, $allowedRoles, true)) { $errors[] = 'Invalid role selected.'; }
    if (!in_array($status, $allowedStatus, true)) { $errors[] = 'Invalid status selected.'; }

    if (empty($errors)) {
        // Prevent duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = 'Email already exists.';
        } else {
            // Hash password and Insert
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (school_id, name, email, password, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$schoolId, $name, $email, $hashedPassword, $role, $status]);

            header("Location: /E-Shkolla/super-admin-users?success=1");
            exit;
        }
    }
}

/** =========================
 * FETCH SCHOOLS FOR DROPDOWN
 * ========================= */
$stmt = $pdo->query("SELECT id, school_name FROM schools ORDER BY school_name ASC");
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="addUserForm" class="<?= empty($errors) ? 'hidden' : '' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm overflow-y-auto p-4">
    
    <div class="w-full max-w-4xl mx-auto bg-white dark:bg-gray-900 rounded-xl shadow-2xl ring-1 ring-gray-200 dark:ring-white/10 p-8">
        
        <div class="mb-8 border-b border-gray-100 dark:border-white/5 pb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Add a new user</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new account and assign it to a specific institution.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <ul class="text-sm text-red-600 dark:text-red-400 list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 md:grid-cols-3">
            
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">User Profile</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Select the appropriate role and school for this user. The password will be encrypted immediately.</p>
            </div>

            <form action="" method="POST" class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="sm:col-span-3">
                    <label for="school_id" class="block text-sm font-medium text-gray-900 dark:text-white">Assigned School</label>
                    <div class="mt-2">
                        <select id="school_id" name="school_id" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10" required>
                            <option value="">-- Select a School --</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= (isset($schoolId) && $schoolId == $school['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                                <div class="sm:col-span-3">
                    <label for="password" class="block text-sm font-medium text-gray-900 dark:text-white">Password</label>
                    <div class="mt-2">
                        <input id="password" type="password" name="password" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10" required />
                    </div>
                </div>


                <div class="sm:col-span-3">
                    <label for="name" class="block text-sm font-medium text-gray-900 dark:text-white">Full Name</label>
                    <div class="mt-2">
                        <input id="name" type="text" name="name" value="<?= htmlspecialchars($name ?? '') ?>" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10" required />
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="email" class="block text-sm font-medium text-gray-900 dark:text-white">Email Address</label>
                    <div class="mt-2">
                        <input id="email" type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10" required />
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="role" class="block text-sm font-medium text-gray-900 dark:text-white">System Role</label>
                    <div class="mt-2">
                        <select id="role" name="role" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10">
                            <option value="student" <?= ($role == 'student') ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= ($role == 'teacher') ? 'selected' : '' ?>>Teacher</option>
                            <option value="school_admin" <?= ($role == 'school_admin') ? 'selected' : '' ?>>School Admin</option>
                            <option value="parent" <?= ($role == 'parent') ? 'selected' : '' ?>>Parent</option>
                            <option value="super_admin" <?= ($role == 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="status" class="block text-sm font-medium text-gray-900 dark:text-white">Account Status</label>
                    <div class="mt-2">
                        <select id="status" name="status" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10">
                            <option value="active" <?= ($status == 'active') ? 'selected' : 'selected' ?>>Active</option>
                            <option value="inactive" <?= ($status == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-6 flex justify-end gap-x-4">
                    <button type="button" onclick="document.getElementById('addUserForm').classList.add('hidden')" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Cancel</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>