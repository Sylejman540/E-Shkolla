<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}


$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null; // Assumes teacher_id is stored in session
if (!$teacherId) {
    die("Teacher ID not found in session.");
}


// Ensure $content is being captured correctly if using a layout wrapper
require_once __DIR__ . '/../../../../db.php';


$stmt = $pdo->prepare("
    SELECT 
        tc.id,
        tc.class_id,
        tc.subject_id,
        c.grade AS class_name,
        c.max_students,
        s.subject_name
    FROM teacher_class tc
    INNER JOIN classes c ON c.id = tc.class_id
    INNER JOIN subjects s ON s.id = tc.subject_id
    WHERE tc.teacher_id = ?
");
$stmt->execute([$teacherId]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);


ob_start();
?>

<div class="space-y-8">
    
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl tracking-tight">
                Klasat e Mia
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Menaxhoni nxënësit dhe notat për klasat tuaja aktuale.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="bg-white overflow-hidden shadow-sm border border-slate-100 rounded-2xl p-5">
            <dt class="text-sm font-medium text-slate-500 truncate">Total Klasa</dt>
            <dd class="mt-1 text-3xl font-semibold text-blue-600"><?= count($classes) ?></dd>
        </div>
    </div>

    <div class="bg-white border border-slate-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/50">
                    <tr>
                        <th scope="col" class="py-4 pl-6 pr-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Klasa
                        </th>
                        <th scope="col" class="hidden sm:table-cell px-3 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Lënda
                        </th>
                        <th scope="col" class="hidden md:table-cell px-3 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Nxënësit
                        </th>
                        <th scope="col" class="relative py-4 pl-3 pr-6 text-right">
                            <span class="sr-only">Veprimi</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-slate-500 italic">
                                Nuk u gjet asnjë klasë e caktuar.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($classes as $row): ?>
                    <tr class="hover:bg-blue-50/30 transition-colors group">
                        <td class="whitespace-nowrap py-5 pl-6 pr-3">
                            <div class="flex items-center">
                                <div class="h-11 w-11 flex-shrink-0 flex items-center justify-center rounded-xl bg-blue-600 text-white font-bold shadow-sm shadow-blue-200">
                                    <?= htmlspecialchars($row['class_name']) ?>
                                </div>
                                <div class="ml-4">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($row['class_name']) ?></div>
                                    <div class="sm:hidden text-slate-500 text-xs mt-0.5">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell whitespace-nowrap px-3 py-5">
                            <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                <?= htmlspecialchars($row['subject_name']) ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap px-3 py-5 text-sm text-slate-600">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m16-10a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <?= htmlspecialchars($row['max_students']) ?> Studentë
                            </div>
                        </td>
                        <td class="whitespace-nowrap py-5 pl-3 pr-6 text-right text-sm font-medium">
                            <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>&subject_id=<?= (int)$row['subject_id'] ?>" 
                               class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm">
                                <span>Menaxho</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>