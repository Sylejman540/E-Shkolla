<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM schools ORDER BY created_at DESC");
$stmt->execute();

$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <!-- <tr>
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white"><?= htmlspecialchars($row['school_name']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['city']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                            <p class="text-green-500 py-[1px] w-14 px-2 h-6 bg-green-200 rounded-xl">
                                <?= htmlspecialchars($row['status']) ?>
                            </p>
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr> -->
<main class="lg:pl-72">
  <div class="xl:pl-18">
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
        <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Schools</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">A list of all schools in your system including their school name, city, status.</p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button type="button" id="addSchoolBtn" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs hover:bg-indigo-500">Add schools</button>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">School Name</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">School Admin</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">City</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Created At</th>
                    </tr>
                </thead>
                <?php if(!empty($schools)): ?>
                <tbody class="divide-y">
                <?php foreach ($schools as $row): ?>
                <tr class="hover:bg-gray-50">

                <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white">
                    <span contenteditable
                        class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                        data-id="<?= $row['id'] ?>"
                        data-field="school_name">
                    <?= htmlspecialchars($row['school_name']) ?>
                    </span>
                </td>

                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                    <span contenteditable
                        class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                        data-id="<?= $row['id'] ?>"
                        data-field="name">
                    <?= htmlspecialchars($row['name']) ?>
                    </span>
                </td>

                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                    <span contenteditable
                        class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                        data-id="<?= $row['id'] ?>"
                        data-field="city">
                    <?= htmlspecialchars($row['city']) ?>
                    </span>
                </td>

                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                    <span contenteditable
                        class="editable inline-block min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500 transition"
                        data-id="<?= $row['id'] ?>"
                        data-field="email">
                    <?= htmlspecialchars($row['email']) ?>
                    </span>
                </td>

                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                    <?php if ($row['id'] != $_SESSION['user']['id']): ?>
                    <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold
                        <?= $row['status']==='active'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-600' ?>"
                        data-id="<?= $row['id'] ?>"
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
                <?php endforeach; ?>
                </tbody>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            The table is empty
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
    const btn = document.getElementById('addSchoolBtn');
    const form = document.getElementById('addSchoolForm');
    const cancel = document.getElementById('cancel');

    btn?.addEventListener('click', () => {
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    cancel?.addEventListener('click', () => {
        form.classList.add('hidden');
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
    const schoolId = el.dataset.id;
    const field    = el.dataset.field;

    let value;
    if (forcedValue !== null) {
        value = forcedValue;
    } else if (el.tagName === 'SELECT') {
        value = el.value;
    } else {
        value = el.innerText.trim();
    }

    fetch('/E-Shkolla/dashboard/superadmin-dashboard/partials/school/update-inline.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ schoolId, field, value })
    }).then(() => location.reload());
    }
</script>


</body>
</html>
