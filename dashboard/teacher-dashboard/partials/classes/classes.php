<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../index.php'; 
require_once __DIR__ . '/../../../../db.php';

$userId = $_SESSION['user']['id']; 
$stmt = $pdo->prepare("
    SELECT 
        tc.id, tc.class_id, tc.subject_id,
        c.grade AS class_name, c.max_students,
        t.subject_name, tc.created_at
    FROM teacher_class tc
    INNER JOIN teachers t ON t.id = tc.teacher_id
    INNER JOIN classes c ON c.id = tc.class_id
    WHERE t.user_id = ?
");
$stmt->execute([$userId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="lg:pl-72 bg-gray-50 dark:bg-gray-950 min-h-screen">
    <div class="px-4 py-6 sm:px-6 lg:px-8">
        
        <div class="border-b border-gray-200 dark:border-white/10 pb-5 sm:flex sm:items-center sm:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:truncate sm:text-3xl tracking-tight">
                    Klasat e Mia
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Lista e klasave ku ju jepni mësim aktiv.
                </p>
            </div>
        </div>

        <div class="mt-8 flex flex-col">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl dark:ring-white/15">
                        <table class="min-w-full divide-y divide-gray-300 dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-bold text-gray-900 dark:text-white sm:pl-6">
                                        Klasa
                                    </th>
                                    <th scope="col" class="hidden sm:table-cell px-3 py-3.5 text-left text-sm font-bold text-gray-900 dark:text-white">
                                        Lënda
                                    </th>
                                    <th scope="col" class="hidden md:table-cell px-3 py-3.5 text-left text-sm font-bold text-gray-900 dark:text-white">
                                        Nxënës (Max)
                                    </th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Veprimi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
                                <?php foreach($classes as $row): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 flex items-center justify-center rounded-lg bg-indigo-600 text-white font-bold">
                                                <?= htmlspecialchars(substr($row['class_name'], 0, 2)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($row['class_name']) ?></div>
                                                <div class="sm:hidden text-gray-500 dark:text-gray-400 italic text-xs">
                                                    <?= htmlspecialchars($row['subject_name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hidden sm:table-cell whitespace-nowrap px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 dark:bg-indigo-400/10 px-2 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-400 ring-1 ring-inset ring-indigo-700/10">
                                            <?= htmlspecialchars($row['subject_name']) ?>
                                        </span>
                                    </td>
                                    <td class="hidden md:table-cell whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex items-center gap-1.5">
                                            <i data-lucide="users-2" class="size-4"></i>
                                            <?= htmlspecialchars($row['max_students']) ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>&subject_id=<?= (int)$row['subject_id'] ?>" 
                                           class="inline-flex items-center gap-1 bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-500 transition shadow-sm">
                                            <span class="hidden xs:inline">Hyr</span>
                                            <i data-lucide="chevron-right" class="size-4"></i>
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
    // Sigurohuni që Lucide Icons të jenë të ngarkuara
    if(typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>