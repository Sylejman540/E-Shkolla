<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) die("Aksesi i ndaluar.");

// --- LOGJIKA E PAGINIMIT ---
$limit = 10; 
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$countStmt->execute([$schoolId]);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade ASC LIMIT $limit OFFSET $offset");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-6 py-8 bg-white dark:bg-gray-900 min-h-screen">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 dark:text-white tracking-tight">Orari i Mësimit</h1>
            <p class="text-sm text-slate-500 mt-1">Menaxhimi i orarit javor për klasat.</p>
        </div>
        <button id="addScheduleBtn" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-all shadow-sm active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"/></svg>
            Shto Orë të Re
        </button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse table-fixed">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                    <th class="w-[70%] px-6 py-4 text-[11px] font-semibold uppercase text-slate-400 tracking-wider">Klasa</th>
                    <th class="w-[30%] px-6 py-4 text-[11px] font-semibold uppercase text-slate-400 tracking-wider text-right">Veprimet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                <?php foreach($classes as $row): ?>
                <tr class="hover:bg-slate-50/30 dark:hover:bg-white/[0.02] transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-sm font-bold border border-indigo-100/50 dark:border-indigo-500/20">
                                <?= htmlspecialchars($row['grade']) ?>
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Klasa <?= htmlspecialchars($row['grade']) ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button onclick="toggleSchedule(<?= $row['id'] ?>)" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 inline-flex items-center gap-2">
                            <span>Shiko Orarin</span>
                            <svg id="icon-<?= $row['id'] ?>" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </td>
                </tr>
                <tr id="sched-row-<?= $row['id'] ?>" class="hidden">
                    <td colspan="2" class="p-0 bg-slate-50/30 dark:bg-black/10">
                        <div id="grid-container-<?= $row['id'] ?>" class="p-6 border-t border-slate-200 dark:border-white/5">
                            <div class="flex flex-col items-center py-4 text-slate-400 gap-2">
                                <div class="animate-spin h-5 w-5 border-2 border-indigo-500 border-t-transparent rounded-full"></div>
                                <span class="text-[10px] font-medium uppercase italic">Duke ngarkuar...</span>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-between">
            <p class="text-xs text-slate-500 font-medium">Faqja <?= $page ?> nga <?= $totalPages ?></p>
            <div class="inline-flex gap-2">
                <a href="?p=<?= max(1, $page - 1) ?>" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 <?= $page <= 1 ? 'opacity-30 pointer-events-none' : 'hover:bg-white' ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                <a href="?p=<?= min($totalPages, $page + 1) ?>" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-600 <?= $page >= $totalPages ? 'opacity-30 pointer-events-none' : 'hover:bg-white' ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110]"></div>

<?php 
// Përfshijmë formën këtu në fund
require_once 'form.php'; 
?>

<script>
// 1. Logjika e Modalit (Fix i bllokimit)
document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('addScheduleBtn');
    const modal = document.getElementById('addScheduleForm');
    const cancelBtn = document.getElementById('closeModal');

    if (addBtn && modal) {
        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        });
    }
});

// 2. Logjika e Orarit (Expand/Collapse)
async function toggleSchedule(classId) {
    const row = document.getElementById(`sched-row-${classId}`);
    const container = document.getElementById(`grid-container-${classId}`);
    const icon = document.getElementById(`icon-${classId}`);
    
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        icon.classList.add('rotate-180');
        try {
            const res = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            container.innerHTML = await res.text();
        } catch (err) {
            container.innerHTML = `<p class="text-xs text-red-500">Gabim gjatë ngarkimit.</p>`;
        }
    } else {
        row.classList.add('hidden');
        icon.classList.remove('rotate-180');
    }
}

// 3. Toast Function
function showToast(msg) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = "bg-slate-800 text-white px-6 py-3 rounded-xl shadow-lg text-sm font-medium mb-3 transition-all duration-300 transform translate-y-10";
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10'), 10);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }, 3000);
}
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>