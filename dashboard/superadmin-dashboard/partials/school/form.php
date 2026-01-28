<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: /E-Shkolla/login");
    exit();
}

require_once __DIR__ . '/../../../../db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid security token. Please refresh the page.');
    }

    $school_name = trim($_POST['school_name'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $email       = strtolower(trim($_POST['email'] ?? ''));
    $city        = trim($_POST['city'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'inactive';
    $password    = $_POST['password'] ?? '';

    if (!$school_name) $errors[] = "School name is required.";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new Exception("This email is already registered.");
            }

            $stmt = $pdo->prepare("INSERT INTO schools (school_name, name, email, city, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$school_name, $name, $email, $city, $status]);
            $schoolId = $pdo->lastInsertId();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'school_admin', ?)");
            $stmt->execute([$schoolId, $name, $email, $hashedPassword, $status]);

            $pdo->commit();
            header("Location: /E-Shkolla/super-admin-schools?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div id="addSchoolForm" class="<?= empty($errors) ? 'hidden' : '' ?> fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-black/60 backdrop-blur-sm overflow-y-auto p-2 sm:p-4">
    
    <div class="w-full max-w-4xl my-auto">
        <div class="rounded-xl bg-white p-6 sm:p-10 shadow-2xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-6 sm:mb-10">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Add a new school</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter the basic information about the school.</p>
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
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Institution Profile</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                        This creates the primary school record and assigns an administrator account.
                    </p>
                </div>

                <form action="" method="post" class="grid grid-cols-1 gap-y-5 sm:grid-cols-6 sm:gap-x-6 md:col-span-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">School Name</label>
                        <input type="text" name="school_name" value="" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">School Admin Name</label>
                        <input type="text" name="name" value="" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Admin Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Admin Password</label>
                        <input type="password" name="password" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white" required>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($city ?? '') ?>" 
                               class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white">
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white">Initial Status</label>
                        <select name="status" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 border dark:bg-white/5 dark:border-white/10 dark:text-white">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="sm:col-span-6 flex flex-col-reverse sm:flex-row justify-end gap-3 mt-4 sm:mt-6">
                        <button type="button" onclick="document.getElementById('addSchoolForm').classList.add('hidden')" 
                                class="w-full sm:w-auto px-6 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="w-full sm:w-auto rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            Save School
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>