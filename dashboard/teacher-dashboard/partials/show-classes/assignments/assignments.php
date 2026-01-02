<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM assignments ORDER BY created_at DESC");
$stmt->execute([]);

$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<main class="lg:pl-72">
  <div class="xl:pl-18">
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6 relative">
        <div class="px-4 sm:px-6 lg:px-8">
<div class="px-4 sm:px-6 lg:px-8">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">
        Detyrat
      </h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Menaxho detyrat për klasat e tua
      </p>
    </div>

    <button id="addSchoolBtn" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
      + Shto Detyrë
    </button>
    </div>
    <?php foreach($assignments as $row): ?>
    <div class="space-y-4">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800/75">
        <div class="flex items-start justify-between">
            <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                <?= htmlspecialchars($row['description']) ?>
            </h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                <?= htmlspecialchars($row['title']) ?>
                <?= htmlspecialchars($row['due_date']) ?>
            </p>
            </div>

            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
            E hapur
            </span>
        </div>
    <?php endforeach; ?>
        <div class="mt-4 flex gap-2">
            <button class="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-500">
            Shiko
            </button>
            <button class="rounded-md bg-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-300">
            Edit
            </button>
            <button class="rounded-md bg-red-100 px-3 py-1.5 text-sm text-red-600 hover:bg-red-200">
            Fshi
            </button>
        </div>
        </div>
    </div>
    </div>
    </div>
        <?php require_once 'form.php'; ?>  </div>
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
