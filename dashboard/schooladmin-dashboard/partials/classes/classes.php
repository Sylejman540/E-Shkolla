<?php 
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.academic_year,
        c.grade,
        c.max_students,
        c.status,
        u.name AS class_header_name
    FROM classes c
    LEFT JOIN users u ON u.id = c.class_header
    WHERE c.school_id = ?
    ORDER BY c.created_at DESC
");

$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total for pagination
$totalRowsCount = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$totalRowsCount->execute([$schoolId]);
$totalRecords = $totalRowsCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Sliding Pagination Range
$range = [];
if ($totalPages <= 7) {
    $range = range(1, $totalPages);
} else {
    if ($page <= 4) { $range = [1, 2, 3, 4, 5, '...', $totalPages]; }
    elseif ($page > $totalPages - 4) { $range = [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages]; }
    else { $range = [1, '...', $page - 1, $page, $page + 1, '...', $totalPages]; }
}

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Klasat e Shkollës</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni vitet akademike, klasat dhe kapacitetin e nxënësve.</p>
        </div>
        <div class="flex gap-3 md:mt-0 mt-4">
        <div class="mt-4 sm:mt-0">
            <button type="button" id="addClassBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Shto Klasë
            </button>
        </div>
        <div class="flex gap-3 md:mt-0 mt-4">
            <a href="/E-Shkolla/classes-csv"
            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 dark:bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v7m0 0l-3-3m3 3l3-3M12 3v9"/>
                </svg>
                Import CSV
            </a>
        </div>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center justify-between">
        <div class="relative w-full max-w-xs">
            <input id="liveSearch" type="text" placeholder="Kërko me vit, klasë ose mësues..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition" oninput="filterClasses()">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
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
            <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[20%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Viti Akademik</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Klasa</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Kapaciteti Max</th>
                        <th class="w-[25%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Mësuesi Kujdestar</th>
                        <th class="w-[12%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Statusi</th>
                        <th class="w-[13%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right">Orari</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($classes as $row): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap overflow-hidden">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white" data-original="<?= htmlspecialchars($row['academic_year']) ?>">
                                <?= htmlspecialchars($row['academic_year']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap overflow-hidden">
                            <span contenteditable class="editable block text-sm text-slate-600 dark:text-slate-300 outline-none font-medium truncate px-1 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" 
                                  data-id="<?= $row['id'] ?>" data-field="grade" data-original="<?= htmlspecialchars($row['grade']) ?>">
                                <?= htmlspecialchars($row['grade']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span contenteditable class="editable inline-block text-sm text-slate-600 dark:text-slate-400 outline-none px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-white/10" 
                                  data-id="<?= $row['id'] ?>" data-field="max_students" data-original="<?= htmlspecialchars($row['max_students']) ?>">
                                <?= htmlspecialchars($row['max_students']) ?>
                            </span>
                        </td>
<td class="px-4 py-3 text-sm text-gray-700">
    <?php if (!empty($row['class_header_name'])): ?>
        <?= htmlspecialchars($row['class_header_name'], ENT_QUOTES, 'UTF-8') ?>
    <?php else: ?>
        <span class="italic text-gray-400">I pacaktuar</span>
    <?php endif; ?>
</td>

                        <td class="px-6 py-4 text-center">
                            <button class="status-toggle px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' ?>" 
                                    data-id="<?= $row['id'] ?>" data-field="status" data-value="<?= $row['status'] ?>" data-original="<?= $row['status'] ?>">
                                <?= $row['status'] ?>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="/E-Shkolla/schedule?class_id=<?= $row['id'] ?>" 
                               class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-bold transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                SHIKO ORARIN
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 bg-slate-50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-center">
            <nav class="flex items-center gap-1">
                <a href="?page=<?= max(1, $page - 1) ?>" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-gray-800 transition <?= $page <= 1 ? 'pointer-events-none opacity-30' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <?php foreach ($range as $p): ?>
                    <?php if ($p === '...'): ?>
                        <span class="px-3 py-1 text-slate-400">...</span>
                    <?php else: ?>
                        <a href="?page=<?= $p ?>" class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $p == $page ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-gray-800' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?page=<?= min($totalPages, $page + 1) ?>" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-gray-800 transition <?= $page >= $totalPages ? 'pointer-events-none opacity-30' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="statusModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-sm bg-white dark:bg-gray-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-white/10 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 mb-4 text-indigo-600">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ndrysho Statusin e Klasës?</h3>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Çaktivizimi mund të kufizojë qasjen në këtë grup akademik.</p>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl dark:bg-gray-800 dark:text-slate-300 transition">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-500 transition shadow-lg shadow-indigo-500/30">Vazhdo</button>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

<?php require_once 'form.php'; ?>

<script>
const API_URL = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/update-inline.php';
let pendingStatusChange = null;

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

// --- CORE SAVE LOGIC ---
async function save(el, forcedValue = null) {
    const classId = el.dataset.id;
    const field   = el.dataset.field;
    const oldValue = el.getAttribute('data-original') || "";
    const newValue = forcedValue !== null ? forcedValue : el.innerText.trim();

    if (newValue === oldValue) return;

    el.classList.add('opacity-40', 'pointer-events-none');

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ classId, field, value: newValue })
        });
        const result = await response.json();

        if (result.status === 'success') {
            el.setAttribute('data-original', newValue);
            if (el.classList.contains('status-toggle')) updateStatusUI(el, newValue);
            showToast('Të dhënat u përditësuan!');
        } else { throw new Error(result.message); }
    } catch (err) {
        el.innerText = oldValue;
        showToast(err.message || 'Gabim!', 'error');
    } finally { el.classList.remove('opacity-40', 'pointer-events-none'); }
}

