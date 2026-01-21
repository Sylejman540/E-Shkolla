<?php
/* =====================================================
   SESSION & DB
===================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/*
|--------------------------------------------------------------------------
| IMPORTANT IDS (THIS IS THE FIX)
|--------------------------------------------------------------------------
| classes.class_header  -> users.id
| teacher_class.teacher_id -> teachers.id
*/
$userId    = $_SESSION['user']['id'] ?? null;          // users.id
$teacherId = $_SESSION['user']['teacher_id'] ?? null; // teachers.id
$schoolId  = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$teacherId || !$schoolId) {
    die('Invalid session.');
}

/* =====================================================
   1. GET PINNED (HEADER / KUJDESTAR) CLASS
   SOURCE: classes (class_header = users.id)
===================================================== */
$headerStmt = $pdo->prepare("
    SELECT 
        id AS class_id,
        grade AS class_name,
        max_students
    FROM classes
    WHERE class_header = ?
      AND school_id = ?
      AND status = 'active'
    LIMIT 1
");
$headerStmt->execute([$userId, $schoolId]);

$pinnedClass = $headerStmt->fetch(PDO::FETCH_ASSOC);

/* =====================================================
   2. PAGINATION (ONLY SUBJECT CLASSES)
   SOURCE: teacher_class (teacher_id = teachers.id)
===================================================== */
$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM teacher_class tc
    INNER JOIN classes c ON c.id = tc.class_id
    WHERE tc.teacher_id = ?
      AND c.school_id = ?
      AND c.status = 'active'
");
$totalStmt->execute([$teacherId, $schoolId]);

$totalItems = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($totalItems / $limit);

/* =====================================================
   3. GET ALL REGULAR CLASSES
   SOURCE: teacher_class
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        tc.class_id,
        tc.subject_id,
        c.grade AS class_name,
        c.max_students,
        s.subject_name
    FROM teacher_class tc
    INNER JOIN classes  c ON c.id = tc.class_id
    INNER JOIN subjects s ON s.id = tc.subject_id
    WHERE tc.teacher_id = ?
      AND c.school_id = ?
      AND c.status = 'active'
    ORDER BY c.grade ASC, s.subject_name ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$teacherId, $schoolId]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   4. RENDER
===================================================== */
ob_start();
?>

<div class="space-y-8">
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl tracking-tight">Klasat e Mia</h1>
            <p class="mt-1 text-sm text-slate-500">Menaxhoni nxënësit dhe notat për klasat tuaja.</p>
        </div>
        <div class="mt-4 md:mt-0 relative">
            <input type="text" id="classSearch" placeholder="Kërko klasën ose lëndën..."
                class="block w-full md:w-80 px-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
        </div>
    </div>

    <div class="bg-white border border-slate-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200" id="classesTable">
                <thead class="bg-slate-50/50">
                    <tr>
                        <th class="py-4 pl-6 pr-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Klasa</th>
                        <th class="hidden sm:table-cell px-3 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Lënda</th>
                        <th class="hidden md:table-cell px-3 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Nxënësit</th>
                        <th class="relative py-4 pl-3 pr-6 text-right"><span class="sr-only">Veprimi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white" id="tableBody">

<?php if ($pinnedClass): ?>
                    <tr class="bg-blue-50/50 transition-colors group class-row border-l-4 border-blue-600">
                        <td class="whitespace-nowrap py-5 pl-6 pr-3">
                            <div class="flex items-center">
                                <div class="h-11 w-11 flex-shrink-0 flex items-center justify-center rounded-xl bg-blue-600 text-white font-bold shadow-sm">
                                    <?= htmlspecialchars($pinnedClass['class_name']) ?>
                                </div>
                                <div class="ml-4">
                                    <div class="font-bold text-slate-900 searchable-class"><?= htmlspecialchars($pinnedClass['class_name']) ?></div>
                                    <div class="text-blue-600 text-[10px] font-black uppercase tracking-widest">Kujdestari (Pinned)</div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell whitespace-nowrap px-3 py-5">
                            <span class="inline-flex items-center rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-slate-700 ring-1 ring-inset ring-slate-200 searchable-subject">
                                Menaxhimi i Klasës
                            </span>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap px-3 py-5 text-sm text-slate-600">
                            <?= (int)$pinnedClass['max_students'] ?> Studentë
                        </td>
                        <td class="whitespace-nowrap py-5 pl-3 pr-6 text-right text-sm font-medium">
                            <a href="/E-Shkolla/show-classes?class_id=<?= (int)$pinnedClass['class_id'] ?>&type=header"
                               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition-all shadow-sm">
                                <span>Menaxho</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </td>
                    </tr>
<?php endif; ?>

<?php foreach ($classes as $row): ?>
                    <tr class="hover:bg-slate-50 transition-colors group class-row">
                        <td class="whitespace-nowrap py-5 pl-6 pr-3">
                            <div class="flex items-center">
                                <div class="h-11 w-11 flex-shrink-0 flex items-center justify-center rounded-xl bg-slate-100 text-slate-600 font-bold group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                                    <?= htmlspecialchars($row['class_name']) ?>
                                </div>
                                <div class="ml-4">
                                    <div class="font-bold text-slate-900 searchable-class"><?= htmlspecialchars($row['class_name']) ?></div>
                                    <div class="sm:hidden text-slate-500 text-xs mt-0.5 searchable-subject"><?= htmlspecialchars($row['subject_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell whitespace-nowrap px-3 py-5">
                            <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-700/10 searchable-subject">
                                <?= htmlspecialchars($row['subject_name']) ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap px-3 py-5 text-sm text-slate-600">
                            <?= (int)$row['max_students'] ?> Studentë
                        </td>
                        <td class="whitespace-nowrap py-5 pl-3 pr-6 text-right text-sm font-medium">
                            <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>&subject_id=<?= (int)$row['subject_id'] ?>"
                               class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm">
                                <span>Menaxho</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path d="M9 5l7 7-7 7" />
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

<script>
document.getElementById('classSearch').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.class-row').forEach(row => {
        const cls = row.querySelector('.searchable-class')?.innerText.toLowerCase() || '';
        const sub = row.querySelector('.searchable-subject')?.innerText.toLowerCase() || '';
        row.style.display = (cls.includes(filter) || sub.includes(filter)) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
