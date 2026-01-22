<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

/* ================= AUTH & TEACHER FETCH ================= */
$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) { die('Sesioni i pavlefshëm'); }

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) { die('Mësuesi nuk u gjet'); }

/* ================= DATA FETCHING ================= */
$today = date('Y-m-d');

// Fetch Assignments
$stmt = $pdo->prepare("
    SELECT id, title, description, status, due_date, created_at 
    FROM assignments 
    WHERE school_id = ? AND teacher_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$schoolId, $teacherId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' AND due_date >= ? THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'active' AND due_date < ? THEN 1 ELSE 0 END) AS overdue
    FROM assignments
    WHERE school_id = ? AND teacher_id = ?
");
$stmt->execute([$today, $today, $schoolId, $teacherId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total     = (int) ($stats['total'] ?? 0);
$active    = (int) ($stats['active'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);
$overdue   = (int) ($stats['overdue'] ?? 0);

ob_start();
?>

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Detyrat e Shtëpisë</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Menaxhoni ngarkesën mësimore dhe afatet kohore.</p>
        </div>
        <button id="addAssignmentBtn" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700 transition-all active:scale-95">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Krijo Detyrë
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10 print:hidden">

        <!-- TOTAL -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-slate-400/20 text-4xl">📦</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Totali</p>
            <span class="mt-2 block text-3xl font-black text-slate-900 dark:text-white">
                <?= $total ?>
            </span>
        </div>

        <!-- ACTIVE -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-blue-500/20 text-4xl">⚡</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Aktive</p>
            <span class="mt-2 block text-3xl font-black text-blue-600 dark:text-blue-400">
                <?= $active ?>
            </span>
        </div>

        <!-- COMPLETED -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-emerald-500/20 text-4xl">✅</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Përfunduara</p>
            <span class="mt-2 block text-3xl font-black text-emerald-600 dark:text-emerald-400">
                <?= $completed ?>
            </span>
        </div>

        <!-- OVERDUE -->
        <div class="relative overflow-hidden bg-gradient-to-br from-rose-500 to-rose-600 rounded-2xl p-5 shadow-lg text-white">
            <div class="absolute right-4 top-4 text-white/30 text-4xl">⏰</div>
            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80">Afat i kaluar</p>
            <span class="mt-2 block text-3xl font-black">
                <?= $overdue ?>
            </span>
        </div>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="relative w-full max-w-xs">
            <input id="assignmentSearch" type="text" placeholder="Kërko titullin..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition dark:text-white">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
        
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <span>Rreshta:</span>
            <select id="rowsPerPage" class="bg-slate-100 dark:bg-gray-800 border-none rounded-lg py-1 px-2 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[25%] px-6 py-4 text-xs font-bold uppercase text-slate-500">Titulli</th>
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase text-slate-500">Përshkrimi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-center">Statusi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-center">Afati</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-right pr-10">Veprimet</th>
                    </tr>
                </thead>
                <tbody id="assignmentTableBody" class="divide-y divide-slate-100 dark:divide-white/5">
                    <?php if (empty($assignments)): ?>
                        <tr id="emptyState"><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">Nuk keni asnjë detyrë.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): 
                            $isOverdue = ($row['due_date'] < $today && $row['status'] !== 'completed');
                            $rowClass = $isOverdue ? 'bg-rose-50/30 dark:bg-rose-500/5' : '';
                        ?>
                        <tr class="assignment-row group transition-colors <?= $rowClass ?> hover:bg-slate-50/50 dark:hover:bg-white/5">
                            <td class="px-6 py-5 whitespace-nowrap overflow-hidden">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 <?= $isOverdue ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10' ?> rounded-lg">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                    <span class="assignment-title text-sm font-bold text-slate-900 dark:text-white truncate" data-original="<?= htmlspecialchars($row['title']) ?>">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-sm text-slate-500 dark:text-slate-400 truncate italic"><?= htmlspecialchars($row['description']) ?></p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php if ($isOverdue): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400 uppercase border border-rose-200 dark:border-rose-500/30">I Përfunduar</span>
                                <?php elseif ($row['status'] === 'completed'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 uppercase border border-emerald-200 dark:border-emerald-500/30">Përfunduar</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400 uppercase border border-blue-200 dark:border-blue-500/30">Aktiv</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300"><?= date('d/m/Y', strtotime($row['due_date'])) ?></span>
                            </td>
                            <td class="px-6 py-5 text-right pr-10">
                                <button type="button" class="deleteAssignment p-2 text-slate-300 hover:text-rose-600 transition-all transform hover:scale-110" data-id="<?= (int)$row['id'] ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-between">
            <p class="text-xs text-slate-500" id="paginationInfo"></p>
            <div class="flex gap-2">
                <button id="prevPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30">
                    <svg class="w-4 h-4 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="nextPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30">
                    <svg class="w-4 h-4 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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