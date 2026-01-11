<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../index.php'; 

require_once __DIR__ . '/../../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $present = $_POST['present'] ?? null;
    $missing = $_POST['missing'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO attendance (present, missing)
        VALUES (:present, :missing)
    ");

    $stmt->execute([
        ':present' => $present,
        ':missing' => $missing
    ]);
}

$classId = (int)($_GET['class_id'] ?? 0);

if ($classId <= 0) {
    die('Invalid class ID');
}

$stmt = $pdo->prepare("
    SELECT 
        sc.id AS student_class_id,
        sc.class_id,
        s.student_id,
        s.name,
        s.email,
        s.status
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");

$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM assignments ORDER BY created_at DESC");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// simple stats
$total = count($assignments);
$completed = 0; // later you can calculate real values
$active = $total - $completed;
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
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
        <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Notat</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Lista e të gjithë notave në klasë</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 mt-4">
          <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Mesatarja e notave në klasë</p>
            <p class="mt-2 text-2xl font-bold text-gray-900"><?= $total ?></p>
          </div>

          <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nxënës pa notë</p>
            <p class="mt-2 text-2xl font-bold text-indigo-600"><?= $active ?></p>
          </div>

          <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nota më e lartë</p>
            <p class="mt-2 text-2xl font-bold text-green-600"><?= $completed ?></p>
          </div>

          <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nota më e ulët</p>
            <p class="mt-2 text-2xl font-bold text-pink-600">—</p>
          </div>
        </div>

        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Emri i nxënësit</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Nota</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Koment</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                            <span class="sr-only">Edit</span>
                        </th>
                    </tr>
                </thead>
                <?php if(!empty($students)): ?>
                <?php foreach($students as $row): ?>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr>
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white"><?= htmlspecialchars($row['name']) ?></td>
<td class="py-4 px-3 text-sm whitespace-nowrap">
    <input
        id="title"
        type="text"
        name="title"
        autocomplete="title"
        placeholder="Nota"
        class="w-20 text-center font-semibold
               rounded-md border border-gray-300
               bg-white px-2 py-1.5 text-sm text-gray-900
               focus:outline-none focus:ring-2 focus:ring-indigo-500/30
               dark:bg-white/5 dark:border-white/10 dark:text-white"
    />
</td>

<td class="py-4 px-3 text-sm">
    <input
        id="title"
        type="text"
        name="title"
        autocomplete="title"
        placeholder="Koment"
        class="block w-full rounded-md
               border border-gray-300
               bg-white px-3 py-1.5 text-sm text-gray-900
               focus:outline-none focus:ring-2 focus:ring-indigo-500/30
               dark:bg-white/5 dark:border-white/10 dark:text-white"
    />
</td>

                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                            <p class="text-green-500 py-[1px] w-14 px-2 h-6 bg-green-200 rounded-xl">
                                <?= htmlspecialchars($row['status']) ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
                <?php endforeach ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            Tabela nuk përmban të dhëna
                        </td>
                    </tr>
                <?php endif; ?>
                </table>
            </div>
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
