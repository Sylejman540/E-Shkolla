<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__  . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $subject = $_POST['subject'];
    $status = $_POST['status'];
    $profile_photo = null;

    if (!empty($_FILES['profile_photo']['name'])) {

        $uploadDir = __DIR__ . '/../../../../uploads/teachers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp  = $_FILES['profile_photo']['tmp_name'];
        $fileName = $_FILES['profile_photo']['name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allowed image types
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($fileExt, $allowed)) {
            die('Invalid image type');
        }

        // Unique filename
        $newFileName = uniqid('teacher_', true) . '.' . $fileExt;
        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            // Save relative path in DB
            $profile_photo = 'uploads/teachers/' . $newFileName;
        }
    }


    $stmt = $pdo->prepare("INSERT INTO teachers(name, email, phone, gender, subject, status, profile_photo) VALUES(?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $gender, $subject, $status, $profile_photo]);

    header("Location: /E-Shkolla/teachers");
    exit;
}

?>
<div id="addSchoolForm" class="hidden mt-8">
  <div class="flex justify-center">
    <div class="w-full max-w-4xl">

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

            <div class="sm:col-span-4">
            <label for="phone" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Numri i telefonit</label>
            <div class="mt-2">
                <input id="phone" type="phone" name="phone" autocomplete="phone" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="gender" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Gjinia</label>
            <div class="mt-2">
                <input id="gender" type="text" name="gender" autocomplete="gender" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="subject" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Lënda</label>
            <div class="mt-2">
                <input id="subject" type="text" name="subject" autocomplete="subject" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
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
                <input id="status" type="text" name="status" autocomplete="status" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
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
