<?php
/* =====================================================
   SESSION & DB
===================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

/* =====================================================
   AUTH & TEACHER CONTEXT
===================================================== */
$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) {
    die('Sesioni i pavlefshëm');
}

$stmt = $pdo->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ? AND school_id = ?
");
$stmt->execute([$userId, $schoolId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) {
    die('Mësuesi nuk u gjet');
}

/* =====================================================
   CLASS CONTEXT (FROM URL)
===================================================== */
$classId = isset($_GET['class_id']) && $_GET['class_id'] !== ''
    ? (int) $_GET['class_id']
    : null;

if (!$classId) {
    die('Klasa nuk është specifikuar.');
}

/* Optional security: make sure teacher teaches this class */
$check = $pdo->prepare("
    SELECT 1 
    FROM teacher_class 
    WHERE teacher_id = ? AND class_id = ?
");
$check->execute([$teacherId, $classId]);

if (!$check->fetchColumn()) {
    die('Nuk keni akses në këtë klasë.');
}

/* =====================================================
   DATA FETCHING
===================================================== */
$today = date('Y-m-d');

/* =====================================================
   AUTO-ARCHIVE ASSIGNMENTS (OVERDUE > 2 DAYS)
===================================================== */
$stmt = $pdo->prepare("
    UPDATE assignments
    SET status = 'inactive'
    WHERE school_id = ?
      AND teacher_id = ?
      AND class_id = ?
      AND status = 'active'
      AND due_date IS NOT NULL
      AND due_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
");

$stmt->execute([
    $schoolId,
    $teacherId,
    $classId
]);


/* -------- Assignments (ONLY THIS CLASS) -------- */
$stmt = $pdo->prepare("
    SELECT id, title, description, status, due_date, created_at
    FROM assignments
    WHERE school_id = ?
      AND teacher_id = ?
      AND class_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$schoolId, $teacherId, $classId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------- Stats (ONLY THIS CLASS) -------- */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' AND due_date >= ? THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'active' AND due_date < ? THEN 1 ELSE 0 END) AS overdue
    FROM assignments
    WHERE school_id = ?
      AND teacher_id = ?
      AND class_id = ?
");
$stmt->execute([$today, $today, $schoolId, $teacherId, $classId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/* =====================================================
   SAFE CAST
===================================================== */
$total     = (int) ($stats['total'] ?? 0);
$active    = (int) ($stats['active'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);
$overdue   = (int) ($stats['overdue'] ?? 0);

// ... [Keep your existing PHP logic at the top unchanged] ...
ob_start();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .homework-view { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
    .badge-label { letter-spacing: 0.05em; }
</style>

<div class="homework-view px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-slate-100 dark:border-slate-800 pb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">Detyrat e Shtëpisë</h1>
            <p class="text-[12px] text-slate-500 dark:text-slate-400">Menaxhoni ngarkesën mësimore dhe afatet kohore.</p>
        </div>
        <button id="addAssignmentBtn" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-[12px] font-bold text-white shadow-sm hover:bg-indigo-700 transition-all active:scale-95">
            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Krijo Detyrë
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-white/10 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest badge-label">Totali</p>
            <span class="mt-1 block text-2xl font-bold text-slate-800 dark:text-white"><?= $total ?></span>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-white/10 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest badge-label">Aktive</p>
            <span class="mt-1 block text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= $active ?></span>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-white/10 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest badge-label">Përfunduara</p>
            <span class="mt-1 block text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $completed ?></span>
        </div>

        <div class="bg-rose-50 dark:bg-rose-500/10 rounded-xl border border-rose-100 dark:border-rose-500/20 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-widest badge-label">Afat i kaluar</p>
            <span class="mt-1 block text-2xl font-bold text-rose-700 dark:text-rose-400"><?= $overdue ?></span>
        </div>
    </div>

    <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between items-center">
        <div class="relative w-full max-w-xs">
            <input id="assignmentSearch" type="text" placeholder="Kërko titullin..." 
                class="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-[13px] focus:ring-2 focus:ring-indigo-500 outline-none transition dark:text-white shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
        
        <div class="flex items-center gap-2 text-[11px] font-medium text-slate-500">
            <span>Rreshta:</span>
            <select id="rowsPerPage" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md py-0.5 px-1 cursor-pointer outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[800px]">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-white/5 border-b border-slate-100 dark:border-white/10">
                        <th class="w-[30%] px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Titulli</th>
                        <th class="w-[30%] px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Përshkrimi</th>
                        <th class="w-[12%] px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-center">Statusi</th>
                        <th class="w-[12%] px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-center">Afati</th>
                        <th class="w-[16%] px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-right pr-8">Veprimet</th>
                    </tr>
                </thead>
                <tbody id="assignmentTableBody" class="divide-y divide-slate-50 dark:divide-white/5">
                    <?php if (empty($assignments)): ?>
                        <tr id="emptyState"><td colspan="5" class="px-5 py-10 text-center text-slate-400 italic text-sm">Nuk keni asnjë detyrë.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): 
                            $isOverdue = ($row['due_date'] < $today && $row['status'] !== 'completed');
                        ?>
                        <tr class="assignment-row group transition-colors hover:bg-slate-50/50 dark:hover:bg-white/5">
                            <td class="px-5 py-3 overflow-hidden">
                                <div class="flex items-center gap-2">
                                    <div class="p-1.5 <?= $isOverdue ? 'text-rose-500' : 'text-indigo-500' ?> rounded-md">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                    <span class="assignment-title text-[13px] font-semibold text-slate-800 dark:text-slate-200 truncate" data-original="<?= htmlspecialchars($row['title']) ?>">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-[12px] text-slate-500 dark:text-slate-400 truncate italic"><?= htmlspecialchars($row['description']) ?></p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($isOverdue): ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-600 border border-rose-100 uppercase badge-label">Vonesë</span>
                                <?php elseif ($row['status'] === 'completed'): ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase badge-label">Kryer</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase badge-label">Aktiv</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400"><?= date('d/m/y', strtotime($row['due_date'])) ?></span>
                            </td>
                            <td class="px-5 py-3 text-right pr-8">
                                <button type="button" class="deleteAssignment p-1.5 text-slate-300 hover:text-rose-600 transition-colors" data-id="<?= (int)$row['id'] ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 bg-slate-50/50 dark:bg-white/5 border-t border-slate-100 dark:border-white/10 flex items-center justify-between">
            <p class="text-[10px] text-slate-400 font-medium" id="paginationInfo"></p>
            <div class="flex gap-1.5">
                <button id="prevPage" class="p-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition disabled:opacity-20 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2.5"/></svg>
                </button>
                <button id="nextPage" class="p-1 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition disabled:opacity-20 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                </button>
            </div>
        </div>
    </div>

    <?php require_once 'form.php'; ?>
</div>
 
<script>
let currentPage = 1;
let rowsPerPage = 5;
let filteredRows = [];

const tableBody = document.getElementById('assignmentTableBody');
const allRows = Array.from(tableBody.querySelectorAll('.assignment-row'));

function updatePagination() {
    const searchVal = document.getElementById('assignmentSearch').value.toLowerCase().trim();
    
    filteredRows = allRows.filter(row => {
        const titleEl = row.querySelector('.assignment-title');
        const text = titleEl.getAttribute('data-original');
        const isMatch = text.toLowerCase().includes(searchVal);
        
        if (searchVal && isMatch) {
            const regex = new RegExp(`(${searchVal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, "gi");
            titleEl.innerHTML = text.replace(regex, `<mark class="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/40 dark:text-white rounded px-0.5">$1</mark>`);
        } else {
            titleEl.innerHTML = text;
        }
        return isMatch;
    });

    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    allRows.forEach(row => row.style.display = 'none');
    filteredRows.slice(start, end).forEach(row => row.style.display = '');

    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
    document.getElementById('paginationInfo').innerText = `Duke shfaqur ${totalRows > 0 ? start + 1 : 0} deri në ${Math.min(end, totalRows)} nga ${totalRows} detyra`;
}

document.getElementById('assignmentSearch').addEventListener('input', () => { currentPage = 1; updatePagination(); });
document.getElementById('rowsPerPage').addEventListener('change', (e) => { rowsPerPage = parseInt(e.target.value); currentPage = 1; updatePagination(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; updatePagination(); } });
document.getElementById('nextPage').addEventListener('click', () => { if (currentPage < Math.ceil(filteredRows.length / rowsPerPage)) { currentPage++; updatePagination(); } });

// Modal UI Logic
const addBtn = document.getElementById('addAssignmentBtn');
const formSection = document.getElementById('addSchoolForm'); 
const cancelBtn = document.getElementById('cancel');

addBtn?.addEventListener('click', () => {
    formSection.classList.remove('hidden');
    formSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
cancelBtn?.addEventListener('click', () => formSection.classList.add('hidden'));

// Delete via AJAX
document.addEventListener('click', function (e) {
    const dBtn = e.target.closest('.deleteAssignment');
    if (!dBtn) return;
    if (!confirm('A jeni i sigurt?')) return;

    const id = dBtn.dataset.id;
    const row = dBtn.closest('tr');
    row.style.opacity = '0.5';

    fetch('/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/delete_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload(); 
        } else {
            row.style.opacity = '1';
            alert(data.message);
        }
    });
});

updatePagination();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php'; 
?>