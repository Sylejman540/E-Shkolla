<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// --- BACKEND PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = $_SESSION['user']['school_id'] ?? null;

    if (!$schoolId) {
        $_SESSION['error'] = "Gabim i sesionit: Shkolla nuk u identifikua. Ju lutem ridentifikohuni.";
        header("Location: /E-Shkolla/students");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Data Sanitization
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $class_id = $_POST['class'] ?? null;
        $gender = $_POST['gender'] ?? 'other';
        $date_birth = $_POST['date_birth'] ?? null;
        $status = $_POST['status'] ?? 'active';
        $student_code = trim($_POST['student_code'] ?? '');

        // 2. Validation Logic
        if (!$email) throw new Exception("Email është i pavlefshëm.");
        if (strlen($password) <= 7) {
            throw new Exception("Fjalëkalimi duhet të ketë të paktën 8 karaktere.");
        }
        if (!$name) throw new Exception("Emri dhe mbiemri kërkohen.");
        if (!$class_id) throw new Exception("Ju lutem zgjidhni një klasë.");
        if (!$date_birth) throw new Exception("Data e lindjes është e kërkuar.");
        if (!preg_match("/^[a-zA-ZÇçËë\s]+$/u", $name)) {throw new Exception("Emri duhet të përmbajë vetëm shkronja.");}

        // 3. Unique Check: Email (Checks user_id column)
        // 3. Unique Check: Email
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            throw new Exception("Ky email është i regjistruar paraprakisht.");
        }


        // 4. Handle Student Code (Auto-generate if empty)
        if (empty($student_code)) {
            $student_code = "STU-" . date("Y") . "-" . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Unique Check: Student Code (Checks student_id column)
        $checkCode = $pdo->prepare("SELECT student_id FROM students WHERE student_code = ? AND school_id = ?");
        $checkCode->execute([$student_code, $schoolId]);
        if ($checkCode->fetch()) {
            throw new Exception("Kodi i nxënësit ($student_code) ekziston në këtë shkollë.");
        }

        // 5. Fetch Class Details
        $stmtClass = $pdo->prepare("SELECT grade FROM classes WHERE id = ? AND school_id = ?");
        $stmtClass->execute([$class_id, $schoolId]);
        $classRow = $stmtClass->fetch();
        if (!$classRow) throw new Exception("Klasa e zgjedhur nuk ekziston.");

        // 6. Security: Hash Password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 7. Insert into Users Table
        $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'student', ?)");
        $stmtUser->execute([$schoolId, $name, $email, $hashedPassword, $status]);
        $newUserId = $pdo->lastInsertId();

        // 8. Insert into Students Table
        $stmtStudent = $pdo->prepare("INSERT INTO students (school_id, user_id, student_code, name, gender, class_name, class_id, date_birth, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtStudent->execute([$schoolId, $newUserId, $student_code, $name, $gender, $classRow['grade'], $class_id, $date_birth, $email, $status]);
        $studentId = $pdo->lastInsertId();

        // 9. Link to student_class Table
        $stmtLink = $pdo->prepare("INSERT INTO student_class (school_id, student_id, class_id) VALUES (?, ?, ?)");
        $stmtLink->execute([$schoolId, $studentId, $class_id]);

        $pdo->commit();
        $_SESSION['success'] = "Nxënësi u shtua me sukses!";
        unset($_SESSION['old']); // clear old data after success
        header("Location: /E-Shkolla/students");
        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        $_SESSION['error'] = $e->getMessage();
        $_SESSION['old'] = $_POST; // ONLY HERE

        header("Location: /E-Shkolla/students?open_form=1");
        exit;
    }
}
?>

<div id="addStudentForm" class="<?= isset($_GET['open_form']) ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
    <div class="w-full max-w-3xl px-4 pb-10">
        <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Shto nxënës të ri</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plotësoni të dhënat për regjistrimin e nxënësit.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('addStudentForm').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mt-4 p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200" role="alert">
                        <span class="font-medium">Gabim!</span> <?= htmlspecialchars($_SESSION['error']); ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/students/form.php" method="post">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 border-b border-gray-900/10 pb-8 dark:border-white/10">
                    
                    <div class="sm:col-span-3">
                        <label for="name" class="block text-sm font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
                        <div class="mt-2">
                            <input id="name" type="text" name="name" value="<?= htmlspecialchars($_SESSION['old']['name'] ?? '') ?>" required class="border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="password" class="block text-sm font-medium text-gray-900 dark:text-white">Fjalëkalimi (duhet të ketë të paktën 8 karaktere.)</label>
                        <div class="mt-2">
                            <input id="password" type="password" name="password" value="<?= htmlspecialchars($_SESSION['old']['password '] ?? '') ?>" required minlength="7" class="border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-white">Email</label>
                        <div class="mt-2">
                            <input id="email" type="email" name="email" value="<?= htmlspecialchars($_SESSION['old']['email'] ?? '') ?>"  required class="border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="student_code" class="block text-sm font-medium text-gray-900 dark:text-white">Kodi i Nxënësit (Opsionale)</label>
                        <div class="mt-2">
                            <input id="student_code" type="text" name="student_code" value="<?= htmlspecialchars($_SESSION['old']['student_code'] ?? '') ?>" placeholder="P.sh. STU-2026-0001" class="border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="class" class="block text-sm font-medium text-gray-900 dark:text-white">Klasa</label>
                        <div class="mt-2">
                            <select id="class" name="class" required class="border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-white/5 dark:text-white dark:outline-white/10">
                                <option value="">Zgjidhni klasën</option>
                                <?php
                                $schoolId = $_SESSION['user']['school_id'] ?? 0;
                                $classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                                $classes->execute([$schoolId]);
                                foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="date_birth" class="block text-sm font-medium text-gray-900 dark:text-white">Data e Lindjes</label>
                        <div class="mt-2">
                            <input id="date_birth" type="date" name="date_birth" value="<?= htmlspecialchars($_SESSION['old']['date_birth'] ?? '') ?>" required class="border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10" />
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="gender" class="block text-sm font-medium text-gray-900 dark:text-white">Gjinia</label>
                        <div class="mt-2">
                            <select id="gender" name="gender" class="border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-white/5 dark:text-white dark:outline-white/10">
                                <option value="male">Mashkull</option>
                                <option value="female">Femër</option>
                                <option value="other">Tjetër</option>
                            </select>
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="status" class="block text-sm font-medium text-gray-900 dark:text-white">Statusi</label>
                        <div class="mt-2">
                            <select id="status" name="status" class="border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-white/5 dark:text-white dark:outline-white/10">
                                <option value="active">Aktive</option>
                                <option value="inactive">Joaktive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-x-4">
                    <button type="button" onclick="document.getElementById('addStudentForm').classList.add('hidden')" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Anulo</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-indigo-600">Ruaj Nxënësin</button>
                </div>
            </form>
        </div>
    </div>
</div>