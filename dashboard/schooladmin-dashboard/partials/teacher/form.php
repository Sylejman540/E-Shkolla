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
        $_SESSION['error'] = "Gabim i sesionit: Shkolla nuk u identifikua.";
        header("Location: /E-Shkolla/teachers");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Data Sanitization
        $name          = trim($_POST['name'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $phone         = trim($_POST['phone'] ?? '');
        $gender        = $_POST['gender'] ?? 'other';
        $status        = $_POST['status'] ?? 'active';
        $password      = $_POST['password'] ?? '';
        $subject_name  = trim($_POST['subject_name'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        
        // IMPORTANT: This is now an array
        $class_ids     = $_POST['class'] ?? []; 

        // 2. Validation Logic
        if (empty($name) || empty($email) || empty($password) || empty($class_ids)) {
            throw new Exception("Ju lutem plotësoni fushat e kërkuara (Emri, Email, Password, dhe të paktën një Klasë).");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email adresa është e pavlefshme.");
        if (strlen($password) < 8) throw new Exception("Fjalëkalimi duhet të ketë të paktën 8 karaktere.");
        if (!preg_match("/^[a-zA-ZÇçËë\s]+$/u", $name)) throw new Exception("Emri duhet të përmbajë vetëm shkronja.");

        // 3. Unique Check: Email
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            throw new Exception("Ky email është i regjistruar paraprakisht.");
        }

        // 4. Handle File Upload
        $profile_photo = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $uploadDir = __DIR__ . '/../../../../uploads/teachers/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($fileExt, $allowed)) {
                throw new Exception("Lloji i imazhit i pavlefshëm. Lejohen: " . implode(', ', $allowed));
            }

            $newFileName = uniqid('teacher_', true) . '.' . $fileExt;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $newFileName)) {
                $profile_photo = 'uploads/teachers/' . $newFileName;
            }
        }

        // 5. Database Operations
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // A. Insert User
        $stmtUser = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', ?)");
        $stmtUser->execute([$schoolId, $name, $email, $hashedPassword, $status]);
        $user_id = $pdo->lastInsertId();

        // B. Insert Teacher Profile
        $stmtTeacher = $pdo->prepare("INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtTeacher->execute([$schoolId, $user_id, $name, $email, $phone, $gender, $subject_name, $status, $profile_photo]);
        $teacher_id = $pdo->lastInsertId();

        // C. Create Subject
        $stmtSubject = $pdo->prepare("INSERT INTO subjects (school_id, user_id, name, subject_name, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtSubject->execute([$schoolId, $user_id, $name, $subject_name, $description, $status]);
        $subject_id = $pdo->lastInsertId();

        // D. Link Teacher to MULTIPLE Classes
        $stmtLink = $pdo->prepare("INSERT INTO teacher_class (school_id, teacher_id, class_id, subject_id) VALUES (?, ?, ?, ?)");
        foreach ($class_ids as $cid) {
            $stmtLink->execute([$schoolId, $teacher_id, (int)$cid, $subject_id]);
        }

                // D. Link Teacher to MULTIPLE Classes
        // D. Link Teacher to Subject (teacher_subjects)
        $stmtLink = $pdo->prepare("
            INSERT INTO teacher_subjects (school_id, teacher_id, subject_id, class_id)
            VALUES (?, ?, ?, ?)
        ");

        $stmtLink->execute([
            $schoolId,
            $teacher_id,
            $subject_id,
            (int)$cid
        ]);


        $pdo->commit();
        $_SESSION['success'] = "Mësuesi u shtua me sukses!";
        unset($_SESSION['old']); 
        header("Location: /E-Shkolla/teachers");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        $_SESSION['old'] = $_POST; 
        header("Location: /E-Shkolla/teachers?show_modal=1");
        exit;
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">

<div id="addTeacherForm" class="<?= (isset($_GET['show_modal']) || isset($_SESSION['error'])) ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10 pb-10">
    <div class="w-full max-w-3xl px-4">
        <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Shto mësues të ri</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plotësoni të dhënat për të krijuar llogarinë e mësuesit.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('addTeacherForm').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mt-4 p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200" role="alert">
                        <span class="font-medium">Gabim!</span> <?= htmlspecialchars($_SESSION['error']); ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form action="" method="post" enctype="multipart/form-data">
                <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6 border-b border-gray-900/10 pb-8 dark:border-white/10">
                    
                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($_SESSION['old']['name'] ?? '') ?>" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Email</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_SESSION['old']['email'] ?? '') ?>" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Fjalëkalimi (duhet të ketë të paktën 8 karaktere.)</label>
                        <input type="password" name="password" required minlength="8" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Numri i telefonit</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($_SESSION['old']['phone'] ?? '') ?>" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Klasat</label>
                            <select id="class-select" name="class[]" multiple autocomplete="off" placeholder="Zgjidhni klasat..." class="mt-2 border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-gray-800 dark:text-white">
                                <?php
                                $s_id = $_SESSION['user']['school_id'] ?? 0;
                                $classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                                $classes->execute([$s_id]);
                                
                                $selectedClasses = $_SESSION['old']['class'] ?? [];
                                
                                foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" 
                                        <?= (is_array($selectedClasses) && in_array($c['id'], $selectedClasses)) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['grade']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Gjinia</label>
                        <select name="gender" class="mt-2 border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-gray-800 dark:text-white">
                            <option value="male" <?= (isset($_SESSION['old']['gender']) && $_SESSION['old']['gender'] == 'male') ? 'selected' : '' ?>>Mashkull</option>
                            <option value="female" <?= (isset($_SESSION['old']['gender']) && $_SESSION['old']['gender'] == 'female') ? 'selected' : '' ?>>Femër</option>
                            <option value="other" <?= (isset($_SESSION['old']['gender']) && $_SESSION['old']['gender'] == 'other') ? 'selected' : '' ?>>Tjetër</option>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Lënda</label>
                        <input type="text" name="subject_name" value="<?= htmlspecialchars($_SESSION['old']['subject_name'] ?? '') ?>" placeholder="Psh: Matematikë" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Statusi</label>
                        <select name="status" class="mt-2 border block w-full rounded-md bg-white p-[7px] text-sm dark:bg-gray-800 dark:text-white">
                            <option value="active" <?= (isset($_SESSION['old']['status']) && $_SESSION['old']['status'] == 'active') ? 'selected' : '' ?>>Aktive</option>
                            <option value="inactive" <?= (isset($_SESSION['old']['status']) && $_SESSION['old']['status'] == 'inactive') ? 'selected' : '' ?>>Joaktive</option>
                        </select>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Përshkrimi i lëndës</label>
                        <textarea name="description" rows="2" class="mt-2 border block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 focus:outline-indigo-600 dark:bg-white/5 dark:text-white"><?= htmlspecialchars($_SESSION['old']['description'] ?? '') ?></textarea>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">Foto e profilit</label>
                        <input type="file" name="profile_photo" class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-x-4">
                    <button type="button" onclick="document.getElementById('addTeacherForm').classList.add('hidden')" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Anulo</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-indigo-600">Ruaj Mësuesin</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    /* Remove border from Tom Select control */
.ts-control {
    border: none !important;
    box-shadow: none !important;
    background-color: transparent;
}

/* Remove border from dropdown */
.ts-dropdown {
    border: none !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08); /* optional soft shadow */
}

/* Remove focus ring */
.ts-control:focus,
.ts-control.focus {
    box-shadow: none !important;
    outline: none !important;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    new TomSelect("#class-select", {
        plugins: ['remove_button'],
        create: false,
        persist: false,
        placeholder: 'Zgjidhni klasat...'
    });
});

</script>