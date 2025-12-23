<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM teachers ORDER BY created_at DESC");
$stmt->execute();

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<main class="lg:pl-72 ml-48 mr-48">
  <div class="xl:pl-18">
    <ul role="list" class="divide-y divide-gray-100 dark:divide-white/5">
    <?php foreach($teachers as $row): ?>
     <li class="flex justify-between gap-x-6 py-5">
        <div class="flex min-w-0 gap-x-4">
        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="" class="size-12 flex-none rounded-full bg-gray-50 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10" />
        <div class="min-w-0 flex-auto">
            <p class="text-sm/6 font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($row['name'])?></p>
            <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['email'])?></p>
        </div>
        </div>
        <div class="hidden shrink-0 sm:flex sm:flex-col sm:items-end">
        <p class="text-sm/6 text-gray-900 dark:text-white">Teacher</p>
        <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">Active</time></p>
        </div>
     </li>
    <?php endforeach; ?>
    </ul>
    </div>
  </div>
</main>
<script>
  const btn = document.getElementById('addSchoolBtn');
  const form = document.getElementById('addSchoolForm');
  const cancel = document.getElementById('cancel');

  btn?.addEventListener('click', () => {
    form.classList.remove('hidden');
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  cancel?.addEventListener('click', () => {
    form.classList.add('hidden');
  });
</script>

</body>
</html>
