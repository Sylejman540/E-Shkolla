<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$limit = 10;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// total rows
$totalStmt = $pdo->query("SELECT COUNT(*) FROM schools");
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// fetch paginated rows
$stmt = $pdo->prepare("SELECT * FROM schools ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);


ob_start();
?>  

<div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="mt-5 sm:px-0 sm:flex-auto">
                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white transition-all">
                    E-Shkolla School Center
                </h1>
                
                <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                    A list of all schools in the system
                </p>
            </div>
            <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <button type="submit" id="addSchoolBtn" class="md:w-full sm:w-auto rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Add School
                </button>
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
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex justify-between items-center">
                    <p class="text-sm text-slate-600">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </p>

                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>"
                            class="px-3 py-1 rounded-md border text-sm hover:bg-slate-100">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>"
                            class="px-3 py-1 rounded-md text-sm
                            <?= $i === $page
                                    ? 'bg-indigo-600 text-white'
                                    : 'border hover:bg-slate-100' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>"
                            class="px-3 py-1 rounded-md border text-sm hover:bg-slate-100">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php require_once 'form.php'; ?>
        </div>
        </div>
    </div>
  </div>
</main>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>
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
