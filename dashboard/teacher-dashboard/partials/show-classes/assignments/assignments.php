<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) { die('Sesion i pavlefshëm'); }

// Merr ID-në e mësuesit
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) { die('Mësuesi nuk u gjet'); }

// Merr detyrat dhe statistikat vetëm për këtë mësues
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE school_id = ? AND teacher_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$schoolId, $teacherId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Detyrat e Shtëpisë</h1>
            <p class="text-sm text-slate-500 font-medium">Menaxhoni ngarkesën mësimore për klasat tuaja.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
                <input type="text" id="assignmentSearch" placeholder="Kërko detyrë..." 
                       class="w-full sm:w-64 pl-4 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm">
            </div>
            <button id="addAssignmentBtn" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-all active:scale-95">
                + Krijo Detyrë
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Totali</p>
            <p class="text-2xl font-black text-slate-900 mt-1"><?= $total ?></p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm border-l-4 border-l-indigo-500">
            <p class="text-xs font-bold text-indigo-400 uppercase tracking-widest">Aktive</p>
            <p class="text-2xl font-black text-indigo-600 mt-1"><?= $active ?></p>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm border-l-4 border-l-emerald-500">
            <p class="text-xs font-bold text-emerald-400 uppercase tracking-widest">Përfunduara</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $completed ?></p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Detyra</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Përshkrimi</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-center">Afati</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right pr-10">Veprimi</th>
                    </tr>
                </thead>
                <tbody id="assignmentTableBody" class="divide-y divide-slate-100">
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-400 italic">Nuk keni asnjë detyrë të regjistruar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): ?>
                        <tr class="group hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="assignment-title text-sm font-bold text-slate-900"><?= htmlspecialchars($row['title']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-slate-500 truncate max-w-xs"><?= htmlspecialchars($row['description']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block px-3 py-1 bg-blue-50 text-blue-600 text-[11px] font-bold rounded-lg border border-blue-100">
                                    <?= date('d/m/Y', strtotime($row['due_date'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right pr-10">
                                <button type="button" class="deleteAssignment p-2 text-slate-300 hover:text-rose-600 transition-colors" data-id="<?= (int)$row['id'] ?>">
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
// Live Search & Highlight
document.getElementById('assignmentSearch').addEventListener('input', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#assignmentTableBody tr');

    rows.forEach(row => {
        let titleEl = row.querySelector('.assignment-title');
        if(!titleEl) return;
        
        let text = titleEl.textContent;
        if (text.toLowerCase().includes(filter)) {
            row.style.display = "";
            if(filter) {
                let regex = new RegExp(`(${filter})`, "gi");
                titleEl.innerHTML = text.replace(regex, `<mark class="bg-yellow-200 text-slate-900 rounded px-0.5">$1</mark>`);
            } else {
                titleEl.innerHTML = text;
            }
        } else {
            row.style.display = "none";
        }
    });
});

// Modal Logic
const addBtn = document.getElementById('addAssignmentBtn');
const formSection = document.getElementById('addSchoolForm'); // Sigurohu që ID përputhet me form.php
const cancelBtn = document.getElementById('cancel');

addBtn?.addEventListener('click', () => {
    formSection.classList.remove('hidden');
    formSection.scrollIntoView({ behavior: 'smooth' });
});

cancelBtn?.addEventListener('click', () => formSection.classList.add('hidden'));

// AJAX Delete
document.addEventListener('click', function (e) {
    const dBtn = e.target.closest('.deleteAssignment');
    if (!dBtn) return;

    if (!confirm('A jeni i sigurt?')) return;

    const id = dBtn.dataset.id;
    fetch('/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/delete_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            dBtn.closest('tr').remove();
        } else {
            alert(data.message);
        }
    })
    .catch(() => alert('Gabim në server'));
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php'; 
?>