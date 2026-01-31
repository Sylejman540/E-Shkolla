<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;

// --- SEARCH & PAGINATION LOGIC ---
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$whereClause = "WHERE c.school_id = :school_id";
if (!empty($search)) {
    // Kërkojmë edhe te emri i mësuesit (u.name)
    $whereClause .= " AND (c.academic_year LIKE :search_year OR c.grade LIKE :search_grade OR u.name LIKE :search_teacher)";
}

/* =====================================================
    KORRIGJIMI KRYESOR: SQL JOIN
    Lidhim classes (class_header) -> teachers (id) -> users (id)
===================================================== */
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.academic_year,
        c.grade,
        c.max_students,
        c.status,
        u.name AS class_header_name
    FROM classes c
    LEFT JOIN teachers t ON t.id = c.class_header
    LEFT JOIN users u ON u.id = t.user_id
    $whereClause
    ORDER BY CAST(SUBSTRING_INDEX(c.grade, '/', 1) AS UNSIGNED) ASC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset
);

$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($search)) {
    $searchVal = "%$search%";
    $stmt->bindValue(':search_year', $searchVal, PDO::PARAM_STR);
    $stmt->bindValue(':search_grade', $searchVal, PDO::PARAM_STR);
    $stmt->bindValue(':search_teacher', $searchVal, PDO::PARAM_STR);
}
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count Total i rregulluar me JOIN që të punojë kërkimi për mësuesin
$totalStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM classes c 
    LEFT JOIN teachers t ON t.id = c.class_header
    LEFT JOIN users u ON u.id = t.user_id 
    $whereClause
