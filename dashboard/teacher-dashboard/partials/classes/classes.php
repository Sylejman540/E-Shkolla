<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null; 
if (!$teacherId) {
    die("Teacher ID not found in session.");
}

require_once __DIR__ . '/../../../../db.php';

// --- LOGJIKA E PAGINATION ---
$limit = 10; // Numri i klasave për faqe
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Numërimi total për pagination
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class WHERE teacher_id = ?");
$totalStmt->execute([$teacherId]);
$totalItems = $totalStmt->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// SQL Query origjinal me shtimin e LIMIT/OFFSET
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
    LIMIT $limit OFFSET $offset
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
        <div class="mt-4 md:mt-0 relative">
            <input type="text" id="classSearch" placeholder="Kërko klasën ose lëndën..." 
                class="block w-full md:w-80 px-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all text-sm">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="bg-white overflow-hidden shadow-sm border border-slate-100 rounded-2xl p-5">
            <dt class="text-sm font-medium text-slate-500 truncate">Total Klasa</dt>
            <dd class="mt-1 text-3xl font-semibold text-blue-600"><?= $totalItems ?></dd>
        </div>
    </div>

    <div class="bg-white border border-slate-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200" id="classesTable">
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
                <tbody class="divide-y divide-slate-100 bg-white" id="tableBody">
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-slate-500 italic">
                                Nuk u gjet asnjë klasë e caktuar.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($classes as $row): ?>
                    <tr class="hover:bg-blue-50/30 transition-colors group class-row">
                        <td class="whitespace-nowrap py-5 pl-6 pr-3">
                            <div class="flex items-center">
                                <div class="h-11 w-11 flex-shrink-0 flex items-center justify-center rounded-xl bg-blue-600 text-white font-bold shadow-sm shadow-blue-200">
                                    <?= htmlspecialchars($row['class_name']) ?>
                                </div>
                                <div class="ml-4">
                                    <div class="font-bold text-slate-900 searchable-class"><?= htmlspecialchars($row['class_name']) ?></div>
                                    <div class="sm:hidden text-slate-500 text-xs mt-0.5 searchable-subject">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell whitespace-nowrap px-3 py-5">
                            <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10 searchable-subject">
                                <?= htmlspecialchars($row['subject_name']) ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap px-3 py-5 text-sm text-slate-600">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A9 9 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
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

        <?php if ($totalPages > 1): ?>
        <div class="bg-white border-t border-slate-100 px-6 py-4 flex items-center justify-between">
            <div class="text-sm text-slate-500">
                Faqja <span class="font-bold text-slate-900"><?= $page ?></span> nga <span class="font-bold text-slate-900"><?= $totalPages ?></span>
            </div>
            <div class="inline-flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-sm">Para</a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-sm">Pas</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// --- LIVE SEARCH ---
document.getElementById('classSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('.class-row');

    rows.forEach(row => {
        const className = row.querySelector('.searchable-class').innerText.toLowerCase();
        const subjectName = row.querySelector('.searchable-subject').innerText.toLowerCase();

        if (className.includes(filter) || subjectName.includes(filter)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>