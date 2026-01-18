<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Pagination
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch Parents
$stmt = $pdo->prepare("SELECT * FROM parents WHERE school_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$schoolId]);
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total for pagination
$totalRowsStmt = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ?");
$totalRowsStmt->execute([$schoolId]);
$totalPages = ceil($totalRowsStmt->fetchColumn() / $limit);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center justify-between">
        <div class="mt-5">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Prindërit e Shkollës</h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">Menaxhoni listën e prindërve dhe të dhënat e tyre në kohë reale.</p>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input id="liveSearch" type="text" placeholder="Kërko prindërit..." class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none" oninput="filterParents()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
        <button onclick="document.getElementById('liveSearch').value=''; filterParents();" class="text-xs text-gray-500 hover:text-indigo-600">Pastro</button>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Emri dhe mbiemri</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Telefon</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Kujdestari/ja</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Krijuar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        <?php foreach ($parents as $row): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white sm:pl-0">
                                <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="name" data-original="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="phone" data-original="<?= htmlspecialchars($row['phone']) ?>"><?= htmlspecialchars($row['phone']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="email" data-original="<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <select class="editable-select rounded-full px-3 py-1 text-xs border bg-gray-50 dark:bg-white/5 dark:text-white appearance-none" data-id="<?= $row['user_id'] ?>" data-field="relation" data-original="<?= $row['relation'] ?>">
                                    <option value="father" <?= $row['relation'] === 'father' ? 'selected' : '' ?>>Babai</option>
                                    <option value="mother" <?= $row['relation'] === 'mother' ? 'selected' : '' ?>>Nëna</option>
                                    <option value="guardian" <?= $row['relation'] === 'guardian' ? 'selected' : '' ?>>Kujdestar</option>
                                    <option value="other" <?= $row['relation'] === 'other' ? 'selected' : '' ?>>Tjetër</option>
                                </select>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>" data-id="<?= $row['user_id'] ?>" data-field="status" data-value="<?= $row['status'] ?>" data-original="<?= $row['status'] ?>">
                                    <?= ucfirst($row['status']) ?>
                                </button>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-400">
                                <?= date('Y-m-d', strtotime($row['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="statusModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-white/10">
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ndrysho Statusin?</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Kjo mund të ndikojë në qasjen e përdoruesit në sistem.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg dark:bg-gray-800 dark:text-gray-300">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500">Vazhdo</button>
        </div>
    </div>
</div>

<script>
const API_URL = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/parent/update-inline.php';
let pendingStatusChange = null;

async function save(el, forcedValue = null) {
    const userId = el.dataset.id;
    const field  = el.dataset.field;
    const oldValue = el.getAttribute('data-original') || "";
    const newValue = forcedValue !== null ? forcedValue : (el.tagName === 'SELECT' ? el.value : el.innerText.trim());

    if (newValue === oldValue) {
        visualFeedback(el, 'error');
        return;
    }

    el.classList.add('opacity-50', 'pointer-events-none');

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId, field, value: newValue })
        });
        const result = await response.json();

        if (result.status === 'success') {
            visualFeedback(el, 'success');
            el.setAttribute('data-original', newValue);
            if (el.classList.contains('status-toggle')) updateStatusUI(el, newValue);
        } else {
            throw new Error(result.message || "Gabim");
        }
    } catch (err) {
        visualFeedback(el, 'error');
        if (el.tagName === 'SELECT') el.value = oldValue;
        else el.innerText = oldValue;
        if (err.message !== "Gabim") alert(err.message);
    } finally {
        el.classList.remove('opacity-50', 'pointer-events-none');
    }
}

function visualFeedback(el, type) {
    const color = type === 'success' ? 'ring-green-500' : 'ring-red-500';
    el.classList.add('ring-2', color);
    setTimeout(() => el.classList.remove('ring-2', 'ring-green-500', 'ring-red-500'), 1500);
}

function updateStatusUI(btn, value) {
    btn.dataset.value = value;
    btn.innerText = value.charAt(0).toUpperCase() + value.slice(1);
    btn.className = `status-toggle px-3 py-1 rounded-full text-xs font-semibold ${value === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`;
}

// Modal Logic
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

document.getElementById('cancelStatus').onclick = () => {
    document.getElementById('statusModal').classList.add('hidden');
};

// Listeners
document.addEventListener('focusout', e => { if (e.target.classList.contains('editable')) save(e.target); });
document.addEventListener('change', e => { if (e.target.classList.contains('editable-select')) save(e.target); });
document.addEventListener('keydown', e => { if (e.target.classList.contains('editable') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); } });

// Live Search
function filterParents() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    const rows = document.querySelectorAll("tbody tr");
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
}

const btn = document.getElementById('addParentBtn');
const form = document.getElementById('addParentForm');
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