<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   AUTH GUARD
========================= */
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    die('Not authenticated');
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

/* =========================
   RESOLVE parent_id
========================= */
$stmt = $pdo->prepare("
    SELECT id
    FROM parents
    WHERE user_id = ? AND school_id = ?
");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) {
    die('Parent profile not found');
}

/* =========================
   RESOLVE student_id
========================= */
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ?
      AND s.school_id = ?
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$children) {
    die('No children linked');
}

// if student_id not provided, use FIRST child
$studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

/* =========================
   OWNERSHIP CHECK
========================= */
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ?
      AND s.student_id = ?
      AND s.school_id = ?
");
$stmt->execute([$parentId, $studentId, $schoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Unauthorized access');
}

/* =========================
   ATTENDANCE DATA
========================= */
$stmt = $pdo->prepare("
    SELECT
        DATE(a.created_at) AS date,
        sub.name AS subject_name,
        t.name AS teacher_name,
        a.present
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN teachers t ON t.id = a.teacher_id
    WHERE a.student_id = ?
      AND a.school_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$studentId, $schoolId]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Prezenca | Prindi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
<main class="lg:pl-72">
<div class="px-6 py-8 space-y-6">

  <!-- HEADER -->
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-xl font-semibold">Prezenca</h2>
    <p class="text-gray-500">
      Nxënësi: <strong><?= htmlspecialchars($student['name']) ?></strong>
    </p>
  </div>

  <!-- TABLE -->
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="min-w-full">
      <thead class="bg-gray-100 text-sm text-gray-600">
        <tr>
          <th class="px-4 py-3 text-left">Data</th>
          <th class="px-4 py-3 text-left">Lënda</th>
          <th class="px-4 py-3 text-left">Mësuesi</th>
          <th class="px-4 py-3 text-center">Statusi</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if ($attendance): ?>
          <?php foreach ($attendance as $row): ?>
            <tr>
              <td class="px-4 py-2"><?= htmlspecialchars($row['date']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['subject_name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['teacher_name']) ?></td>
              <td class="px-4 py-2 text-center">
                <span class="px-2 py-1 text-xs rounded
                  <?= $row['present'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                  <?= $row['present'] ? 'I pranishëm' : 'Mungesë' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="px-4 py-6 text-center text-gray-500">
              Nuk ka të dhëna për prezencë.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</main>
</body>
</html>
