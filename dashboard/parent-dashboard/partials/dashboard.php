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

$userId   = (int)$_SESSION['user']['id'];       // users.id
$schoolId = (int)$_SESSION['user']['school_id'];

/* =========================
   RESOLVE REAL parent_id
   ========================= */
$stmt = $pdo->prepare("
    SELECT id 
    FROM parents 
    WHERE user_id = ? AND school_id = ?
");
$stmt->execute([$userId, $schoolId]);
$parentId = (int)$stmt->fetchColumn();

if (!$parentId) {
    die('Parent profile not found');
}

/* =========================
   RESOLVE student_id
   ========================= */
$studentId = (int)($_GET['student_id'] ?? 0);

if (!$studentId) {
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM parent_student
        WHERE parent_id = ?
        LIMIT 1
    ");
    $stmt->execute([$parentId]);
    $studentId = (int)$stmt->fetchColumn();
}

if (!$studentId) {
    die('No children linked to this parent');
}

/* =========================
   OWNERSHIP CHECK
   ========================= */
$stmt = $pdo->prepare("
    SELECT 
        s.student_id,
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
   KPI: ATTENDANCE %
   ========================= */
$stmt = $pdo->prepare("
    SELECT 
        SUM(present = 1) AS present_days,
        COUNT(*) AS total_days
    FROM attendance
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$att = $stmt->fetch(PDO::FETCH_ASSOC);

$attendancePercent = $att['total_days'] > 0
    ? round(($att['present_days'] / $att['total_days']) * 100)
    : 0;

/* =========================
   KPI: PENDING ASSIGNMENTS
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

$classId = (int)$stmt->fetchColumn();

if (!$classId) {
    die('Class not found');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignments
    WHERE class_id = ?
      AND school_id = ?
");
$stmt->execute([
    $classId,
    $schoolId
]);

$pendingAssignments = (int)$stmt->fetchColumn();


/* =========================
   KPI: AVERAGE GRADE
   ========================= */
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(grade), 2)
    FROM grades
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$averageGrade = (float)($stmt->fetchColumn() ?: 0);

/* =========================
   BACKEND READY ✔
   ========================= */
?>

<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <title>Parent Dashboard | E-Shkolla</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

<div class="flex min-h-screen">

  <!-- SIDEBAR -->
  <aside class="w-64 bg-white border-r">
    <div class="p-5 font-bold text-blue-600 text-xl">🎓 E-SHKOLLA</div>
    <nav class="px-4 space-y-2">
      <a class="block p-2 rounded bg-blue-50">Paneli</a>
      <a class="block p-2 rounded hover:bg-gray-100">Fëmijët e Mi</a>
      <a class="block p-2 rounded hover:bg-gray-100">Prezenca</a>
      <a class="block p-2 rounded hover:bg-gray-100">Notat</a>
      <a class="block p-2 rounded hover:bg-gray-100">Detyrat</a>
    </nav>
  </aside>

  <!-- CONTENT -->
  <main class="flex-1 p-8">

    <div class="bg-white p-6 rounded-xl shadow mb-6">
      <h2 class="text-xl font-semibold">
        Mirë se erdhe, <?= htmlspecialchars($_SESSION['user']['name']) ?>
      </h2>
      <p class="text-gray-500">Klasa: <?= htmlspecialchars($student['class_name']) ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

      <div class="bg-blue-50 p-4 rounded-lg">
        <p class="text-sm text-gray-500">Prezenca</p>
        <p class="text-2xl font-bold"><?= $attendancePercent ?>%</p>
      </div>

      <div class="bg-green-50 p-4 rounded-lg">
        <p class="text-sm text-gray-500">Mesatarja</p>
        <p class="text-2xl font-bold"><?= $averageGrade ?></p>
      </div>

      <div class="bg-purple-50 p-4 rounded-lg">
        <p class="text-sm text-gray-500">Detyra aktive</p>
        <p class="text-2xl font-bold"><?= $pendingAssignments ?></p>
      </div>

    </div>

  </main>

</div>
</body>
</html>
