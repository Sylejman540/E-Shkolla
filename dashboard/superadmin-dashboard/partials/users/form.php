<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

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
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = 'Email already exists.';
        } else {
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


$stmt = $pdo->query("SELECT id, school_name FROM schools ORDER BY school_name ASC");
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="addUserForm" class="<?= empty($errors) ? 'hidden' : '' ?> fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-black/60 backdrop-blur-sm overflow-y-auto p-2 sm:p-4">
    
    <div class="w-full max-w-4xl my-auto">
        <div class="rounded-xl bg-white p-6 sm:p-10 shadow-2xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-6 sm:mb-10">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Add a new user</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new account and assign system permissions.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/10 dark:border-red-800">
                    <ul class="text-xs sm:text-sm text-red-600 dark:text-red-400 list-disc list-inside space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-y-10 md:grid-cols-3 md:gap-x-12">
                
                <div class="border-b border-gray-100 pb-6 md:border-none md:pb-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Account Details</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                        Assign the user to a specific school and define their role. All passwords are encrypted immediately.
                    </p>
                </div>

                <form action="" method="post" class="grid grid-cols-1 gap-y-5 sm:grid-cols-6 sm:gap-x-6 md:col-span-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Full Name</label>
                        <input type="text" name="name" value="" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Assign School</label>
                        <select name="school_id" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= (isset($schoolId) && $schoolId == $school['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">System Role</label>
                        <select name="role" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white">
                            <option value="student" <?= ($role == 'student') ? 'selected' : '' ?>>Student</option>
                            <option value="teacher" <?= ($role == 'teacher') ? 'selected' : '' ?>>Teacher</option>
                            <option value="parent" <?= ($role == 'parent') ? 'selected' : '' ?>>Parent</option>
                            <option value="school_admin" <?= ($role == 'school_admin') ? 'selected' : '' ?>>School Admin</option>
                            <option value="super_admin" <?= ($role == 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Password</label>
                        <input type="password" name="password" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Status</label>
                        <select name="status" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="sm:col-span-6 flex flex-col-reverse sm:flex-row justify-end gap-3 mt-4 sm:mt-6">
                        <button type="button" id="cancel" 
                                class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="w-full sm:w-auto rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>