<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../index.php'; 

require_once __DIR__ . '/../../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
    $teacherId = (int) ($_SESSION['user']['id'] ?? 0);

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $classId   = (int) ($_POST['class_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    if (!$schoolId || !$teacherId || !$studentId || !$classId || !$subjectId) {
        die('Invalid attendance data');
    }

    $present = isset($_POST['present']) ? 1 : 0;
    $missing = isset($_POST['missing']) ? 1 : 0;

    /* ===== 1 HOUR LOCK CHECK ===== */
    $lockStmt = $pdo->prepare("
        SELECT id
        FROM attendance
        WHERE student_id = ?
          AND class_id   = ?
          AND subject_id = ?
          AND teacher_id = ?
          AND created_at >= NOW() - INTERVAL 1 HOUR
        LIMIT 1
    ");
    $lockStmt->execute([
        $studentId,
        $classId,
        $subjectId,
        $teacherId
    ]);

    if ($lockStmt->fetch()) {
        // Already recorded in the last hour
        header("Location: " . $_SERVER['REQUEST_URI'] . "&error=locked");
        exit;
    }

    /* ===== INSERT ATTENDANCE ===== */
    $stmt = $pdo->prepare("
        INSERT INTO attendance
            (school_id, student_id, class_id, subject_id, teacher_id, present, missing)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $schoolId,
        $studentId,
        $classId,
        $subjectId,
        $teacherId,
        $present,
        $missing
    ]);

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


$classId = (int)($_GET['class_id'] ?? 0);

if ($classId <= 0) {
    die('Invalid class ID');
}

$stmt = $pdo->prepare("
    SELECT 
        sc.id AS student_class_id,
        sc.class_id,
        s.student_id,
        s.name,
        s.email,
        s.status
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");

$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h1 class="text-base font-semibold text-gray-900 dark:text-white">Nxënës</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Lista e të gjithë nxënësve në klasë</p>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'locked'): ?>
  <div class="mb-4 rounded-md bg-yellow-100 px-4 py-3 text-sm text-yellow-800">
    Prezenca për këtë nxënës është regjistruar tashmë gjatë orës së fundit.
  </div>
<?php endif; ?>

                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                <thead>
                    <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Emri</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Email</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Veprime</th>
                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0">
                            <span class="sr-only">Edit</span>
                        </th>
                    </tr>
                </thead>
                <?php if(!empty($students)): ?>
                <?php foreach($students as $row): ?>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr>
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0 dark:text-white"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                            <button class="status-toggle px-3 py-1 rounded-full text-xs font-semibold
                                <?= $row['status']==='active'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-600' ?>"
                               
                                data-field="status"
                                data-value="<?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </button>
                
                        </td>   
                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                            <div class="flex gap-2">
                                <form method="post" class="flex gap-2">
                                    <input type="hidden" name="student_id" value="<?= (int)$row['student_id'] ?>">
                                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                                    <input type="hidden" name="subject_id" value="<?= (int)$subjectId ?>">

                                    <button type="submit" name="present" class="px-3 py-1.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200 transition">Prezent</button>

                                    <button type="submit" name="missing" class="px-3 py-1.5 text-xs font-medium rounded-full bg-red-100 text-red-800 hover:bg-red-200 transition">Mungon</button>
                                </form>
                            </div>
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
