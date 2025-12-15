<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id      = (int) $_POST['id'];   // ✅ REQUIRED
    $name    = $_POST['name'];
    $email   = $_POST['email'];
    $phone   = $_POST['phone'];
    $subject = $_POST['subject'];

    $stmt = $pdo->prepare("
        UPDATE teachers 
        SET 
            name = ?, 
            email = ?, 
            phone = ?, 
            subject = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $email,
        $phone,
        $subject,
        $id
    ]);

    header("Location: /E-Shkolla/teachers");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div id="addSchoolForm" class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
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
            
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/update-teacher.php" method="post" enctype="multipart/form-data"  class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
            <input type="hidden" name="id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
        
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
            <label for="subject" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Lënda</label>
            <div class="mt-2">
                <input id="subject" type="text" name="subject" autocomplete="subject" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
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
</body>
</html>