<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;

// --- PAGINATION & SEARCH LOGIC ---
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$sqlLimit = (int)$limit;
$sqlOffset = (int)$offset;

$whereClause = "WHERE t.school_id = :school_id";
if (!empty($search)) {
    $whereClause .= " AND (u.name LIKE :search_name OR u.email LIKE :search_email OR t.subject_name LIKE :search_subject)";
}

// 1. Fetch Teachers + Ordered Classes
$stmt = $pdo->prepare("
    SELECT 
        t.*, 
        u.name, 
        u.email, 
        u.status, 
        u.created_at,
        (
            SELECT GROUP_CONCAT(
                c.grade 
                ORDER BY CAST(c.grade AS UNSIGNED) ASC 
                SEPARATOR ', '
            )
            FROM teacher_class tc
            JOIN classes c ON tc.class_id = c.id
            WHERE tc.teacher_id = t.id
        ) AS assigned_classes
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    $whereClause
    ORDER BY u.name ASC
    LIMIT $sqlLimit OFFSET $sqlOffset
");

$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($search)) {
    $searchVal = "%$search%";
    $stmt->bindValue(':search_name', $searchVal, PDO::PARAM_STR);
    $stmt->bindValue(':search_email', $searchVal, PDO::PARAM_STR);
    $stmt->bindValue(':search_subject', $searchVal, PDO::PARAM_STR);
}
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Count Total for Pagination
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id = t.user_id $whereClause");
$totalStmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
if (!empty($search)) {
    $totalStmt->bindValue(':search_name', $searchVal, PDO::PARAM_STR);
    $totalStmt->bindValue(':search_email', $searchVal, PDO::PARAM_STR);
    $totalStmt->bindValue(':search_subject', $searchVal, PDO::PARAM_STR);
}
$totalStmt->execute();
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$range = ($totalPages <= 7) ? ($totalPages > 0 ? range(1, $totalPages) : []) : (($page <= 4) ? [1, 2, 3, 4, 5, '...', $totalPages] : (($page > $totalPages - 4) ? [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages] : [1, '...', $page - 1, $page, $page + 1, '...', $totalPages]));

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (!$isAjax) { ob_start(); }

// --- HIGHLIGHT FUNCTION ---
function highlight($text, $search) {
    if (empty($search)) return htmlspecialchars($text);
    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark class="bg-yellow-200 dark:bg-yellow-500/40 text-current rounded-sm px-0.5">$1</mark>', htmlspecialchars($text));
}
?>

<div id="teacherTableContainer" class="px-4 sm:px-6 lg:px-8 py-8 transition-opacity duration-200">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Mësuesit e Shkollës</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni stafin akademik dhe të dhënat e tyre.</p>
        </div>
        <div class="flex gap-3 md:mt-0 mt-4">
            <button type="button" id="addTeacherBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Shto mësues
            </button>
            <a href="/E-Shkolla/csv" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 dark:bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v7m0 0l-3-3m3 3l3-3M12 3v9"/></svg>
                Import CSV
            </a>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center justify-between">
        <div class="relative w-full max-w-xs flex items-center gap-2">
            <div class="relative flex-1">
                <input id="liveSearch" type="text" placeholder="Kërko me emër, email ose lëndë..." 
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition" oninput="filterTeachers()">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
            <?php if(!empty($search)): ?>
            <button onclick="clearSearch()" class="p-2.5 rounded-xl bg-slate-100 dark:bg-gray-800 text-slate-500 hover:text-rose-500 transition-colors shadow-sm" title="Pastro kërkimin">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="tableWrapper" class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden h-auto">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[1000px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[220px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Mësuesi</th>
                        <th class="w-[220px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kontakt</th>
                        <th class="w-[100px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Gjinia</th>
                        <th class="w-[150px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Lënda</th>
                        <th class="w-[250px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Klasat</th>
                        <th class="w-[120px] px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right pr-10">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php if (empty($teachers)): ?>
                    <tr><td colspan="6" class="px-6 py-20 text-center text-slate-500">Asnjë mësues nuk u gjet.</td></tr>
                    <?php else: foreach ($teachers as $row): ?>
                    <tr class="h-[52px] hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-2 whitespace-nowrap overflow-hidden">
                            <div class="flex items-center gap-3">
                                <?php
                                $photo = $row['profile_photo'];
                                if (!$photo || !file_exists($_SERVER['DOCUMENT_ROOT'] . '/E-Shkolla/' . $photo)) {
                                    $photo = 'uploads/teachers/default-avatar.png';
                                }
                                ?>
                                <img src="/E-Shkolla/<?= htmlspecialchars($photo) ?>" class="w-8 h-8 flex-shrink-0 rounded-full object-cover ring-1 ring-slate-200 dark:ring-gray-800 shadow-sm"/>
                                <span contenteditable class="editable block text-sm font-semibold text-slate-900 dark:text-white outline-none truncate" data-id="<?= $row['user_id'] ?>" data-field="name" data-original="<?= htmlspecialchars($row['name']) ?>">
                                    <?= highlight($row['name'], $search) ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap overflow-hidden">
                            <div class="text-sm flex flex-col leading-tight">
                                <span contenteditable class="editable text-slate-600 dark:text-slate-300 outline-none truncate" data-id="<?= $row['user_id'] ?>" data-field="email" data-original="<?= htmlspecialchars($row['email']) ?>">
                                    <?= highlight($row['email'], $search) ?>
                                </span>
                                <span contenteditable class="editable text-[10px] text-slate-400 outline-none truncate" data-id="<?= $row['user_id'] ?>" data-field="phone" data-original="<?= htmlspecialchars($row['phone'] ?? 'Pa telefon') ?>">
                                    <?= htmlspecialchars($row['phone'] ?? 'Pa telefon') ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-2 text-center">
                            <select class="editable-select rounded-lg px-2 py-0.5 text-xs border-none bg-slate-100 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-indigo-500 outline-none" data-id="<?= $row['user_id'] ?>" data-field="gender" data-original="<?= $row['gender'] ?>">
                                <option value="male" <?= $row['gender'] === 'male' ? 'selected' : '' ?>>M</option>
                                <option value="female" <?= $row['gender'] === 'female' ? 'selected' : '' ?>>F</option>
                            </select>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap overflow-hidden">
                            <span contenteditable class="editable text-sm text-indigo-600 dark:text-indigo-400 font-medium outline-none truncate" data-id="<?= $row['user_id'] ?>" data-field="subject_name" data-original="<?= htmlspecialchars($row['subject_name']) ?>">
                                <?= highlight($row['subject_name'], $search) ?>
                            </span>
                        </td>
                        <td class="px-6 py-2">
                            <div class="flex flex-nowrap gap-1 overflow-x-auto no-scrollbar">
                                <?php if($row['assigned_classes']): 
                                    foreach(explode(', ', $row['assigned_classes']) as $cls): ?>
                                    <span class="flex-shrink-0 px-1.5 py-0.5 bg-slate-100 dark:bg-gray-800 text-[9px] font-bold rounded-md border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($cls) ?></span>
                                <?php endforeach; else: ?>
                                    <span class="text-xs text-slate-400">Pa klasa</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-2 text-right pr-10">
                            <button class="status-toggle px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' ?>" 
                                data-id="<?= $row['user_id'] ?>" data-field="status" data-value="<?= $row['status'] ?>" data-original="<?= $row['status'] ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </button>
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
                        <a href="?search=<?=urlencode($search)?>&page=<?=$p?>" class="pagination-link px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $p == $page ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-gray-800' ?>"><?= $p ?></a>
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
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ky veprim do të përditësojë qasjen e mësuesit menjëherë.</p>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl dark:bg-gray-800 dark:text-slate-300 transition">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-500 transition shadow-lg shadow-indigo-500/30">Vazhdo</button>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

