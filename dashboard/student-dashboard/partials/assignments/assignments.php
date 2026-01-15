<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../index.php';
require_once __DIR__ . '/../../../../db.php';

/* =========================
   AUTH
========================= */
$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$schoolId) {
    die('Access denied');
}

/* =========================
   GET STUDENT ID
========================= */
$stmt = $pdo->prepare("
    SELECT student_id
    FROM students
    WHERE user_id = ?
      AND school_id = ?
    LIMIT 1
");
$stmt->execute([$userId, $schoolId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    die('Student not found');
}

/* =========================
   GET STUDENT CLASS
========================= */
$stmt = $pdo->prepare("
    SELECT class_id
    FROM student_class
    WHERE student_id = ?
    LIMIT 1
");
$stmt->execute([$studentId]);
$classId = $stmt->fetchColumn();

if (!$classId) {
    die('Student has no class');
}


/* =========================
   FETCH ASSIGNMENTS
========================= */
$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.title,
        a.description,
        a.due_date,
        sub.subject_name,
        u.name AS teacher_name
    FROM assignments a
    JOIN class_subject cs ON cs.class_id = a.class_id
    JOIN subjects sub ON sub.id = cs.subject_id
    JOIN users u ON u.id = a.teacher_id
    WHERE a.class_id = ?
      AND a.school_id = ?
    ORDER BY a.due_date ASC
");


$stmt->execute([$classId, $schoolId]);
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
    <div class="flex items-center justify-between mb-8">
      <section class="mt-8">
  <h2 class="text-lg font-semibold mb-4">📚 Detyrat e mia</h2>

<div class="bg-white rounded-xl shadow overflow-hidden w-full">


    <?php if (empty($assignments)): ?>
      <div class="p-6 text-center text-gray-500">
        Nuk ka detyra të caktuara.
      </div>
    <?php else: ?>
<table class="min-w-full w-full divide-y divide-gray-200">

        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold">Lënda</th>
            <th class="px-6 py-3 text-left text-xs font-semibold">Titulli</th>
            <th class="px-6 py-3 text-left text-xs font-semibold">Mësuesi</th>
            <th class="px-6 py-3 text-left text-xs font-semibold">Afati</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          <?php foreach ($assignments as $a): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm"><?= htmlspecialchars($a['subject_name']) ?></td>
              <td class="px-6 py-4 text-sm font-medium"><?= htmlspecialchars($a['title']) ?></td>
              <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($a['teacher_name']) ?></td>
              <td class="px-6 py-4 text-sm text-red-600">
                <?= htmlspecialchars($a['due_date']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </div>
</section>

    </div>
  </div>
</main>
</body>
</html>
