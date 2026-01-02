<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../index.php'; 

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
    <div>
      <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pinned Projects</h2>

      <ul role="list" class="mt-3 grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
        <?php foreach($assignments as $row): ?>
          <li class="col-span-1 flex rounded-md shadow-xs dark:shadow-none">
            
            <div class="flex w-16 shrink-0 items-center justify-center rounded-l-md bg-pink-600 text-sm font-medium text-white dark:bg-pink-700">
              <?= htmlspecialchars($row['title']) ?>
            </div>

            <div class="flex flex-1 items-center justify-between truncate rounded-r-md border-t border-r border-b border-gray-200 bg-white dark:border-white/10 dark:bg-gray-800/50">
              <div class="flex-1 truncate px-4 py-2 text-sm">
                <a href="#" class="font-medium text-gray-900 hover:text-gray-600 dark:text-white dark:hover:text-gray-200">
                  <?= htmlspecialchars($row['description']) ?>
                </a>
                <p class="text-gray-500 dark:text-gray-400">
                  <?= htmlspecialchars($row['due_date']) ?>
                </p>
              </div>

              <div class="shrink-0 pr-2">
                <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-gray-400 hover:text-gray-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:hover:text-white dark:focus:outline-white">
                  <span class="sr-only">Open options</span>
                  <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                    <path d="M10 3a1.5 1.5 0 1 1 0 3ZM10 8.5a1.5 1.5 0 1 1 0 3ZM11.5 15.5a1.5 1.5 0 1 0-3 0Z" />
                  </svg>
                </button>
              </div>
            </div>

          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php require_once 'form.php'; ?>  
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
