<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__  . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $class = $_POST['class'];
    $parent_name = $_POST['parent_name'];
    $parent_phone = $_POST['parent_phone'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    $schoolId = $_SESSION['user']['school_id'] ?? null;
    $user_id = $_SESSION['user']['id'] ?? null;

    if (!$schoolId) {
        die('School ID missing from session');
    }

    $stmt = $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, NULL, ?, 'student', ?)");
    $stmt->execute([$schoolId, $name, $password, $status]);

    $stmt = $pdo->prepare("INSERT INTO students(school_id, user_id, name, gender, class, parent_name, parent_phone, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $user_id, $name, $gender, $class, $parent_name, $parent_phone, $status]);

    $stmt = $pdo->prepare("INSERT INTO parents(school_id, user_id, parent_name, parent_phone, parent_email, relation, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $user_id, $parent_name, $parent_phone, $parent_email, $relation, $status]);


    header("Location: /E-Shkolla/students");
    exit;
}

?>
<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
    <div class="w-full max-w-3xl px-4">

      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Shto nxënës të ri
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Plotësoni të dhënat bazë për nxënësin.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">
            
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/students/form.php" method="post" class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">

            <div class="sm:col-span-3">
            <label for="name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
            <div class="mt-2">
                <input id="name" type="text" name="name" autocomplete="name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="password" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Fjalëkalim</label>
            <div class="mt-2">
                <input id="password" type="text" name="password" autocomplete="password" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-2">
            <label for="gender" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Gjinia</label>
            <div class="mt-2">
              <select id="gender" name="gender" autocomplete="sex" class="border block w-full rounded-md bg-white p-[7px] text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
                <option value="male">Mashkull</option>
                <option value="female">Femër</option>
                <option value="other">Tjetër</option>
              </select>
            </div>
            </div>

            <div class="sm:col-span-4">
            <label for="class" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Klasa</label>
            <div class="mt-2">
                <input id="class" type="class" name="class" autocomplete="class" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="parent_name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Emri i prindërit</label>
            <div class="mt-2">
                <input id="parent_name" type="text" name="parent_name" autocomplete="parent_name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="parent_phone" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Numri i prindërit</label>
            <div class="mt-2">
                <input id="parent_phone" type="text" name="parent_phone" autocomplete="parent_phone" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-4">
            <label for="parent_email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Emaili i prindërit</label>
            <div class="mt-2">
                <input id="parent_email" type="text" name="parent_email" autocomplete="parent_email" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-2">
            <label for="relation" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Kujdestari/ja</label>
            <div class="mt-2">
              <select id="relation" name="relation" autocomplete="relation" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
                  <option value="mother">Nëna</option>
                  <option value="father">Babai</option>
                  <option value="guardian">Kujdestari/ja</option>
                  <option value="other">Tjetër</option>
              </select>
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="status" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Statusi</label>
            <div class="mt-2">
              <select id="status" name="status" autocomplete="status" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
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
