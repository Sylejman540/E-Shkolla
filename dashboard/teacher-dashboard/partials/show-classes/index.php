<?php
$classId = $_GET['class_id'] ?? null;
$query = $classId ? '?class_id=' . (int)$classId : '';

?>
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
    <a href="/E-Shkolla/teacher-dashboard" class="relative flex h-16 shrink-0 items-center">
      <img src="/E-Shkolla/images/logo.png" alt="Your Company" class="w-48 h-48 dark:hidden" />
    </a>
    <nav class="relative flex flex-1 flex-col">
      <ul role="list" class="flex flex-1 flex-col gap-y-7">
        <li>
          <ul role="list" class="-mx-2 space-y-1">
          <li>
            <a href="/E-Shkolla/show-classes<?= $query ?>"
              class="group flex gap-x-3 rounded-md bg-gray-50 p-2 text-sm font-semibold text-indigo-600">
              📊 Paneli
            </a>
          </li>

          <li>
            <a href="/E-Shkolla/class-attendance<?= $query ?>"
              class="group flex gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600">
              📝 Prezenca
            </a>
          </li>

          <li>
            <a href="/E-Shkolla/class-assignments<?= $query ?>"
              class="group flex gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600">
              📄 Detyrat
            </a>
          </li>

          <li>
            <a href="/E-Shkolla/class-grades<?= $query ?>"
              class="group flex gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600">
              🎓 Notat
            </a>
          </li>
        </ul>
        </li>
        <li>
        </li>
         <li class="-mx-6 mt-auto">
        <?php
            require_once __DIR__  . '/../../../../db.php';

            $user_id = $_SESSION['user']['id'];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ?>
        <?php foreach ($teachers as $row): ?>
        <div class="flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition">
            
            <!-- <img src="/E-Shkolla/<?= htmlspecialchars($row['profile_photo']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-600"/> -->

            <div class="flex flex-col">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                <?= htmlspecialchars($row['name']) ?>
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Mësues
            </span>
            </div>
        </div>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</div>

</body>
</html>