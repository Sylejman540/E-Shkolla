<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM parents ORDER BY created_at DESC");
$stmt->execute();

$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Prindërit</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Lista e të gjithë prindërve në sistemin tuaj, duke përfshirë fëmijën dhe statusin e tyre.</p>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Emri dhe mbiemri</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Numri i telefonit</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Kujdestari/ja</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Data e krijimit</th>
                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                            <span class="sr-only">Edit</span>
                        </th>
                    </tr>
                </thead>
                <?php if(!empty($parents)): ?>
                <?php foreach($parents as $row): ?>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr>
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <?php
                                echo match ($row['relation']) {
                                    'father'   => 'Babi',
                                    'mother' => 'Nëna',
                                    'guardian' => "Kujestar",
                                    'other'  => 'Tjetër',
                                    default  => '-',
                                };
                            ?>
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <?php
                                $statusLabel = match ($row['status']) {
                                    'active'   => 'Aktiv',
                                    'inactive' => 'Joaktiv',
                                    default    => '-',
                                };

                                $statusClass = match ($row['status']) {
                                    'active'   => 'bg-green-200 text-green-700',
                                    'inactive' => 'bg-red-200 text-red-700',
                                    default    => 'bg-gray-200 text-gray-700',
                                };
                            ?>

                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                <?= $statusLabel ?>
                            </span>
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['created_at']) ?></td>
                        <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-0">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Edit<span class="sr-only">, Lindsay Walton</span></a>
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

        <?php require_once 'form.php'; ?>
        </div>
        </div>
    </div>
  </div>
</main>
<script>
  const btn = document.getElementById('addParentBtn');
  const form = document.getElementById('addSchoolForm');
  const cancel = document.getElementById('cancel');

  btn?.addEventListener('click', () => {
    form.classList.remove('hidden');
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  cancel?.addEventListener('click', () => {
    form.classList.add('hidden');
  });

  document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const shouldOpenForm = params.get('open_form');

    if (shouldOpenForm === '1') {
        const form = document.getElementById('addSchoolForm');
        if (form) {
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
});
</script>

</body>
</html>
