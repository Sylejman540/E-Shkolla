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
$studentId = (int) ($_GET['student_id'] ?? 0);

if (!$studentId) {
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM parent_student
        WHERE parent_id = ?
        LIMIT 1
    ");
    $stmt->execute([$parentId]);
    $studentId = (int) $stmt->fetchColumn();
}

if (!$studentId) {
    die('No children linked to this parent');
}

/* =========================
   OWNERSHIP CHECK + STUDENT
   ========================= */
$stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.name AS student_name,
        s.class_name
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ?
      AND s.student_id = ?
      AND s.school_id = ?
");
$stmt->execute([$parentId, $studentId, $schoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Unauthorized child access');
}

/* =========================
   FETCH GRADES (PARENT VIEW)
   ========================= */
$stmt = $pdo->prepare("
    SELECT
        g.grade,
        g.created_at,
        sub.subject_name
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_id = ?
      AND g.school_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$studentId, $schoolId]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   AVERAGE GRADE
   ========================= */
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(grade), 2)
    FROM grades
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$averageGrade = (float) ($stmt->fetchColumn() ?: 0);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Notat | Paneli i Prindit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
<main class="lg:pl-72">
<div class="px-6 py-8 space-y-6">

  <!-- HEADER -->
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-xl font-semibold">Notat e Nxënësit</h2>
    <p class="text-gray-500">
      Nxënësi:
      <strong><?= htmlspecialchars($student['student_name']) ?></strong> |
      Klasa:
      <?= htmlspecialchars($student['class_name']) ?>
    </p>
  </div>

  <!-- AVERAGE -->
  <div class="bg-green-50 rounded-xl p-4">
    <p class="text-sm text-gray-600">Mesatarja</p>
    <p class="text-3xl font-bold"><?= $averageGrade ?></p>
  </div>

  <!-- GRADES TABLE -->
  <div class="bg-white rounded-xl shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Lista e Notave</h3>

    <?php if (!empty($grades)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="border-b text-left text-sm text-gray-600">
              <th class="py-2">Lënda</th>
              <th class="py-2">Nota</th>
              <th class="py-2">Data</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grades as $row): ?>
              <tr class="border-b last:border-b-0">
                <td class="py-2 font-medium">
                  <?= htmlspecialchars($row['subject_name']) ?>
                </td>
                <td class="py-2">
                  <span class="inline-block px-2 py-1 rounded text-sm
                    <?= $row['grade'] >= 4
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($row['grade']) ?>
                  </span>
                </td>
                <td class="py-2 text-sm text-gray-500">
                  <?= htmlspecialchars(date('d.m.Y', strtotime($row['created_at']))) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-sm text-gray-500">Nuk ka nota për këtë nxënës.</p>
    <?php endif; ?>
  </div>

</div>
</main>
</body>
</html>
