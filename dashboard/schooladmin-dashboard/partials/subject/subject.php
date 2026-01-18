<?php 
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

/**
 * SQL i përditësuar:
 * 1. s.subject_name -> Emri i lëndës nga tabela subjects
 * 2. t.name -> Emri i mësuesit nga tabela teachers
 * 3. t.status -> Statusi i mësuesit (pasi kërkuat që statusi të vijë nga tabela teachers)
 */
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.subject_name, 
        t.name AS teacher_display_name, 
        t.status AS teacher_display_status,
        t.user_id AS teacher_user_id
    FROM subjects s
    LEFT JOIN teachers t ON s.user_id = t.user_id
    WHERE s.school_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->execute([$schoolId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center justify-between">
        <div class="mt-5">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Lëndët e Shkollës</h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">Menaxhimi i lëndëve dhe statusi i mësuesve përgjegjës.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="/E-Shkolla/teachers?open_form=1" class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 transition">
                Shto Lëndë/Mësues
            </a>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input id="liveSearch" type="text" placeholder="Kërko lëndët..." class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none border-gray-300 dark:border-white/10" oninput="filterSubjects()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
        <button onclick="document.getElementById('liveSearch').value=''; filterSubjects();" class="text-xs text-gray-500 hover:text-indigo-600">Pastro</button>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Emri i Lëndës</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Mësimdhënësi Përgjegjës</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi i Mësuesit</th>
                        </tr>
                    </thead>
                    <tbody id="subjectsTableBody" class="divide-y divide-gray-200 dark:divide-white/5">
                        <?php if(!empty($subjects)): ?>
                            <?php foreach ($subjects as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white sm:pl-0">
                                    <span contenteditable class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none transition-all" 
                                          data-id="<?= $row['id'] ?>" 
                                          data-field="subject_name" 
                                          data-original="<?= htmlspecialchars($row['subject_name']) ?>">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
    <span class="font-medium"><?= htmlspecialchars($row['teacher_display_name'] ?? 'I pacaktuar') ?></span>
</td>

<td class="px-3 py-4 text-sm">
    <?php if($row['teacher_display_name']): ?>
        <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold <?= $row['teacher_display_status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>" 
                data-id="<?= $row['teacher_user_id'] ?>" 
                data-field="status" 
                data-value="<?= $row['teacher_display_status'] ?>" 
                data-original="<?= $row['teacher_display_status'] ?>"
                data-target="teacher">
            <?= ucfirst($row['teacher_display_status']) ?>
        </button>
    <?php else: ?>
        <span class="text-gray-400 italic text-xs">Pa status</span>
    <?php endif; ?>
</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="py-10 text-center text-sm text-gray-500">Nuk u gjet asnjë lëndë.</td></tr>
                        <?php endif; ?>
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
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ndrysho Statusin e Mësuesit?</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ky veprim do të ndryshojë statusin e mësuesit në të gjithë sistemin.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button id="cancelStatus" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg dark:bg-gray-800 dark:text-gray-300">Anulo</button>
            <button id="confirmStatus" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500">Vazhdo</button>
        </div>
    </div>
</div>

<script>
// Kujdes: Këtu duhet të keni dy API endpoint nëse ndryshoni dy tabela të ndryshme
const API_SUBJECT = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/subjects/update-inline.php';
const API_TEACHER = '/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/update-inline.php';

let pendingStatusChange = null;

async function save(el, forcedValue = null) {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const oldValue = el.getAttribute('data-original') || "";
    const newValue = forcedValue !== null ? forcedValue : el.innerText.trim();

    if (newValue === oldValue) return;

    // Përcaktojmë cilën API do thërrasim
    const isTeacher = el.hasAttribute('data-target') && el.dataset.target === 'teacher';
    const targetURL = isTeacher ? API_TEACHER : API_SUBJECT;

    el.classList.add('opacity-50', 'pointer-events-none');

    try {
        const response = await fetch(targetURL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                [isTeacher ? 'userId' : 'subjectId']: id, 
                field, 
                value: newValue 
            })
        });
        
        const result = await response.json();

        if (result.status === 'success' || result.success) {
            visualFeedback(el, 'success');
            el.setAttribute('data-original', newValue);
            if (field === 'status') updateStatusUI(el, newValue);
        } else {
            throw new Error(result.message || "Gabim");
        }
    } catch (err) {
        visualFeedback(el, 'error');
        el.innerText = oldValue;
        console.error(err);
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

function filterSubjects() {
    const filter = document.getElementById("liveSearch").value.toLowerCase().trim();
    document.querySelectorAll("#subjectsTableBody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
}
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>