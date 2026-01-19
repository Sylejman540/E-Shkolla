<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) { die('Sesion i pavlefshëm'); }

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) { die('Mësuesi nuk u gjet'); }

// Fetch Assignments
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE school_id = ? AND teacher_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$schoolId, $teacherId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed
    FROM assignments
    WHERE school_id = ? AND teacher_id = ?
");
$stmt->execute([$schoolId, $teacherId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total     = (int) ($stats['total'] ?? 0);
$active    = (int) ($stats['active'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);

ob_start();
?>

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Detyrat e Shtëpisë</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Menaxhoni ngarkesën mësimore për klasat tuaja.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative group">
                <input type="text" id="assignmentSearch" placeholder="Kërko detyrë..." 
                       class="w-full sm:w-64 pl-10 pr-4 py-2.5 bg-white dark:bg-gray-900 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm dark:text-white">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
            <button id="addAssignmentBtn" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700 transition-all active:scale-95">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Krijo Detyrë
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-slate-200 dark:border-white/10 shadow-sm group">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Totali i Detyrave</p>
            <p class="text-3xl font-black text-slate-900 dark:text-white mt-2"><?= $total ?></p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-slate-200 dark:border-white/10 shadow-sm border-l-4 border-l-indigo-500">
            <p class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-[0.2em]">Detyra Aktive</p>
            <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-2"><?= $active ?></p>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-slate-200 dark:border-white/10 shadow-sm border-l-4 border-l-emerald-500">
            <p class="text-[10px] font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-[0.2em]">Të Përfunduara</p>
            <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2"><?= $completed ?></p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[800px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Titulli i Detyrës</th>
                        <th class="w-[40%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Përshkrimi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Afati Kohor</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right pr-10">Veprimet</th>
                    </tr>
                </thead>
                <tbody id="assignmentTableBody" class="divide-y divide-slate-100 dark:divide-white/5">
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">Nuk keni asnjë detyrë të regjistruar deri më tani.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): ?>
                        <tr class="group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-6 py-5 whitespace-nowrap overflow-hidden">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-indigo-50 dark:bg-indigo-500/10 rounded-lg text-indigo-600 dark:text-indigo-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                    <span class="assignment-title text-sm font-bold text-slate-900 dark:text-white truncate" data-original="<?= htmlspecialchars($row['title']) ?>">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-sm text-slate-500 dark:text-slate-400 truncate italic">
                                    <?= htmlspecialchars($row['description']) ?>
                                </p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="inline-flex items-center px-3 py-1 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 text-[10px] font-black rounded-lg border border-blue-100 dark:border-blue-500/20 uppercase">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <?= date('d/m/Y', strtotime($row['due_date'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right pr-10">
                                <button type="button" class="deleteAssignment p-2 text-slate-300 dark:text-slate-600 hover:text-rose-600 dark:hover:text-rose-500 transition-all transform hover:scale-110" data-id="<?= (int)$row['id'] ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php require_once 'form.php'; ?>
</div>

<script>
// Search with Indigo Highlight
document.getElementById('assignmentSearch').addEventListener('input', function() {
    let filter = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll('#assignmentTableBody tr');

    rows.forEach(row => {
        let titleEl = row.querySelector('.assignment-title');
        if(!titleEl) return;
        
        let text = titleEl.getAttribute('data-original');
        if (text.toLowerCase().includes(filter)) {
            row.style.display = "";
            if(filter !== "") {
                let regex = new RegExp(`(${filter})`, "gi");
                titleEl.innerHTML = text.replace(regex, `<mark class="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/40 dark:text-white rounded px-0.5">$1</mark>`);
            } else {
                titleEl.innerHTML = text;
            }
        } else {
            row.style.display = "none";
        }
    });
});

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

    if (!confirm('A jeni i sigurt që dëshironi të fshini këtë detyrë?')) return;

    const id = dBtn.dataset.id;
    const row = dBtn.closest('tr');
    
    // Add a fading effect before deletion
    row.style.opacity = '0.5';
    row.style.pointerEvents = 'none';

    fetch('/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/delete_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            row.style.transform = 'translateX(20px)';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            row.style.opacity = '1';
            row.style.pointerEvents = 'auto';
            alert(data.message);
        }
    })
    .catch(() => {
        row.style.opacity = '1';
        row.style.pointerEvents = 'auto';
        alert('Gabim në server gjatë fshirjes.');
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php'; 
?>