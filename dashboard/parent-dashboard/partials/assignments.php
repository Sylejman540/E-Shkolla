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

$userId   = (int) $_SESSION['user']['id'];       // users.id
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
   RESOLVE class_id (FIXED)
   ========================= */
$stmt = $pdo->prepare("
    SELECT id
    FROM classes
    WHERE grade = ?
      AND school_id = ?
");
$stmt->execute([
    $student['class_name'],
    $schoolId
]);

$classId = (int) $stmt->fetchColumn();

if (!$classId) {
    die('Class not found');
}

/* =========================
   FETCH ASSIGNMENTS (PARENT VIEW)
   ========================= */
$stmt = $pdo->prepare("
    SELECT 
        title,
        description,
        due_date,
        CASE 
            WHEN due_date < CURDATE() THEN 'late'
            ELSE 'active'
        END AS status
    FROM assignments
    WHERE class_id = ?
      AND school_id = ?
    ORDER BY due_date ASC
");
$stmt->execute([$classId, $schoolId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Detyrat | Paneli i Prindit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
<main class="lg:pl-72">
<div class="px-6 py-8 space-y-6">

  <!-- HEADER -->
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-xl font-semibold">Detyrat e Nxënësit</h2>
    <p class="text-gray-500">
      Nxënësi: <strong><?= htmlspecialchars($student['student_name']) ?></strong> |
      Klasa: <?= htmlspecialchars($student['class_name']) ?>
    </p>
  </div>

  <!-- ASSIGNMENTS LIST -->
  <div class="bg-white rounded-xl shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Lista e Detyrave</h3>

    <?php if (!empty($assignments)): ?>
      <?php foreach ($assignments as $task): ?>
        <div class="flex justify-between items-center border-b last:border-b-0 py-3">
          <div>
            <p class="font-medium"><?= htmlspecialchars($task['title']) ?></p>
            <p class="text-sm text-gray-500">
              Afati: <?= htmlspecialchars($task['due_date']) ?>
            </p>
          </div>

          <span class="px-2 py-1 text-xs rounded
            <?= $task['status'] === 'late'
                ? 'bg-red-100 text-red-600'
                : 'bg-green-100 text-green-600' ?>">
            <?= ucfirst($task['status']) ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-sm text-gray-500">Nuk ka detyra për këtë klasë.</p>
    <?php endif; ?>
  </div>

</div>
</main>
</body>
</html>
