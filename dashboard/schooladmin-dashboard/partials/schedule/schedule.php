<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Fetch Classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade ASC");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="mt-5 sm:flex-auto">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Orari i Mësimit</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Menaxhoni orarin javor për secilën klasë dhe shmangni përplasjet.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16">
            <button type="button" onclick="openGlobalScheduleForm()" class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 transition">
                Shto Orë Mësimi
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div id="success-alert" class="mt-6 p-4 text-sm text-green-800 bg-green-50 border border-green-200 rounded-lg transition-opacity duration-500">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Klasa</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Viti Akademik</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Nr. Nxënësve</th>
                            <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white">Veprime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        <?php foreach($classes as $row): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition group">
                            <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-bold text-indigo-600 dark:text-indigo-400 sm:pl-0">
                                <?= htmlspecialchars($row['grade']) ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($row['academic_year']) ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($row['max_students']) ?>
                            </td>
                            <td class="py-4 text-right text-sm font-medium">
                                <button onclick="toggleSchedule(<?= $row['id'] ?>)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-md transition font-semibold">
                                    Shiko/Edito Orarin
                                </button>
                            </td>
                        </tr>
                        <tr id="sched-row-<?= $row['id'] ?>" class="hidden bg-slate-50 dark:bg-gray-900/50">
                            <td colspan="4" class="p-6">
                                <div id="grid-container-<?= $row['id'] ?>" class="bg-white dark:bg-gray-800 rounded-xl shadow-inner border dark:border-white/10 p-4 min-h-[100px]">
                                    <div class="flex justify-center p-10">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
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
    <?php require_once 'form.php'; ?>
</div>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>

<script>
// --- Modal Logic ---
function openGlobalScheduleForm() {
    const modal = document.getElementById('addScheduleModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

// Close logic is already in form.php, but we can ensure it here
document.addEventListener('click', (e) => {
    const modal = document.getElementById('addScheduleModal');
    if (e.target === modal) {
        modal.classList.add('hidden');
    }
});

// --- Toggle and Load Schedule Grid ---
async function toggleSchedule(classId) {
    const row = document.getElementById(`sched-row-${classId}`);
    const container = document.getElementById(`grid-container-${classId}`);
    
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        try {
            // Sigurohuni që ky path është i saktë
            const response = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            container.innerHTML = await response.text();
        } catch (err) {
            container.innerHTML = '<div class="p-4 text-red-500 bg-red-50 rounded-lg text-sm">Gabim: Nuk u mundësua ngarkimi i orarit.</div>';
        }
    } else {
        row.classList.add('hidden');
    }
}

// --- Inline Delete Action ---
async function deleteScheduleEntry(id, classId) {
    if(!confirm('A jeni të sigurt që dëshironi të fshini këtë orë mësimi?')) return;
    
    try {
        const res = await fetch('delete-entry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        
        if(res.ok) {
            // Refresh grid
            const container = document.getElementById(`grid-container-${classId}`);
            container.innerHTML = '<div class="flex justify-center p-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>';
            
            const response = await fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classId}`);
            container.innerHTML = await response.text();
        }
    } catch (err) {
        alert("Ndodhi një gabim gjatë fshirjes.");
    }
}

// --- Auto-hide alerts ---
setTimeout(() => {
    const alert = document.getElementById("success-alert");
    if (alert) {
        alert.style.opacity = "0";
        setTimeout(() => alert.remove(), 700);
    }
}, 3000);
</script>