<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Pagination (Opsionale, por e rekomanduar për performancë)
$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM parents WHERE school_id = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$schoolId]);
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRows = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_id = ?");
$totalRows->execute([$schoolId]);
$totalPages = ceil($totalRows->fetchColumn() / $limit);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="mt-5 sm:flex-auto">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Prindërit e Shkollës
            </h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                Menaxhoni listën e prindërve dhe lidhjen e tyre me nxënësit.
            </p>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input
                id="liveSearch"
                type="text"
                placeholder="Kërko prindërit..."
                class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none"
                oninput="filterParents()"
            >
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <button onclick="document.getElementById('liveSearch').value=''; filterParents();" class="text-xs text-gray-500 hover:text-indigo-600">Pastro</button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <div id="success-alert" class="p-4 mb-6 text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg transition-opacity duration-700">
        <?= htmlspecialchars($_SESSION['success']); ?>
    </div>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/10">
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
                        <?php if(!empty($parents)): ?>
                            <?php foreach ($parents as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white sm:pl-0">
                                    <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="name"><?= htmlspecialchars($row['name']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="phone"><?= htmlspecialchars($row['phone']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span contenteditable class="editable inline-block min-w-[8rem] px-2 py-1 rounded outline-none focus:ring-2 focus:ring-indigo-500" data-id="<?= $row['user_id'] ?>" data-field="email"><?= htmlspecialchars($row['email']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <select class="editable-select rounded-full px-3 py-1 text-xs border bg-gray-50 dark:bg-white/5 dark:text-white appearance-none" data-id="<?= $row['user_id'] ?>" data-field="relation">
                                        <option value="father" <?= $row['relation'] === 'father' ? 'selected' : '' ?>>Babai</option>
                                        <option value="mother" <?= $row['relation'] === 'mother' ? 'selected' : '' ?>>Nëna</option>
                                        <option value="guardian" <?= $row['relation'] === 'guardian' ? 'selected' : '' ?>>Kujdestar</option>
                                        <option value="other" <?= $row['relation'] === 'other' ? 'selected' : '' ?>>Tjetër</option>
                                    </select>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>" data-id="<?= $row['user_id'] ?>" data-field="status" data-value="<?= $row['status'] ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </button>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-400">
                                    <?= date('Y-m-d', strtotime($row['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="py-10 text-center text-gray-500">Nuk u gjet asnjë prind.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex justify-between items-center">
                        <p class="text-sm text-slate-600 dark:text-gray-400">Faqja <?= $page ?> nga <?= $totalPages ?></p>
                        <div class="flex gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="px-3 py-1 rounded-md text-sm <?= $i === $page ? 'bg-indigo-600 text-white' : 'border hover:bg-slate-100 dark:text-white' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php require_once 'form.php'; ?>
</div>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>

<script>
// --- Inline Save Logic ---
function save(el, forcedValue = null) {
    const userId = el.dataset.id;
    const field  = el.dataset.field;
    let value = forcedValue !== null ? forcedValue : (el.tagName === 'SELECT' ? el.value : el.innerText.trim());

    fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/parent/update-inline.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId, field, value })
    }).then(() => location.reload());
}

document.querySelectorAll('.editable').forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } });
    el.addEventListener('blur', () => save(el));
});

document.querySelectorAll('.editable-select').forEach(el => {
    el.addEventListener('change', () => save(el));
});

document.querySelectorAll('.status-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const newStatus = btn.dataset.value === 'active' ? 'inactive' : 'active';
        save(btn, newStatus);
    });
});

// --- Live Search Logic ---
let searchTimeout;
function filterParents() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
        const rows = document.querySelectorAll("tbody tr:not(.no-results)");
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const isMatch = text.includes(filter);
            row.style.display = isMatch ? "" : "none";
            if (isMatch) {
                visibleCount++;
                highlightMatch(row, filter);
            }
        });

        let noResults = document.querySelector('.no-results');
        if (visibleCount === 0 && rows.length > 0) {
            if (!noResults) {
                const tr = document.createElement('tr');
                tr.className = 'no-results';
                tr.innerHTML = `<td colspan="6" class="py-10 text-center text-gray-500">Nuk u gjet asnjë rezultat.</td>`;
                document.querySelector('tbody').appendChild(tr);
            }
        } else if (noResults) noResults.remove();
    }, 250);
}

function highlightMatch(row, filter) {
    row.querySelectorAll(".editable").forEach(span => {
        const originalText = span.getAttribute('data-original') || span.innerText;
        if (!span.getAttribute('data-original')) span.setAttribute('data-original', originalText);

        if (!filter) {
            span.innerText = originalText;
            return;
        }
        const regex = new RegExp(`(${filter.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "gi");
        span.innerHTML = originalText.replace(regex, "<mark class='bg-yellow-200 text-black rounded-sm'>$1</mark>");
    });
}

// Auto-hide alert
document.addEventListener("DOMContentLoaded", () => {
    const alert = document.getElementById("success-alert");
    if (alert) {
        setTimeout(() => {
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 700);
        }, 3000);
    }
});
</script>