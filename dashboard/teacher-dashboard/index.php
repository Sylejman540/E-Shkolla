<!DOCTYPE html>
<html lang="en" class="h-full bg-white dark:bg-gray-900"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla </title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">

<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
  <div class="relative flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 dark:border-white/10 dark:bg-gray-900 dark:before:pointer-events-none dark:before:absolute dark:before:inset-0 dark:before:bg-black/10">
    <a href="/E-Shkolla/super-admin-schools" class="relative flex h-16 shrink-0 items-center">
      <img src="/E-Shkolla/images/logo.png" alt="Your Company" class="w-48 h-48 dark:hidden" />
    </a>
    <nav class="relative flex flex-1 flex-col">
      <ul role="list" class="flex flex-1 flex-col gap-y-7">
        <li>
          <ul role="list" class="-mx-2 space-y-1">
            <li>
            <a href="/E-Shkolla/teacher-dashboard"
                class="group flex gap-x-3 rounded-md bg-gray-50 p-2 text-sm/6 font-semibold text-indigo-600 dark:bg-white/5 dark:text-white">
                📊
                Paneli
            </a>
            </li>

            <li>
            <a href="/E-Shkolla/teacher-classes"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                📚
                Klasat e Mia
            </a>
            </li>

            <li>
            <a href="/E-Shkolla/teacher-attendance"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                📝
                Prezenca
            </a>
            </li>

            <li>
            <a href="/E-Shkolla/teacher-assignments"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                📄
                Detyrat
            </a>
            </li>

            <li>
            <a href="/E-Shkolla/teacher-grades"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-white">
                🎓
                Notat
            </a>
            </li>

            <li>
            <a href="/E-Shkolla/teacher-schedule"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-white">
                🕒
                Orari
            </a>
            </li>
          </ul>
        </li>
        <li>
        <div class="text-xs/6 font-semibold text-gray-400 dark:text-gray-500">Profili</div>
          <ul role="list" class="-mx-2 mt-2 space-y-1">
            <li>
            <a href="/E-Shkolla/teacher-profile"
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-white">
                👤
                Profili
            </a>
            </li>
            <li>
            <a href="/E-Shkolla/logout" class="flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-red-600 hover:bg-red-50">
              🚪 Logout
            </a>
            </li>
          </ul>
        </li>
        <li class="-mx-6 mt-auto">
        <?php
            require_once __DIR__  . '/../../db.php';

            $stmt = $pdo->prepare("SELECT * FROM teachers ORDER BY created_at DESC");
            $stmt->execute([]);

            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ?>
        <?php foreach ($teachers as $row): ?>
        <div class="flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition">
            
            <img src="/E-Shkolla/<?= htmlspecialchars($row['profile_photo']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-600"/>

            <div class="flex flex-col">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                <?= htmlspecialchars($row['name']) ?>
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Teacher
            </span>
            </div>
        </div>
        <?php endforeach; ?>

        </li>
      </ul>
    </nav>
  </div>
</div>

<div class="sticky top-0 z-40 flex items-center gap-x-6 bg-white px-4 py-4 shadow-xs sm:px-6 lg:hidden dark:bg-gray-900 dark:shadow-none dark:before:pointer-events-none dark:before:absolute dark:before:inset-0 dark:before:border-b dark:before:border-white/10 dark:before:bg-black/10">
  <button type="button" command="show-modal" commandfor="sidebar" class="relative -m-2.5 p-2.5 text-gray-700 lg:hidden dark:text-gray-400">
    <span class="sr-only">Open sidebar</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
      <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </button>
  <div class="relative flex-1 text-sm/6 font-semibold text-gray-900 dark:text-white">Dashboard</div>
  <a href="#" class="relative">
    <span class="sr-only">Your profile</span>
    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="" class="size-8 rounded-full bg-gray-50 outline -outline-offset-1 outline-black/5 dark:bg-gray-800 dark:outline-white/10" />
  </a>
</div>

</body>
</html>