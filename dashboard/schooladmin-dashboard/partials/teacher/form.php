<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = $_POST['name'];
    $email   = $_POST['email'];
    $phone   = $_POST['phone'];
    $gender  = $_POST['gender'];
    $status  = $_POST['status'];
    $password = $_POST['password']; 
    $subject_name = $_POST['subject_name'];
    $description = $_POST['description'];
    $schoolId = $_SESSION['user']['school_id'] ?? null;
    $user_id = $_SESSION['user']['id'] ?? null;

    if (!$schoolId) {
        die('School ID missing from session');
    }

    $profile_photo = null;

    if (!empty($_FILES['profile_photo']['name'])) {
        $uploadDir = __DIR__ . '/../../../../uploads/teachers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp  = $_FILES['profile_photo']['tmp_name'];
        $fileName = $_FILES['profile_photo']['name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($fileExt, $allowed)) {
            die('Invalid image type');
        }

        $newFileName = uniqid('teacher_', true) . '.' . $fileExt;
        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $profile_photo = 'uploads/teachers/' . $newFileName;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', ?)");
    $stmt->execute([$schoolId, $name, $email, $password, $status]);

    $stmt = $pdo->prepare("INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $user_id, $name, $email, $phone, $gender, $subject_name, $status, $profile_photo]);

    $stmt = $pdo->prepare("INSERT INTO subjects(school_id, user_id, name, subject_name, description, status) VALUES(?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $user_id, $name, $subject_name, $description, $status]);

    header("Location: /E-Shkolla/teachers");
    exit;
}
?>

<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
    <div class="w-full max-w-3xl px-4">


      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Shto mësues të ri
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Plotësoni të dhënat bazë për mësuesin.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">
            
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/form.php" method="post" enctype="multipart/form-data"  class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">

            <div class="sm:col-span-3">
            <label for="name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
            <div class="mt-2">
                <input id="name" type="text" name="name" autocomplete="name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Email</label>
            <div class="mt-2">
                <input id="email" type="text" name="email" autocomplete="email" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="password" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Password</label>
            <div class="mt-2">
                <input id="password" type="text" name="password" autocomplete="password" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-4">
            <label for="phone" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Numri i telefonit</label>
            <div class="mt-2">
                <input id="phone" type="phone" name="phone" autocomplete="phone" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="gender" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Gjinia</label>
            <div class="mt-2">
                <select id="gender" name="gender" class="border block w-full rounded-md p-[7px] mt-1">
                  <option value="male">Mashkull</option>
                  <option value="female">Femër</option>
                  <option value="other">Tjetër</option>
                </select>
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="subject_name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Lënda</label>
            <div class="mt-2">
                <input id="subject_name" type="text" name="subject_name" autocomplete="subject" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-4">
            <label for="description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Përshkrimi rreth lëndës</label>
            <div class="mt-2">
                <input id="description" type="text" name="description" autocomplete="description" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="profile_photo" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Foto e profilit</label>
            <div class="mt-2">
                <input id="profile_photo" type="file" name="profile_photo" autocomplete="profile_photo" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-2">
            <label for="status" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Statusi</label>
            <div class="mt-2">
                <select id="status" name="status" class="border block w-full rounded-md p-[9px] mt-1">
                  <option value="active">Aktive</option>
                  <option value="inactive">Joaktive</option>
                </select>
            </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-x-4">
            <button type="button" id="cancel" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Cancel</button>

            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500">Save</button>
        </div>
        </form>
    </div>
  </div>
</div>
