<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

// Check if there are errors stored from a previous redirect
$session_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']); // Clear them so they don't persist forever

$schoolId = $_SESSION['user']['school_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // 1. Data Collection & Sanitization
    $name          = trim($_POST['name'] ?? '');
    $email         = strtolower(trim($_POST['email'] ?? ''));
    $phone         = trim($_POST['phone'] ?? '');
    $gender        = $_POST['gender'] ?? '';
    $status        = $_POST['status'] ?? 'active';
    $password      = $_POST['password'] ?? '';
    $subject_name  = trim($_POST['subject_name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $class_id      = (int) ($_POST['class'] ?? 0);

    // 2. Validation
    if (!$schoolId) $errors[] = "Gabim sesioni: ID e shkollës mungon.";
    if (empty($name) || empty($email) || empty($password) || empty($class_id)) {
        $errors[] = "Ju lutem plotësoni fushat e kërkuara (Emri, Email, Password, dhe Klasa).";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email adresa është e pavlefshme.";
    if (strlen($password) < 5) $errors[] = "Fjalëkalimi duhet të jetë të paktën 5 karaktere.";

    // 3. Handle File Upload
    $profile_photo = null;
    if (!empty($_FILES['profile_photo']['name']) && empty($errors)) {
        $uploadDir = __DIR__ . '/../../../../uploads/teachers/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileTmp = $_FILES['profile_photo']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($fileExt, $allowed)) {
            $errors[] = "Lloji i imazhit i pavlefshëm. Lejohen: " . implode(', ', $allowed);
        } else {
            $newFileName = uniqid('teacher_', true) . '.' . $fileExt;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $profile_photo = 'uploads/teachers/' . $newFileName;
            } else {
                $errors[] = "Dështoi ngarkimi i fotos.";
            }
        }
    }

    // 4. Database Transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // A. Check if email exists
            $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) throw new Exception("Ky email është i regjistruar paraprakisht.");

            // B. Create User
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', ?)");
            $stmt->execute([$schoolId, $name, $email, $hashedPassword, $status]);
            $user_id = $pdo->lastInsertId();

            // C. Create Teacher Profile
            $stmt = $pdo->prepare("INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$schoolId, $user_id, $name, $email, $phone, $gender, $subject_name, $status, $profile_photo]);
            $teacher_id = $pdo->lastInsertId();

            // D. Create Subject
            $stmt = $pdo->prepare("INSERT INTO subjects (school_id, name, subject_name, description, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$schoolId, $name, $subject_name, $description, $status]);
            $subject_id = $pdo->lastInsertId();

            // E. Link
            $stmt = $pdo->prepare("INSERT INTO teacher_class (school_id, teacher_id, class_id, subject_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$schoolId, $teacher_id, $class_id, $subject_id]);

            $pdo->commit();
            header("Location: /E-Shkolla/teachers?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }

    // If we reached here, there are errors. 
    // Save to session and redirect back to keep the URL clean but show errors.
    $_SESSION['form_errors'] = $errors;
    header("Location: /E-Shkolla/teachers");
    exit;
}
?>

<div id="addTeacherForm" class="<?= (isset($_GET['show_modal']) || !empty($session_errors)) ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm overflow-y-auto pt-10 pb-10">
    <div class="w-full max-w-3xl px-4">
        <div class="rounded-xl bg-white p-8 shadow-2xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Shto mësues të ri</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plotësoni të dhënat për të krijuar llogarinë dhe profilin e mësuesit.</p>
            </div>

            <?php if (!empty($session_errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-md">
                    <p class="font-bold mb-1">Ju lutem korrigjoni gabimet e mëposhtme:</p>
                    <ul class="list-disc list-inside">
                        <?php foreach ($session_errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data" class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring-indigo-600 sm:text-sm p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring-indigo-600 sm:text-sm p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Password</label>
                    <input type="password" name="password" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring-indigo-600 sm:text-sm p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Numri i telefonit</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Klasa</label>
                    <select name="class" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <option value="">Zgjidh Klasën</option>
                        <?php
                        $classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                        $classes->execute([$schoolId]);
                        foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Gjinia</label>
                    <select name="gender" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <option value="male">Mashkull</option>
                        <option value="female">Femër</option>
                        <option value="other">Tjetër</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Lënda</label>
                    <input type="text" name="subject_name" value="<?= htmlspecialchars($_POST['subject_name'] ?? '') ?>" placeholder="Psh: Matematikë" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Statusi</label>
                    <select name="status" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        <option value="active">Aktive</option>
                        <option value="inactive">Joaktive</option>
                    </select>
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Përshkrimi i lëndës</label>
                    <textarea name="description" rows="2" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm p-2 border"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">Foto e profilit</label>
                    <input type="file" name="profile_photo" class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <div class="sm:col-span-6 flex justify-end gap-x-4 mt-4 border-t pt-6">
                    <button type="button" onclick="window.location.href='/E-Shkolla/teachers'" class="text-sm font-semibold text-gray-700 hover:text-gray-900">Anulo</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">Ruaj Mësuesin</button>
                </div>
            </form>
        </div>
    </div>
</div>