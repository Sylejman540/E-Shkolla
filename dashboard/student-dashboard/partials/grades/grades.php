<?php 
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

// 1. AUTHENTICATION & ROLE ENFORCEMENT
$userId   = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

// Only students should see this view
if (!$userId || $userRole !== 'student' || !$schoolId) {
    header("Location: /E-Shkolla/login");
    exit();
}

try {
    // 2. FETCH STUDENT ID WITH STRICT SCHOOL ISOLATION
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $studentId = $stmt->fetchColumn();

    if (!$studentId) {
        error_log("Unauthorized Access Attempt: User $userId for School $schoolId");
        die('Studenti nuk u gjet.');
    }

    // 3. FETCH GRADES WITH REINFORCED ISOLATION
    // We join 'users' to get teacher name, but ensure the grade itself belongs to the school
    $stmt = $pdo->prepare("
        SELECT
            g.grade,
            g.comment,
            g.created_at,
            sub.subject_name,
            u.name AS teacher_name
        FROM grades g
        JOIN subjects sub ON sub.id = g.subject_id
        JOIN teachers t ON t.id = g.teacher_id
        JOIN users u ON u.id = t.user_id
        WHERE g.student_id = ?
          AND g.school_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$studentId, $schoolId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error in grades.php: " . $e->getMessage());
    die("Ndodhi një gabim teknik. Ju lutem provoni përsëri.");
}

// Statistics calculation
$totalEntries = count($grades);
$bestGrade = 0;
$totalSum = 0;

foreach ($grades as $g) {
    $currentGrade = (int)$g['grade'];
    $totalSum += $currentGrade;
    if ($currentGrade > $bestGrade) $bestGrade = $currentGrade;
}

$averageGrade = $totalEntries ? round($totalSum / $totalEntries, 2) : 0;

// XSS Protection helper
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
        
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Notat e Mia</h1>
                <p class="text-sm text-gray-500">Pasqyra e vlerësimeve dhe performanca akademike.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                    Viti Akademik 2025/26
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
            <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Mesatarja e Përgjithshme</dt>
                <dd class="mt-1 text-3xl font-bold text-indigo-600"><?= number_format($averageGrade, 2) ?></dd>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?= ($averageGrade / 5) * 100 ?>%"></div>
                </div>
            </div>

            <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Vlerësime Totale</dt>
                <dd class="mt-1 text-3xl font-bold text-gray-900"><?= $totalEntries ?></dd>
                <p class="mt-2 text-xs text-gray-400">Nota të regjistruara këtë semestër</p>
            </div>

            <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Nota më e Lartë</dt>
                <dd class="mt-1 text-3xl font-bold text-green-600"><?= $bestGrade ?></dd>
                <p class="mt-2 text-xs text-gray-400">Suksesi yt maksimal</p>
            </div>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Detajet e Vlerësimit</h2>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($grades)): ?>
                    <div class="p-12 text-center">
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nuk ka nota</h3>
                        <p class="mt-1 text-sm text-gray-500">Mësuesit nuk kanë hedhur ende asnjë vlerësim.</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lënda</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Mësuesi</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Nota</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data & Komenti</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($grades as $row): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <?= e($row['subject_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= e($row['teacher_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php 
                                            $gradeNum = (int)$row['grade'];
                                            $colorClass = 'bg-red-100 text-red-700';
                                            if ($gradeNum >= 4) $colorClass = 'bg-green-100 text-green-700';
                                            elseif ($gradeNum >= 3) $colorClass = 'bg-yellow-100 text-yellow-700';
                                        ?>
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full font-bold <?= $colorClass ?>">
                                            <?= $gradeNum ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="font-medium text-gray-400 text-[10px] uppercase">
                                            <?= date('d M, Y', strtotime($row['created_at'])) ?>
                                        </div>
                                        <div class="italic text-gray-600">
                                            <?= $row['comment'] ? '"' . e($row['comment']) . '"' : '—' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>