function updateStatusUI(btn, value) {
    btn.dataset.value = value;
    btn.innerText = value.charAt(0).toUpperCase() + value.slice(1);
    const active = value === 'active';
    btn.className = `status-toggle px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all ${active ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'}`;
}

// --- EVENT LISTENERS ---
document.addEventListener('click', e => {
    if (e.target.classList.contains('status-toggle')) {
        pendingStatusChange = { btn: e.target, newStatus: e.target.dataset.value === 'active' ? 'inactive' : 'active' };
        document.getElementById('statusModal').classList.remove('hidden');
    }
});

document.getElementById('confirmStatus').onclick = () => {
    if (pendingStatusChange) save(pendingStatusChange.btn, pendingStatusChange.newStatus);
    document.getElementById('statusModal').classList.add('hidden');
};
document.getElementById('cancelStatus').onclick = () => document.getElementById('statusModal').classList.add('hidden');

document.addEventListener('focusout', e => { if (e.target.classList.contains('editable')) save(e.target); });
document.addEventListener('keydown', e => { if (e.target.classList.contains('editable') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); } });

// --- SEARCH & HIGHLIGHTING ---
function filterClasses() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    const rows = document.querySelectorAll("tbody tr");
    rows.forEach(row => {
        const searchables = row.querySelectorAll('[data-original]');
        let match = false;
        searchables.forEach(el => {
            const txt = el.getAttribute('data-original');
            if (filter === "") { el.innerHTML = txt; match = true; }
            else if (txt.toLowerCase().includes(filter)) {
                el.innerHTML = txt.replace(new RegExp(`(${filter})`, 'gi'), '<mark class="bg-yellow-200 dark:bg-yellow-500/40 text-current rounded-sm px-0.5">$1</mark>');
                match = true;
            } else { el.innerHTML = txt; }
        });
        row.style.display = match ? "" : "none";
    });
}

const btn = document.getElementById('addClassBtn');
const form = document.getElementById('addClassForm');
const cancel = document.getElementById('cancel');

btn?.addEventListener('click', () => {
 form.classList.remove('hidden');
 form.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

cancel?.addEventListener('click', () => {
 form.classList.add('hidden');
});

</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>