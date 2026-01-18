<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) die("Aksesi i ndaluar.");

// Fetch all classes for the school
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade ASC");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Orari i Mësimit</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni orarin javor për çdo klasë të shkollës suaj.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button id="addScheduleBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Shto Orë Mësimi
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <script>
        // This calls the JavaScript function we defined in schedule.php
        window.addEventListener('DOMContentLoaded', (event) => {
            showToast("<?= addslashes($_SESSION['success']) ?>", "success");
        });
    </script>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[600px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[70%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Klasa dhe Viti Akademik</th>
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right">Veprime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach($classes as $row): ?>
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-bold">
                                    <?= htmlspecialchars($row['grade']) ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">Klasa <?= htmlspecialchars($row['grade']) ?></div>
                                    <div class="text-xs text-slate-500 italic"><?= htmlspecialchars($row['academic_year'] ?? 'Viti aktual') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="toggleSchedule(<?= $row['id'] ?>)" 
                                    class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 px-4 py-2 rounded-xl transition">
                                <span>Shiko Orarin</span>
                                <svg id="icon-<?= $row['id'] ?>" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr id="sched-row-<?= $row['id'] ?>" class="hidden bg-slate-50/50 dark:bg-black/20 overflow-hidden">
                        <td colspan="2" class="p-0 border-none">
                            <div class="px-6 py-8 border-t border-slate-200 dark:border-white/5">
                                <div id="grid-container-<?= $row['id'] ?>" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-white/10 min-h-[200px] flex items-center justify-center">
                                     <div class="flex flex-col items-center gap-3 text-slate-400">
                                         <div class="animate-spin h-6 w-6 border-2 border-indigo-500 border-t-transparent rounded-full"></div>
                                         <span class="text-xs font-medium uppercase tracking-widest">Duke ngarkuar të dhënat...</span>
                                     </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

<?php require_once 'form.php'; ?>

<script>
// --- TOAST NOTIFICATIONS ---
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const isSuccess = type === 'success';
    toast.className = `${isSuccess ? 'bg-emerald-600' : 'bg-rose-600'} text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-medium transform transition-all duration-300 translate-y-10 opacity-0`;
    toast.innerHTML = `${isSuccess ? '✓' : '✕'} <span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Check for PHP session success messages
<?php if (isset($_SESSION['success'])): ?>
    showToast("<?= addslashes($_SESSION['success']) ?>");
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

async function toggleSchedule(classId) {
    const row = document.getElementById(`sched-row-${classId}`);
    const container = document.getElementById(`grid-container-${classId}`);
    const icon = document.getElementById(`icon-${classId}`);
    
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        icon.classList.add('rotate-180');
        
        try {
            const res = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            if (!res.ok) throw new Error();
            container.innerHTML = await res.text();
            container.classList.remove('flex', 'items-center', 'justify-center'); // Remove spinner centering
        } catch (err) {
            container.innerHTML = `<div class="text-rose-500 text-sm font-medium">Dështoi ngarkimi i orarit. Ju lutem provoni përsëri.</div>`;
        }
    } else {
        row.classList.add('hidden');
        icon.classList.remove('rotate-180');
    }
}

async function deleteScheduleEntry(id, classId) {
    if(!confirm('A jeni të sigurt që dëshironi të fshini këtë orë mësimi?')) return;
    
    try {
        const res = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/delete-entry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        
        if(data.success) {
            showToast("Ora e mësimit u fshi me sukses.");
            // Refresh grid only
            const refresh = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            document.getElementById(`grid-container-${classId}`).innerHTML = await refresh.text();
        } else {
            showToast("Gabim: " + (data.message || "Fshirja dështoi"), "error");
        }
    } catch (err) { 
        showToast("Gabim gjatë komunikimit me serverin.", "error"); 
    }
}
const btn = document.getElementById('addScheduleBtn');
const form = document.getElementById('addScheduleForm');
const cancel = document.getElementById('closeModal');

btn?.addEventListener('click', () => {
 form.classList.remove('hidden');
 form.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

cancel?.addEventListener('click', () => {
 form.classList.add('hidden');
});

function autoSelectSubject() {
    const teacher = document.getElementById('f_teacher');
    const subject = document.getElementById('f_subject');
    const subId = teacher.options[teacher.selectedIndex].getAttribute('data-sub');
    if(subId) {
        subject.value = subId;
    }
}
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>