<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../../../db.php';

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$schoolId || !$teacherId) {
    die('Invalid teacher session');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $classId   = (int) ($_POST['class_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    $grade     = $_POST['grade'] ?? null;
    $comment   = $_POST['comment'] ?? null;

    if (!$studentId || !$classId || !$subjectId) {
        die('Invalid grade data');
    }

    $stmt = $pdo->prepare("
        INSERT INTO grades(school_id, teacher_id, student_id, class_id, subject_id, grade, comment)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE grade = VALUES(grade),  comment = VALUES(comment)");

    $stmt->execute([$schoolId, $teacherId, $studentId, $classId, $subjectId, $grade, $comment]);

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

if (!$classId || !$subjectId) {
    die('Missing class or subject');
}

$stmt = $pdo->prepare("
    SELECT
        AVG(grade)         AS avg_grade,
        MAX(grade)         AS max_grade,
        MIN(grade)         AS min_grade,
        SUM(grade IS NULL) AS no_grade_count
    FROM grades
    WHERE school_id = ?
      AND class_id = ?
      AND subject_id = ?");

$stmt->execute([$schoolId, $classId, $subjectId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$avgGrade = $stats['avg_grade'] !== null ? number_format($stats['avg_grade'], 2) : '-';
$maxGrade = $stats['max_grade'] ?? '-';
$minGrade = $stats['min_grade'] ?? '-';
$noGrade  = $stats['no_grade_count'] ?? 0;

$stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
$stmt->execute([$subjectId]);
$subjectName = $stmt->fetchColumn() ?: 'Lënda';

$stmt = $pdo->prepare("SELECT s.student_id, s.name, s.email, s.status FROM student_class sc INNER JOIN students s ON s.student_id = sc.student_id WHERE sc.class_id = ? ORDER BY s.name ASC");

$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT student_id, grade, comment FROM grades WHERE school_id = ? AND class_id = ? AND subject_id = ?");

$stmt->execute([$schoolId, $classId, $subjectId]);

$grades = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $grades[$row['student_id']] = $row;
}
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
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">
            Notat – <?= htmlspecialchars($subjectName) ?>
            </h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
            Statistika për klasën aktuale
            </p>
        </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 mt-4">
        
        <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Mesatarja e notave</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">
            <?= $avgGrade ?>
            </p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nxënës pa notë</p>
            <p class="mt-2 text-2xl font-bold text-indigo-600">
            <?= $noGrade ?>
            </p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nota më e lartë</p>
            <p class="mt-2 text-2xl font-bold text-green-600">
            <?= $maxGrade ?>
            </p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow">
            <p class="text-sm text-gray-500">Nota më e ulët</p>
            <p class="mt-2 text-2xl font-bold text-pink-600">
            <?= $minGrade ?>
            </p>
        </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Emri i nxënësit</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Nota</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Koment</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                            <span class="sr-only">Edit</span>
                        </th>
                    </tr>
                </thead>
                <?php if(!empty($students)): ?>
                <?php foreach($students as $row): ?>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr>
                    <td class="py-4 pr-3 pl-4 text-sm font-medium text-gray-900 sm:pl-0 dark:text-white">
                        <?= htmlspecialchars($row['name']) ?>
                    </td>

                    <td class="py-4 px-3">
                        <form method="POST" class="flex items-center gap-3">
                        <input type="hidden" name="student_id" value="<?= (int)$row['student_id'] ?>">
                        <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
                        <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">

                        <input
                            type="text"
                            name="grade"
                            value="<?= htmlspecialchars($grades[$row['student_id']]['grade'] ?? '') ?>"
                            class="w-20 px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500"
                            placeholder="1–5"
                        />
                    </td>

                    <td class="py-4 px-3">
                        <input
                            type="text"
                            name="comment"
                            value="<?= htmlspecialchars($grades[$row['student_id']]['comment'] ?? '') ?>"
                            class="min-w-[10rem] px-2 py-1 rounded outline-none hover:bg-gray-100 focus:bg-indigo-50 focus:ring-2 focus:ring-indigo-500"
                            placeholder="Koment"
                        />
                    </td>

                    <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            <?= $row['status']==='active'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-600' ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </td>

                    <td class="py-4 pr-4 text-right">
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 font-semibold px-3 py-1.5 text-sm text-white hover:bg-indigo-700">
                            Ruaj
                        </button>
                    </form>
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
</script>

</body>
</html>
