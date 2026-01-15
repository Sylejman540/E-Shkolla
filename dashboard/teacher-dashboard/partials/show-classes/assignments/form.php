<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $classId   = (int) ($_GET['class_id'] ?? 0);
    $teacherId = (int) ($_SESSION['user']['id'] ?? 0);
    $schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);

    if (!$classId || !$teacherId || !$schoolId) {
        die('Invalid context');
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = $_POST['due_date'] ?? null;
    $status      = $_POST['completed_at'] ?? 'active';

    $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("INSERT INTO assignments(class_id, teacher_id, school_id, title, description, due_date, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([$classId, $teacherId, $schoolId, $title, $description, $due_date, $completedAt]);

    header("Location: /E-Shkolla/class-assignments?class_id=$classId");
    exit;
}
?>
<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
    <div class="w-full max-w-3xl px-4">


      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Shto detyrë të re
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Plotësoni të dhënat bazë rreth detyrës.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">
            
        <form action="/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/form.php?class_id=<?= (int)$_GET['class_id'] ?>" method="post" class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">

            <div class="sm:col-span-3">
            <label for="title" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Titulli i detyrës</label>
            <div class="mt-2">
                <input id="title" type="text" name="title" autocomplete="title" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="due_date" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Afati i dorëzimit</label>
            <div class="mt-2">
                <input id="due_date" type="text" name="due_date" autocomplete="due_date" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Përmbledhja</label>
            <div class="mt-2">
                <input id="description" type="text" name="description" autocomplete="description" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div> 
            </div>

            <div class="sm:col-span-3">
            <label for="completed_at" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Completed At</label>
            <div class="mt-2">
              <select id="completed_at" name="completed_at" autocomplete="completed_at" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
                <option value="active">Active</option>
                <option value="done">Done</option>
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
