<?php 
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Pagination
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Marrim klasat bashkë me emrin e mësuesit kujdestar
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name 
    FROM classes c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.school_id = :school_id 
    ORDER BY c.grade ASC, c.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRowsCount = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$totalRowsCount->execute([$schoolId]);
$totalRecords = $totalRowsCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center justify-between">
        <div class="mt-5">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Klasat e Shkollës</h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">Menaxhoni vitet akademike, klasat dhe kapacitetin e nxënësve.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button type="button" id="addClassBtn" class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 transition">
                Shto Klasë
            </button>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input id="liveSearch" type="text" placeholder="Kërko klasat..." class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none" oninput="filterClasses()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
        <button onclick="document.getElementById('liveSearch').value=''; filterClasses();" class="text-xs text-gray-500 hover:text-indigo-600">Pastro</button>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Viti Akademik</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Klasa</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Nr. i Nxënësve Max</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Mësuesi Kujdestar</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                            <th class="relative py-3.5 pl-3 pr-4 sm:pr-0 text-right text-sm font-semibold">Orari</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        <?php foreach ($classes as $row): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-0">
                                <span><?= htmlspecialchars($row['academic_year']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span contenteditable class="editable inline-block min-w-[5rem] px-2 py-1 rounded outline-none" data-id="<?= $row['id'] ?>" data-field="grade" data-original="<?= htmlspecialchars($row['grade']) ?>"><?= htmlspecialchars($row['grade']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span contenteditable class="editable inline-block min-w-[3rem] px-2 py-1 rounded outline-none" data-id="<?= $row['id'] ?>" data-field="max_students" data-original="<?= htmlspecialchars($row['max_students']) ?>"><?= htmlspecialchars($row['max_students']) ?></span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400 font-medium">
                                <?= $row['teacher_name'] ? htmlspecialchars($row['teacher_name']) : '<span class="italic text-gray-400 text-xs">I pacaktuar</span>' ?>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>" data-id="<?= $row['id'] ?>" data-field="status" data-value="<?= $row['status'] ?>" data-original="<?= $row['status'] ?>">
                                    <?= ucfirst($row['status']) ?>
                                </button>
                            </td>
                            <td class="py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                                <a href="/E-Shkolla/schedule?class_id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">Shiko orarin</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-white/10 pt-4">
        <p class="text-sm text-gray-700 dark:text-gray-400">Faqja <?= $page ?> nga <?= $totalPages ?></p>
        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $i === $page ? 'z-10 bg-indigo-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 dark:text-white' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    </div>
    <?php endif; ?>

    <?php require_once 'form.php'; ?>
</div>

<div id="statusModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-xl border border-gray-200 dark:border-white/10">
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ndrysho Statusin e Klasës?</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Çaktivizimi i klasës mund të kufizojë regjistrimet e reja për këtë vit akademik.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg dark:bg-gray-800 dark:text-gray-300">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500">Vazhdo</button>
        </div>
    </div>
</div>

<script>
const API_URL = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/update-inline.php';
let pendingStatusChange = null;

async function save(el, forcedValue = null) {
    const classId = el.dataset.id;
    const field   = el.dataset.field;
    const oldValue = el.getAttribute('data-original') || "";
    const newValue = forcedValue !== null ? forcedValue : el.innerText.trim();

    if (newValue === oldValue) return;

    el.classList.add('opacity-50', 'pointer-events-none');

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ classId, field, value: newValue })
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
        el.innerText = oldValue;
        alert(err.message);
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

// Modal Toggle Logic
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

// Inline Listeners
document.addEventListener('focusout', e => { if (e.target.classList.contains('editable')) save(e.target); });
document.addEventListener('keydown', e => { if (e.target.classList.contains('editable') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); } });

// Live Search
function filterClasses() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    document.querySelectorAll("tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
}

// Add Class Modal Logic
const addBtn = document.getElementById('addClassBtn');
const classForm = document.getElementById('addClassForm');
addBtn?.addEventListener('click', () => classForm.classList.remove('hidden'));

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