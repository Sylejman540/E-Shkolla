<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../../../db.php';

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) {
    die('Invalid session');
}

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) {
    die('Teacher not found');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM assignments
    WHERE school_id = ?
      AND teacher_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$schoolId, $teacherId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(completed_at IS NULL) AS active,
        SUM(completed_at IS NOT NULL) AS completed
    FROM assignments
    WHERE school_id = ?
      AND teacher_id = ?
");

$stmt->execute([$schoolId, $teacherId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total     = (int) ($stats['total'] ?? 0);
$active    = (int) ($stats['active'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM assignments ORDER BY created_at DESC");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Shkolla | Detyrat</title>

  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
<main class="lg:pl-72">
  <div class="px-4 py-8 sm:px-6 lg:px-8">

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Detyrat</h1>
        <p class="mt-1 text-sm text-gray-600">
          Menaxho detyrat për klasat e tua
        </p>
      </div>

      <button id="addSchoolBtn"
        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">
        + Shto Detyrë
      </button>
    </div>

    <!-- STATS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Totali i Detyrave</p>
        <p class="mt-2 text-2xl font-bold text-gray-900"><?= $total ?></p>  
      </div>

      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Detyra Aktive</p>
        <p class="mt-2 text-2xl font-bold text-indigo-600"><?= $active ?></p>
      </div>

      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Të Përfunduara</p>
        <p class="mt-2 text-2xl font-bold text-green-600"><?= $completed ?></p>
      </div>
    </div>

    <section>
      <h2 class="text-sm font-semibold text-gray-700 mb-4">
        📋 Të gjitha detyrat
      </h2>

      <div class="rounded-xl bg-white shadow divide-y">
        <?php foreach ($assignments as $row): ?>
          <div class="flex items-center justify-between p-5 hover:bg-gray-50">
            <div>
              <p class="font-medium text-gray-900">
                <?= htmlspecialchars($row['title']) ?>
              </p>
              <p class="text-sm text-gray-500">
                <?= htmlspecialchars($row['description']) ?>
              </p>
              <p class="text-sm text-blue-500 bg-blue-200 mt-1 w-[68px] rounded-md p-[2px]">
              <?= htmlspecialchars($row['due_date']) ?>
              </p>
            </div>
            <button
              type="button"
              class="deleteAssignment text-red-600 hover:text-red-800"
              data-id="<?= (int)$assignment['id'] ?>"
              title="Fshij detyrën">
              🗑
            </button>
          </div>
        <?php endforeach; ?>
        
      </div>
    </section>

    <?php require_once 'form.php'; ?>

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

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.deleteAssignment');
    if (!btn) return;

    const id = btn.dataset.id;
    if (!id) return;

    if (!confirm('A jeni i sigurt që doni ta fshini këtë detyrë?')) return;

    fetch('/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/delete_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // remove row instantly (better UX)
            btn.closest('tr')?.remove();
        } else {
            alert(data.message || 'Fshirja dështoi');
        }
    })
    .catch(() => alert('Gabim në server'));
});
</script>

</body>
</html>
