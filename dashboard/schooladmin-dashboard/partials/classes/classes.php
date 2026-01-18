<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Pagination
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Marrim klasat bashkë me emrin e mësuesit kujdestar (nëse ka)
$stmt = $pdo->prepare("
    SELECT c.*, t.name as teacher_name 
    FROM classes c 
    LEFT JOIN teachers t ON c.user_id = t.user_id 
    WHERE c.school_id = ? 
    ORDER BY c.grade ASC, c.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRowsCount = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$totalRowsCount->execute([$schoolId]);
$totalRecords = $totalRowsCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="mt-5 sm:flex-auto">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Klasat e Shkollës
            </h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                Menaxhoni vitet akademike, klasat dhe kapacitetin e nxënësve.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button type="button" id="addSchoolBtn" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 transition">
                Shto Klasë
            </button>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input id="liveSearch" type="text" placeholder="Kërko klasat..." 
                   class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none"
                   oninput="filterClasses()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <button onclick="document.getElementById('liveSearch').value=''; filterClasses();" class="text-xs text-gray-500 hover:text-indigo-600 transition">Pastro</button>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Viti Akademik</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Klasa</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Nr. i Nxënësve Max</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Mësuesi Kujdestar</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                            <th class="relative py-3.5 pl-3 pr-4 sm:pr-0 text-right text-sm font-semibold">Orari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        <?php if(!empty($classes)): ?>
                            <?php foreach ($classes as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white sm:pl-0">
                                    <span class="px-2 py-1"><?= htmlspecialchars($row['academic_year']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span contenteditable class="editable px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['id'] ?>" data-field="grade"><?= htmlspecialchars($row['grade']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span contenteditable class="editable px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['id'] ?>" data-field="max_students"><?= htmlspecialchars($row['max_students']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400 font-medium">
                                    <?= $row['teacher_name'] ? htmlspecialchars($row['teacher_name']) : '<span class="italic text-gray-400 text-xs">I pacaktuar</span>' ?>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>" 
                                            data-id="<?= $row['id'] ?>" data-field="status" data-value="<?= $row['status'] ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </button>
                                </td>
                                <td class="py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                    <a href="/E-Shkolla/schedule?class_id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">Shiko orarin</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="py-10 text-center text-gray-500">Nuk ka klasa të regjistruara.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-white/10 px-4 py-3 sm:px-6">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-gray-700 dark:text-gray-400">Duke treguar <span class="font-medium"><?= $offset + 1 ?></span> deri në <span class="font-medium"><?= min($offset + $limit, $totalRecords) ?></span> nga <span class="font-medium"><?= $totalRecords ?></span> rezultate</p>
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $i === $page ? 'z-10 bg-indigo-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 dark:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <?php require_once 'form.php'; ?>
</div>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>

<script>
// Logic for Modal
const btn = document.getElementById('addSchoolBtn');
const form = document.getElementById('addClassForm');

btn?.addEventListener('click', () => form.classList.remove('hidden'));

// Inline Save
function saveClass(el, forcedValue = null) {
    const classId = el.dataset.id;
    const field  = el.dataset.field;
    let value = forcedValue !== null ? forcedValue : el.innerText.trim();

    fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/update-inline.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ classId, field, value })
    }).then(() => location.reload());
}

document.querySelectorAll('.editable').forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } });
    el.addEventListener('blur', () => saveClass(el));
});

document.querySelectorAll('.status-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const newStatus = btn.dataset.value === 'active' ? 'inactive' : 'active';
        saveClass(btn, newStatus);
    });
});

// Live Search & Highlighting
function filterClasses() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    const rows = document.querySelectorAll("tbody tr:not(.no-results)");
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const isMatch = text.includes(filter);
        row.style.display = isMatch ? "" : "none";
        if (isMatch) highlightMatch(row, filter);
    });
}

function highlightMatch(row, filter) {
    row.querySelectorAll(".editable, td:nth-child(1) span").forEach(span => {
        const originalText = span.getAttribute('data-original') || span.innerText;
        if (!span.getAttribute('data-original')) span.setAttribute('data-original', originalText);
        if (!filter) { span.innerText = originalText; return; }
        const regex = new RegExp(`(${filter.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "gi");
        span.innerHTML = originalText.replace(regex, "<mark class='bg-yellow-200 text-black rounded-sm'>$1</mark>");
    });
}
</script>