<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || $_SESSION['user']['role'] !== 'student' || !$schoolId) {
    header("Location: /login.php");
    exit();
}

// --- PAGINATION LOGIC ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Get student_id
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $studentId = $stmt->fetchColumn();

    if (!$studentId) {
        die("Studenti nuk u gjet.");
    }

    // 2. Get Total Count for Pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id = ? AND school_id = ?");
    $countStmt->execute([$studentId, $schoolId]);
    $totalGrades = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalGrades / $limit);

    // 3. Fetch Grades (Ordered by Subject Name with Pagination)
    $stmt = $pdo->prepare("
        SELECT g.*, sub.subject_name, u.name as teacher_name
        FROM grades g
        LEFT JOIN subjects sub ON g.subject_id = sub.id
        LEFT JOIN teachers t ON g.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE g.student_id = ? AND g.school_id = ?
        ORDER BY sub.subject_name ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$studentId, $schoolId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. KPI Data (Calculated from all grades, not just the paginated ones)
    $kpiStmt = $pdo->prepare("SELECT grade FROM grades WHERE student_id = ? AND school_id = ?");
    $kpiStmt->execute([$studentId, $schoolId]);
    $allGrades = $kpiStmt->fetchAll(PDO::FETCH_COLUMN);

    $totalEntries = count($allGrades);
    $sum = array_sum(array_map('intval', $allGrades));
    $best = $totalEntries ? max(array_map('intval', $allGrades)) : 0;
    $average = $totalEntries ? round($sum / $totalEntries, 2) : 0;

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Ndodhi një gabim teknik.");
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
ob_start();
?>

<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Notat e Mia</h1>
            <p class="text-sm text-gray-500">Pasqyra e vlerësimeve sipas alfabetit.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                Faqja <?= $page ?> nga <?= $totalPages ?: 1 ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Mesatarja e Përgjithshme</dt>
            <dd class="mt-1 text-3xl font-bold text-indigo-600"><?= number_format($average, 2) ?></dd>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?= ($average / 5) * 100 ?>%"></div>
            </div>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Vlerësime Totale</dt>
            <dd class="mt-1 text-3xl font-bold text-gray-900"><?= $totalEntries ?></dd>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Nota më e Lartë</dt>
            <dd class="mt-1 text-3xl font-bold text-green-600"><?= $best ?></dd>
        </div>
    </div>

    <div class="bg-white shadow rounded-2xl border border-gray-100 overflow-hidden">        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th rowspan="2" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase border-r">Lënda</th>
                        <th colspan="4" class="px-4 py-2 text-center text-[10px] font-black text-indigo-500 uppercase border-b border-r bg-indigo-50/30">Perioda 1</th>
                        <th colspan="4" class="px-4 py-2 text-center text-[10px] font-black text-rose-500 uppercase border-b border-r bg-rose-50/30">Perioda 2</th>
                        <th rowspan="2" class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Nota</th>
                    </tr>
                    <tr class="bg-gray-50/50">
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Gojë</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Test</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Aktv</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-blue-500 uppercase border-r">Det</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Gojë</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Test</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-gray-400 uppercase">Aktv</th>
                        <th class="px-2 py-2 text-[9px] text-center font-bold text-blue-500 uppercase border-r">Det</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($grades)): ?>
                        <tr><td colspan="10" class="p-12 text-center text-gray-400 italic">Nuk ka nota në këtë faqe.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grades as $row): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 border-r whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900"><?= e($row['subject_name']) ?></div>
                                    <div class="text-[10px] text-gray-400 italic"><?= e($row['teacher_name']) ?></div>
                                </td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p1_oral'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p1_test'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p1_activity'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm font-bold text-blue-600 border-r"><?= $row['p1_homework'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p2_oral'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p2_test'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm text-gray-600"><?= $row['p2_activity'] ?: '-' ?></td>
                                <td class="px-2 py-4 text-center text-sm font-bold text-blue-600 border-r"><?= $row['p2_homework'] ?: '-' ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php 
                                        $f = (int)$row['grade'];
                                        $c = $f >= 4 ? 'bg-green-100 text-green-700' : ($f >= 2 ? 'bg-indigo-100 text-indigo-700' : 'bg-red-100 text-red-700');
                                    ?>
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full font-bold <?= $c ?>">
                                        <?= $f ?: '-' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <a href="?page=<?= max(1, $page - 1) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Para</a>
                <a href="?page=<?= min($totalPages, $page + 1) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Pas</a>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Duke treguar <span class="font-medium"><?= $offset + 1 ?></span> deri <span class="font-medium"><?= min($offset + $limit, $totalGrades) ?></span> nga <span class="font-medium"><?= $totalGrades ?></span> rezultate
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>