");
$totalStmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($search)) {
    $totalStmt->bindValue(':search_year', $searchVal, PDO::PARAM_STR);
    $totalStmt->bindValue(':search_grade', $searchVal, PDO::PARAM_STR);
    $totalStmt->bindValue(':search_teacher', $searchVal, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// ... pjesa tjetër e kodit (HTML/JS) mbetet e njëjtë ...

$range = ($totalPages <= 7) ? ($totalPages > 0 ? range(1, $totalPages) : []) : (($page <= 4) ? [1, 2, 3, 4, 5, '...', $totalPages] : (($page > $totalPages - 4) ? [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages] : [1, '...', $page - 1, $page, $page + 1, '...', $totalPages]));

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (!$isAjax) { ob_start(); }

function highlight($text, $search) {
    if (empty($search)) return htmlspecialchars($text ?? '');
    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark class="bg-yellow-200 dark:bg-yellow-500/40 text-current rounded-sm px-0.5">$1</mark>', htmlspecialchars($text ?? ''));
}
?>

<div id="classesTableContainer" class="px-4 sm:px-6 lg:px-8 py-8 transition-opacity duration-200">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Klasat e Shkollës</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni vitet akademike, klasat dhe kapacitetin e nxënësve.</p>
        </div>
        <div class="flex gap-3 md:mt-0 mt-4">
            <button type="button" id="addClassBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Shto Klasë
            </button>
            <a href="/E-Shkolla/classes-csv" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 dark:bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v7m0 0l-3-3m3 3l3-3M12 3v9"/></svg>
                Import CSV
            </a>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center justify-between">
        <div class="relative w-full max-w-xs flex items-center gap-2">
            <div class="relative flex-1">
                <input id="liveSearch" type="text" placeholder="Kërko me vit, klasë ose mësues..." 
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition" oninput="filterClasses()">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
            <?php if(!empty($search)): ?>
            <button onclick="clearSearch()" class="p-2.5 rounded-xl bg-slate-100 dark:bg-gray-800 text-slate-500 hover:text-rose-500 transition-colors shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="tableWrapper" class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden h-auto">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[920px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[20%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Viti Akademik</th>
                        <th class="w-[15%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Klasa</th>
                        <th class="w-[15%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Kapaciteti Max</th>
                        <th class="w-[25%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Mësuesi Kujdestar</th>
                        <th class="w-[12%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Statusi</th>
                        <th class="w-[13%] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right pr-6">Orari</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php if (empty($classes)): ?>
                    <tr><td colspan="6" class="px-6 py-20 text-center text-slate-500">Asnjë klasë nuk u gjet.</td></tr>
                    <?php else: foreach ($classes as $row): ?>
                    <tr class="h-[52px] hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-2 whitespace-nowrap overflow-hidden">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                <?= highlight($row['academic_year'], $search) ?>
                            </span>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap overflow-hidden">
                            <span contenteditable class="editable block text-sm text-slate-600 dark:text-slate-300 outline-none font-medium truncate" 
                                  data-id="<?= $row['id'] ?>" data-field="grade" data-original="<?= htmlspecialchars($row['grade']) ?>">
                                <?= highlight($row['grade'], $search) ?>
                            </span>
                        </td>
                        <td class="px-6 py-2 text-center">
                            <span contenteditable class="editable inline-block text-sm text-slate-600 dark:text-slate-400 outline-none" 
                                  data-id="<?= $row['id'] ?>" data-field="max_students" data-original="<?= htmlspecialchars($row['max_students']) ?>">
                                <?= htmlspecialchars($row['max_students']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            <span contenteditable class="editable block text-sm text-indigo-600 dark:text-indigo-400 outline-none font-medium truncate" 
                                  data-id="<?= $row['id'] ?>" data-field="class_header" data-original="<?= htmlspecialchars($row['class_header_name'] ?? 'I pacaktuar') ?>">
                                <?= highlight($row['class_header_name'] ?? 'I pacaktuar', $search) ?>
                            </span>
                        </td>
                        <td class="px-6 py-2 text-center">
                            <button class="status-toggle px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' ?>" 
                                    data-id="<?= $row['id'] ?>" data-field="status" data-value="<?= $row['status'] ?>" data-original="<?= $row['status'] ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </button>
                        </td>
                        <td class="px-6 py-2 text-right pr-6 whitespace-nowrap">
                            <a href="/E-Shkolla/schedule?class_id=<?= $row['id'] ?>" class="inline-flex items-center gap-1 text-[10px] text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-bold transition uppercase">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Orari
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-3 bg-slate-50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-center">
            <nav class="flex items-center gap-1">
                <?php foreach ($range as $p): ?>
                    <?php if ($p === '...'): ?>
                        <span class="px-3 py-1 text-slate-400">...</span>
                    <?php else: ?>
                        <a href="?search=<?=urlencode($search)?>&page=<?=$p?>" class="ajax-page-link px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $p == $page ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-gray-800' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isAjax): ?>
<div id="statusModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-sm bg-white dark:bg-gray-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-white/10 text-center">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ndrysho Statusin?</h3>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ky veprim mund të ndikojë në qasjen e mësuesve dhe nxënësve në këtë klasë.</p>
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
let searchTimeout;

// --- DEBOUNCED SEARCH LOGIC ---
function filterClasses() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const query = document.getElementById("liveSearch").value.trim();
        const url = new URL(window.location.href);
        if (query) url.searchParams.set('search', query);
        else url.searchParams.delete('search');
        url.searchParams.set('page', 1);
        loadTablePage(url.toString());
    }, 400); // 400ms delay like other tables
}

function clearSearch() {
    document.getElementById("liveSearch").value = "";
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    url.searchParams.set('page', 1);
    loadTablePage(url.toString());
}

async function loadTablePage(url) {
    const container = document.getElementById('classesTableContainer');
    const wrapper = document.getElementById('tableWrapper');
    if (wrapper) wrapper.style.minHeight = `${wrapper.offsetHeight}px`;
    container.style.opacity = '0.5';
    
    try {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTable = doc.getElementById('classesTableContainer');
        if (newTable) {
            container.innerHTML = newTable.innerHTML;
            window.history.pushState({}, '', url);
        }
    } catch (err) {
        showToast('Gabim gjatë ngarkimit', 'error');
    } finally {
        container.style.opacity = '1';
        if (wrapper) wrapper.style.minHeight = '0px';
    }
}

async function save(el, forcedValue = null) {
    const classId = el.dataset.id;
    const field  = el.dataset.field;
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
            showToast('Klasa u përditësua!');
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
    btn.className = `status-toggle px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all ${active ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'}`;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const isSuccess = type === 'success';
    toast.className = `${isSuccess ? 'bg-emerald-600' : 'bg-rose-600'} text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-medium transform transition-all duration-300 translate-y-10 opacity-0`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function checkUrlMessages() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('success') === '1') {
        showToast('Klasa u shtua me sukses!', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (params.get('error') === '1') {
        showToast('Gabim gjatë shtimit të klasës!', 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

document.addEventListener('DOMContentLoaded', checkUrlMessages);

document.addEventListener('click', e => {
    const link = e.target.closest('.ajax-page-link');
    if (link) { e.preventDefault(); loadTablePage(link.getAttribute('href')); }

    if (e.target.classList.contains('status-toggle')) {
        window.pendingStatus = { btn: e.target, newStatus: e.target.dataset.value === 'active' ? 'inactive' : 'active' };
        document.getElementById('statusModal').classList.remove('hidden');
    }

    const addBtn = e.target.closest('#addClassBtn');
    if (addBtn) {
        const form = document.getElementById('addClassForm');
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

document.getElementById('confirmStatus').onclick = () => {
    if (window.pendingStatus) save(window.pendingStatus.btn, window.pendingStatus.newStatus);
    document.getElementById('statusModal').classList.add('hidden');
};
document.getElementById('cancelStatus').onclick = () => document.getElementById('statusModal').classList.add('hidden');

document.addEventListener('focusout', e => { if (e.target.classList.contains('editable')) save(e.target); });
document.addEventListener('keydown', e => { if (e.target.classList.contains('editable') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); } });
</script>
<?php endif; ?>

<?php 
if (!$isAjax) {
    $content = ob_get_clean(); 
    require_once __DIR__ . '/../../index.php'; 
}
?>