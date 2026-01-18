<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) die("Aksesi i ndaluar.");

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade ASC");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center py-6">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Orari i Mësimit</h1>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openGlobalScheduleForm()" class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                Shto Orë Mësimi
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div id="success-alert" class="mb-4 p-4 bg-green-50 text-green-800 border border-green-200 rounded-lg transition-opacity duration-500">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="mt-4 flow-root">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-300 dark:divide-white/10">
                <thead>
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white">Klasa</th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white">Veprime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    <?php foreach($classes as $row): ?>
                    <tr>
                        <td class="py-4 pl-4 pr-3 text-sm font-bold text-indigo-600 dark:text-indigo-400">Klasa <?= htmlspecialchars($row['grade']) ?></td>
                        <td class="py-4 text-right">
                            <button onclick="toggleSchedule(<?= $row['id'] ?>)" class="text-indigo-600 bg-indigo-50 px-3 py-1 rounded-md text-sm font-medium">Shiko Orarin</button>
                        </td>
                    </tr>
                    <tr id="sched-row-<?= $row['id'] ?>" class="hidden bg-slate-50 dark:bg-gray-900/50">
                        <td colspan="2" class="p-4">
                            <div id="grid-container-<?= $row['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-inner border dark:border-white/10">
                                <p class="text-center text-gray-500">Duke ngarkuar...</p>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require_once 'form.php'; ?>
</div>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>

<script>
async function toggleSchedule(classId) {
    const row = document.getElementById(`sched-row-${classId}`);
    const container = document.getElementById(`grid-container-${classId}`);
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        // Rruga relative pa /E-Shkolla/ për të shmangur gabimin 404
        const res = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
        container.innerHTML = await res.text();
    } else {
        row.classList.add('hidden');
    }
}

async function deleteScheduleEntry(id, classId) {
    if(!confirm('A jeni të sigurt?')) return;
    try {
        const res = await fetch('partials/schedule/delete-entry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if(data.success) {
            // Refresh grid-in pa reload faqen
            const container = document.getElementById(`grid-container-${classId}`);
            const refresh = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            container.innerHTML = await refresh.text();
        }
    } catch (err) { alert("Gabim gjatë fshirjes."); }
}

function openGlobalScheduleForm() { document.getElementById('addScheduleModal').classList.remove('hidden'); }
function closeModalFunc() { document.getElementById('addScheduleModal').classList.add('hidden'); }

setTimeout(() => {
    const alert = document.getElementById("success-alert");
    if (alert) alert.style.opacity = "0";
}, 3000);

function autoSelectSubject() {
    const teacher = document.getElementById('f_teacher');
    const subject = document.getElementById('f_subject');
    const subId = teacher.options[teacher.selectedIndex].getAttribute('data-sub');
    if(subId) {
        subject.value = subId;
    }
}
</script>