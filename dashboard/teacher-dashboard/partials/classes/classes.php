<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$userId = $_SESSION['user']['id']; // users.id
$stmt = $pdo->prepare("
    SELECT 
        tc.id,
        tc.class_id,
        c.grade AS class_name,
        c.max_students,
        t.subject_name,
        tc.created_at
    FROM teacher_class tc
    INNER JOIN teachers t ON t.id = tc.teacher_id
    INNER JOIN classes c ON c.id = tc.class_id
    WHERE t.user_id = ?
");


$stmt->execute([$userId]);

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
        <div class="sm:flex sm:items-center">
          <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Klasat</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Tabela që përmban të dhëna të klasave</p>
          </div>
        </div>
        <div class="-mx-4 mt-10 ring-1 ring-gray-300 sm:mx-0 sm:rounded-lg dark:ring-white/15">
          <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
            <thead>
              <tr>
                <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 dark:text-white">Klasa</th>
                <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell dark:text-white">Lënda</th>
                <th scope="col" class="hidden px-3 py-3.5 text-left text-sm font-semibold text-gray-900 lg:table-cell dark:text-white">Nxënës</th>
                <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-6">
                  <span class="sr-only">Hyr/Prezenca</span>
                </th>
              </tr>
            </thead>
            <?php foreach($classes as $row): ?>
            <tbody>
              <tr>
                <td class="flex relative py-4 pr-3 pl-4 text-sm sm:pl-6">
                <div><?= htmlspecialchars($row['class_name'])?></div>
                </td>
                 <td class="hidden px-3 py-3.5 text-sm text-gray-500 lg:table-cell dark:text-gray-400"><div><?= htmlspecialchars($row['subject_name'])?></div></td>
                <td class="hidden px-3 py-3.5 text-sm text-gray-500 lg:table-cell dark:text-gray-400"><?= htmlspecialchars($row['max_students'])?></td>
                <td class="relative py-3.5 pr-4 pl-3 text-right text-sm font-medium sm:pr-6">
                  <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>">
                    <button type="button" class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-white dark:bg-white/10 dark:text-white dark:inset-ring-white/10 dark:hover:bg-white/15 dark:disabled:hover:bg-white/10">Hyr në klasë</button>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
