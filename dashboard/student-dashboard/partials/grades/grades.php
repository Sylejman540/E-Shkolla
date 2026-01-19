<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$schoolId) {
    header("Location: /login.php");
    exit;
}

// 1. Merr ID-në e studentit
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
$stmt->execute([$userId, $schoolId]);
$studentId = $stmt->fetchColumn();

if (!$studentId) {
    die('Studenti nuk u gjet.');
}

// 2. Merr notat me të dhënat e lëndës dhe mësuesit
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

// Statistikat
$totalEntries = count($grades);
$bestGrade = 0;
$totalSum = 0;

foreach ($grades as $g) {
    $totalSum += $g['grade'];
    if ($g['grade'] > $bestGrade) $bestGrade = $g['grade'];
}

$averageGrade = $totalEntries ? round($totalSum / $totalEntries, 2) : 0;

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
            <div class="bg-white overflow-hidden shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Mesatarja e Përgjithshme</dt>
                <dd class="mt-1 text-3xl font-bold text-indigo-600"><?= number_format($averageGrade, 2) ?></dd>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?= ($averageGrade / 5) * 100 ?>%"></div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Vlerësime Totale</dt>
                <dd class="mt-1 text-3xl font-bold text-gray-900"><?= $totalEntries ?></dd>
                <p class="mt-2 text-xs text-gray-400">Nota të regjistruara këtë semestër</p>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-2xl border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500 truncate">Nota më e Lartë</dt>
                <dd class="mt-1 text-3xl font-bold text-green-600"><?= $bestGrade ?></dd>
                <p class="mt-2 text-xs text-gray-400">Suksesi yt maksimal</p>
            </div>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Detajet e Vlerësimit</h2>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($grades)): ?>
                    <div class="p-12 text-center">
                        <div class="mx-auto h-12 w-12 text-gray-400">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Nuk ka nota</h3>
                        <p class="mt-1 text-sm text-gray-500">Mësuesit nuk kanë hedhur ende asnjë vlerësim.</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Lënda</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Mësuesi</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Nota</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data & Komenti</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($grades as $row): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <?= htmlspecialchars($row['subject_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?= htmlspecialchars($row['teacher_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php 
                                            $colorClass = 'bg-red-100 text-red-700';
                                            if ($row['grade'] >= 4) $colorClass = 'bg-green-100 text-green-700';
                                            elseif ($row['grade'] >= 3) $colorClass = 'bg-yellow-100 text-yellow-700';
                                        ?>
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full font-bold <?= $colorClass ?>">
                                            <?= $row['grade'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="font-medium text-gray-400 text-[10px] uppercase">
                                            <?= date('d M, Y', strtotime($row['created_at'])) ?>
                                        </div>
                                        <div class="italic text-gray-600">
                                            <?= $row['comment'] ? '"' . htmlspecialchars($row['comment']) . '"' : '—' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>