<?php require_once 'form.php'; ?>

<script>
const API_URL = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/update-inline.php';
let searchTimeout;

function filterTeachers() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const query = document.getElementById("liveSearch").value.trim();
        const url = new URL(window.location.href);
        if (query) url.searchParams.set('search', query);
        else url.searchParams.delete('search');
        url.searchParams.set('page', 1);
        loadPage(url.toString());
    }, 400);
}

function clearSearch() {
    document.getElementById("liveSearch").value = "";
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    url.searchParams.set('page', 1);
    loadPage(url.toString());
}

async function loadPage(url) {
    const container = document.getElementById('teacherTableContainer');
    const wrapper = document.getElementById('tableWrapper');
    wrapper.style.minHeight = `${wrapper.offsetHeight}px`;
    container.style.opacity = '0.5';
    
    try {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        container.innerHTML = doc.getElementById('teacherTableContainer').innerHTML;
        window.history.pushState({}, '', url);
    } catch (err) {
        showToast('Gabim gjatë ngarkimit', 'error');
    } finally {
        container.style.opacity = '1';
        wrapper.style.minHeight = '0px';
    }
}

async function save(el, forcedValue = null) {
    const userId = el.dataset.id;
    const field  = el.dataset.field;
    const oldValue = el.getAttribute('data-original') || "";
    const newValue = forcedValue !== null ? forcedValue : (el.tagName === 'SELECT' ? el.value : el.innerText.trim());

    if (newValue === oldValue) return;
    el.classList.add('opacity-40', 'pointer-events-none');

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId, field, value: newValue })
        });
        const result = await response.json();
        if (result.status === 'success') {
            el.setAttribute('data-original', newValue);
            if (el.classList.contains('status-toggle')) updateStatusUI(el, newValue);
            showToast('Të dhënat u përditësuan');
        } else { throw new Error(result.message); }
    } catch (err) {
        if (el.tagName === 'SELECT') el.value = oldValue; else el.innerText = oldValue;
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
    toast.innerHTML = `${isSuccess ? '✓' : '✕'} <span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

document.addEventListener('click', e => {
    const link = e.target.closest('.pagination-link');
    if (link) { e.preventDefault(); loadPage(link.getAttribute('href')); }

    if (e.target.classList.contains('status-toggle')) {
        window.pendingStatus = { btn: e.target, newStatus: e.target.dataset.value === 'active' ? 'inactive' : 'active' };
        document.getElementById('statusModal').classList.remove('hidden');
    }

    const addBtn = e.target.closest('#addTeacherBtn');
    if (addBtn) {
        const form = document.getElementById('addTeacherForm');
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
document.addEventListener('change', e => { if (e.target.classList.contains('editable-select')) save(e.target); });
document.addEventListener('keydown', e => { 
    if (e.target.classList.contains('editable') && e.key === 'Enter') { 
        e.preventDefault(); 
        e.target.blur(); 
    } 
});

<?php if (isset($_SESSION['success'])): ?>
    showToast("<?= htmlspecialchars($_SESSION['success']) ?>");
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
</script>
<?php endif; ?>

<?php 
if (!$isAjax) {
    $content = ob_get_clean(); 
    require_once __DIR__ . '/../../index.php'; 
}
?>