<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// --- PAGINATION LOGIC ---
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch teachers with user data
$stmt = $pdo->prepare("
    SELECT t.*, u.name, u.email, u.status, u.created_at
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    WHERE t.school_id = :school_id
    ORDER BY u.name ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total for pagination
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = ?");
$totalStmt->execute([$schoolId]);
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// --- SLIDING PAGINATION RANGE ---
$range = [];
$adjacents = 2;
if ($totalPages <= 7) {
    $range = range(1, $totalPages);
} else {
    if ($page <= 4) {
        $range = [1, 2, 3, 4, 5, '...', $totalPages];
    } elseif ($page > $totalPages - 4) {
        $range = [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
    } else {
        $range = [1, '...', $page - 1, $page, $page + 1, '...', $totalPages];
    }
}

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Mësuesit e Shkollës</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni listën e mësuesve, lëndët dhe statusin e tyre akademik.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button type="button" id="addTeacherBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Shto mësues
            </button>
        </div>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="relative w-full sm:w-80">
            <input id="liveSearch" type="text" placeholder="Kërko mësuesit..." class="w-full pl-10 pr-4 py-2 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition" oninput="filterTeachers()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
        <div class="text-xs text-slate-400 italic">Treguar <?= count($teachers) ?> nga <?= $totalRows ?> mësues</div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Mësuesi</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kontakt</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Gjinia</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Lënda</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Statusi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($teachers as $row): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <img src="/E-Shkolla/<?= htmlspecialchars($row['profile_photo'] ?: 'assets/img/default-avatar.png') ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-800 shadow-sm"/>
                                <span contenteditable class="editable block text-sm font-semibold text-slate-900 dark:text-white outline-none rounded px-1 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" data-id="<?= $row['user_id'] ?>" data-field="name"><?= htmlspecialchars($row['name']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-600 dark:text-slate-300 flex flex-col">
                                <span contenteditable class="editable outline-none" data-id="<?= $row['user_id'] ?>" data-field="email"><?= htmlspecialchars($row['email']) ?></span>
                                <span contenteditable class="editable text-xs text-slate-400 outline-none mt-1" data-id="<?= $row['user_id'] ?>" data-field="phone"><?= htmlspecialchars($row['phone'] ?? 'Pa numër') ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <select class="editable-select rounded-lg px-2 py-1 text-xs border-none bg-slate-100 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="gender">
                                <option value="male" <?= $row['gender'] === 'male' ? 'selected' : '' ?>>M</option>
                                <option value="female" <?= $row['gender'] === 'female' ? 'selected' : '' ?>>F</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span contenteditable class="editable text-sm text-indigo-600 dark:text-indigo-400 font-medium outline-none" data-id="<?= $row['user_id'] ?>" data-field="subject_name"><?= htmlspecialchars($row['subject_name']) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <button class="status-toggle px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' ?>" data-id="<?= $row['user_id'] ?>" data-value="<?= $row['status'] ?>">
                                <?= $row['status'] ?>
                            </button>
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
                        <a href="?page=<?= $p ?>" class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $p == $page ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-gray-800' ?>">
                            <?= $p ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <a href="?page=<?= min($totalPages, $page + 1) ?>" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-gray-800 transition <?= $page >= $totalPages ? 'pointer-events-none opacity-30' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
    <?php require_once 'form.php'; ?>
</div>

<div id="statusModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-sm bg-white dark:bg-gray-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-white/10">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 mb-4">
                <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ndrysho Statusin?</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ky veprim do të përditësojë qasjen e mësuesit në sistem menjëherë.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl dark:bg-gray-800 dark:text-slate-300 hover:bg-slate-200 transition">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-500 shadow-lg shadow-indigo-500/30 transition">Vazhdo</button>
        </div>
    </div>
</div>

<script>
const API_URL = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/update-inline.php';
let pendingStatusChange = null;

// --- TOAST SYSTEM ---
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
    
    toast.className = `${bgColor} text-white px-6 py-3 rounded-xl shadow-lg transform transition-all duration-300 translate-y-10 opacity-0 flex items-center gap-2 text-sm font-medium`;
    toast.innerHTML = `
        ${type === 'success' ? '✓' : '✕'} 
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
    }, 10);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// --- SAVE FUNCTION ---
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
            if (el.classList.contains('status-toggle')) {
                updateStatusUI(el, newValue);
            }
            showToast('Ndryshimi u ruajt me sukses!');
        } else {
            throw new Error(result.message || 'Gabim gjatë ruajtjes');
        }
    } catch (err) {
        // Revert UI on error
        if (el.tagName === 'SELECT') el.value = oldValue;
        else el.innerText = oldValue;
        showToast(err.message, 'error');
    } finally {
        el.classList.remove('opacity-40', 'pointer-events-none');
    }
}

function updateStatusUI(btn, value) {
    btn.dataset.value = value;
    btn.innerText = value.charAt(0).toUpperCase() + value.slice(1);
    const is_active = value === 'active';
    btn.className = `status-toggle px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all ${
        is_active 
        ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' 
        : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'
    }`;
}

// --- EVENT LISTENERS ---
document.addEventListener('click', e => {
    if (e.target.classList.contains('status-toggle')) {
        pendingStatusChange = { 
            btn: e.target, 
            newStatus: e.target.dataset.value === 'active' ? 'inactive' : 'active' 
        };
        document.getElementById('statusModal').classList.remove('hidden');
    }
});

document.getElementById('confirmStatus').onclick = () => {
    if (pendingStatusChange) {
        save(pendingStatusChange.btn, pendingStatusChange.newStatus);
    }
    document.getElementById('statusModal').classList.add('hidden');
};

document.getElementById('cancelStatus').onclick = () => document.getElementById('statusModal').classList.add('hidden');

// Inline editing
document.addEventListener('focusout', e => { 
    if (e.target.classList.contains('editable')) save(e.target); 
});

document.addEventListener('change', e => { 
    if (e.target.classList.contains('editable-select')) save(e.target); 
});

document.addEventListener('keydown', e => { 
    if (e.target.classList.contains('editable') && e.key === 'Enter') { 
        e.preventDefault(); 
        e.target.blur(); 
    } 
});

// --- SEARCH & HIGHLIGHTING ---
function filterTeachers() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    const rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {
        const textNodes = row.querySelectorAll('.editable, td:not(:has(select, button, img))');
        let matchFound = false;

        textNodes.forEach(node => {
            const originalText = node.getAttribute('data-original') || node.innerText;
            
            if (filter === "") {
                node.innerHTML = originalText; // Clear highlights
                matchFound = true;
            } else if (originalText.toLowerCase().includes(filter)) {
                const regex = new RegExp(`(${filter})`, 'gi');
                node.innerHTML = originalText.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-500/40 text-current rounded-sm">$1</mark>');
                matchFound = true;
            } else {
                node.innerHTML = originalText;
            }
        });

        row.style.display = matchFound ? "" : "none";
    });
}
</script>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>