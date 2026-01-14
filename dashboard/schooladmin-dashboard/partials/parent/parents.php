<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM parents WHERE school_id = ?");
$stmt->execute([$schoolId]);

$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$openForm = isset($_GET['open_form']) && $_GET['open_form'] == '1';
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<main class="lg:pl-72">
  <div class="xl:pl-18">
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
        <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Prindërit</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Lista e të gjithë prindërve në sistemin tuaj, duke përfshirë fëmijën dhe statusin e tyre.</p>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Emri dhe mbiemri</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Numri i telefonit</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Kujdestari/ja</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Data e krijimit</th>
                    </tr>
                </thead>
                <?php if(!empty($parents)): ?>
                <?php foreach($parents as $row): ?>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr>
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white">
                            <span contenteditable
                                class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                                 data-id="<?= (int)$row['user_id'] ?>"
                                data-field="name">
                            <?= htmlspecialchars($row['name']) ?>
                            </span>
                        </td>
                        
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <span contenteditable
                                class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                                 data-id="<?= (int)$row['user_id'] ?>"
                                data-field="email">
                            <?= htmlspecialchars($row['email']) ?>
                            </span>
                        </td>
                        
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <span contenteditable
                                class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                                data-id="<?= $row['user_id'] ?>"
                                data-field="phone">
                            <?= htmlspecialchars($row['phone']) ?>
                            </span>
                        </td>

                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                        <select
                            class="editable-select
                                rounded-full
                                px-3 py-1
                                text-xs font-medium
                                border border-gray-300
                                bg-gray-50
                                text-gray-700
                                focus:outline-none
                                focus:ring-2
                                focus:ring-indigo-500
                                focus:border-indigo-500
                                transition appearance-none"
                            data-id="<?= $row['user_id'] ?>"
                            data-field="relation"
                            <?= $row['user_id'] == $_SESSION['user']['id'] ? 'opacity-50 cursor-not-allowed' : '' ?>
                        >
                            <?php foreach (['father', 'mother','guardian','other'] as $role): ?>
                            <option value="<?= $role ?>" <?= $row['relation']===$role?'selected':'' ?>>
                                <?= ucfirst(str_replace('_',' ',$role)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </td>

                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <?php if ($row['user_id'] != $_SESSION['user']['id']): ?>
                            <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold
                                <?= $row['status']==='active'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-600' ?>"
                                data-id="<?= $row['user_id'] ?>"
                                data-field="status"
                                data-value="<?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </button>
                            <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs bg-green-100 text-green-700">
                                Active
                            </span>
                            <?php endif; ?>
                        </td> 
                        <td class="px-3 py-4 text-sm text-gray-400">
                            <?= date('Y-m-d', strtotime($row['created_at'])) ?>
                        </td>
                    </tr>
                </tbody>
                <?php endforeach ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            Tabela nuk përmban të dhëna
                        </td>
                    </tr>
                <?php endif; ?>
                </table>
            </div>
            </div>
            <?php require_once 'form.php'; ?>
        </div>
        </div>
    </div>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('addSchoolForm');
    const cancelBtns = document.querySelectorAll('#cancel, #cancelBtn');

    cancelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.add('hidden');

            // Remove open_form from URL
            const url = new URL(window.location);
            url.searchParams.delete('open_form');
            window.history.replaceState({}, '', url);
        });
    });
});

      document.querySelectorAll('.editable').forEach(el => {
    el.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
        e.preventDefault();
        el.blur();
        }
    });
    el.addEventListener('blur', () => saveSchool(el));
    });

    document.querySelectorAll('.editable-select').forEach(el => {
    el.addEventListener('change', () => saveSchool(el));
    });

    document.querySelectorAll('.status-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const newStatus = btn.dataset.value === 'active'
        ? 'inactive'
        : 'active';
        saveSchool(btn, newStatus);
    });
    });

function saveSchool(el, forcedValue = null) {
    const userId = el.dataset.id;
    const field  = el.dataset.field;

    let value;
    if (forcedValue !== null) {
        value = forcedValue;
    } else if (el.tagName === 'SELECT') {
        value = el.value;
    } else {
        value = el.innerText.trim();
    }

    fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/parent/update-inline.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId, field, value })
    }).then(() => location.reload());
}
</script>


</body>
</html>
