<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM classes ORDER BY created_at DESC");

$stmt->execute([]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <div class="mb-6">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            Klasat e Mia
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Klasat që ju janë caktuar për këtë vit shkollor
          </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

          <div class="rounded-xl bg-white p-6 shadow-sm hover:shadow-md transition dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
            
            <?php foreach($classes as $class): ?>  
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <?= htmlspecialchars($class['grade']) ?>
              </h3>
              <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                <?= htmlspecialchars($row['subject_name']) ?>
              </span>
            </div>

            <!-- Info -->
            <div class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <p><?= htmlspecialchars($class['academic_year']) ?></p>
              <p><?= htmlspecialchars($class['max_students']) ?></p>
            </div>

            <!-- Actions -->
            <div class="mt-5 grid grid-cols-3 gap-2">
              <button class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-500">
                Prezenca
              </button>
              <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                Detyrat
              </button>
              <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Notat
              </button>
            </div>
            <?php endforeach; ?>
        </div>
      </div>
    </div>
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
