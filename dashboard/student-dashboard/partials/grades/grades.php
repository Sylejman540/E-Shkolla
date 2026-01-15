<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../index.php';
require_once __DIR__ . '/../../../../db.php';

$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$schoolId) {
    die('Access denied');
}


$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
$stmt->execute([$userId, $schoolId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    die('Student not found');
}

$stmt = $pdo->prepare("
    SELECT
        g.grade,
        g.comment,
        sub.subject_name,
        u.name AS teacher_name
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    JOIN teachers t ON t.id = g.teacher_id
    JOIN users u ON u.id = t.user_id
    WHERE g.student_id = ?
      AND g.school_id = ?
");


$stmt->execute([$studentId, $schoolId]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjectsCount = count($grades);
$bestGrade = 0;
$totalGrade = 0;

foreach ($grades as $g) {
    $totalGrade += $g['grade'];
    if ($g['grade'] > $bestGrade) {
        $bestGrade = $g['grade'];
    }
}

$averageGrade = $subjectsCount ? round($totalGrade / $subjectsCount, 2) : 0;
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Shkolla | Notat</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
<main class="lg:pl-72">
  <div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Notat e mia</h1>
      <p class="mt-1 text-sm text-gray-600">
        Shiko notat për secilën lëndë dhe performancën tënde
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Nota mesatare</p>
        <p class="mt-2 text-2xl font-bold text-indigo-600">
          <?= number_format($averageGrade, 2) ?>
        </p>
      </div>

      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Lëndë të vlerësuara</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">
          <?= $subjectsCount ?>
        </p>
      </div>

      <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-sm text-gray-500">Nota më e lartë</p>
        <p class="mt-2 text-2xl font-bold text-green-600">
          <?= $bestGrade ?>
        </p>
      </div>
    </div>

    <section>
      <h2 class="text-sm font-semibold text-gray-700 mb-4">
        📘 Notat sipas lëndës
      </h2>

      <div class="overflow-hidden rounded-xl bg-white shadow">

        <?php if (empty($grades)): ?>
          <div class="p-6 text-center text-gray-500 text-sm">
            Nuk ka nota të regjistruara ende.
          </div>
        <?php else: ?>
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lënda</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Mësuesi</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nota</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Koment</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php foreach ($grades as $row): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4 text-sm text-gray-900">
                    <?= htmlspecialchars($row['subject_name']) ?>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500">
                    <?= htmlspecialchars($row['teacher_name']) ?>
                  </td>
                  <td class="px-6 py-4 text-sm font-semibold">
                    <span class="<?= $row['grade'] >= 4 ? 'text-green-600' : ($row['grade'] >= 3 ? 'text-yellow-600' : 'text-red-600') ?>">
                      <?= $row['grade'] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500">
                    <?= $row['comment'] ? htmlspecialchars($row['comment']) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
</body>
</html>
