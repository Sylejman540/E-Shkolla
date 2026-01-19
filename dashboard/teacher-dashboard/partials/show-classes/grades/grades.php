<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../../db.php';

// 1. Siguria & Validimi i Sesionit
$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$schoolId || !$teacherId) {
    die('Sesion i pavlefshëm.');
}

// 2. Trajtimi i POST (Ruajtja e Notës)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $studentId = (int) $_POST['student_id'];
    $classId   = (int) $_POST['class_id'];
    $subjectId = (int) $_POST['subject_id'];
    $grade     = filter_var($_POST['grade'], FILTER_VALIDATE_INT);
    $comment   = trim(filter_var($_POST['comment'], FILTER_SANITIZE_STRING));

    // Validimi i rrezes së notës (1-5)
    if ($grade === false || $grade < 1 || $grade > 5) {
        $_SESSION['error'] = "Nota duhet të jetë midis 1 dhe 5.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO grades (school_id, teacher_id, student_id, class_id, subject_id, grade, comment)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    grade = VALUES(grade), 
                    comment = VALUES(comment),
                    updated_at = NOW()");
            
            $stmt->execute([$schoolId, $teacherId, $studentId, $classId, $subjectId, $grade, $comment]);
            $_SESSION['success'] = "Nota u ruajt me sukses!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Gabim gjatë ruajtjes.";
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// 3. Marrja e të dhënave (Optimizuar)
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

// Statistikat
$stmt = $pdo->prepare("
    SELECT 
        ROUND(AVG(grade), 2) as avg_grade, 
        MAX(grade) as max_grade, 
        MIN(grade) as min_grade,
        COUNT(CASE WHEN grade IS NULL THEN 1 END) as no_grade
    FROM grades WHERE class_id = ? AND subject_id = ?");
$stmt->execute([$classId, $subjectId]);
$stats = $stmt->fetch();

// Lista e studentëve dhe notat ekzistuese
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, g.grade, g.comment 
    FROM student_class sc 
    JOIN students s ON s.student_id = sc.student_id 
    LEFT JOIN grades g ON g.student_id = s.student_id AND g.subject_id = ?
    WHERE sc.class_id = ? 
    ORDER BY s.name ASC");
$stmt->execute([$subjectId, $classId]);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Menaxhimi i Notave</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fade-out { from { opacity: 1; } to { opacity: 0; } }
        .toast { animation: fade-out 1s ease 3s forwards; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">

<?php if (isset($_SESSION['success'])): ?>
    <div class="toast fixed top-5 right-5 z-50 bg-green-600 text-white px-6 py-3 rounded-lg shadow-xl">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>        
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notat: <?= htmlspecialchars($subjectName ?? 'Lënda') ?></h1>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-400 uppercase">Mesatarja</p>
                    <p class="text-2xl font-bold text-indigo-600"><?= $stats['avg_grade'] ?: '0.00' ?></p>
                </div>
                </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Nxënësi</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Nota (1-5)</th>
                        <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Koment</th>
                        <th class="px-6 py-4 text-right">Veprimi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php foreach ($students as $student): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <form method="POST" onsubmit="return handleFormSubmit(this);">
                            <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                            
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <?= htmlspecialchars($student['name']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <input type="number" name="grade" min="1" max="5" 
                                    value="<?= $student['grade'] ?>"
                                    class="w-20 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none font-bold <?= $student['grade'] == 1 ? 'text-red-500' : '' ?>">
                            </td>
<td class="px-6 py-4 whitespace-nowrap">
                        <input type="text" name="comment" 
                            value="<?= htmlspecialchars($student['comment'] ?? '') ?>"
                            placeholder="Shto një shënim..."
                            class="w-full min-w-[200px] px-3 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-white/10 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </td>
                            <td class="px-6 py-4 text-right">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95">
                                    Ruaj
                                </button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
// Kjo siguron që kodi i mësipërm të shfaqet brenda strukturës tuaj kryesore
require_once __DIR__ . '/../index.php'; 
?>
<script>
function handleFormSubmit(form) {
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '...';
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    return true;
}
</script>
</body>
</html>