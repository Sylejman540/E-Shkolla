<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$schoolId) die("Aksesi i ndaluar.");

// --- LOGJIKA E PAGINIMIT ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Marrim student_id
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $studentId = $stmt->fetchColumn();

    // 2. Marrim class_id
    $stmt = $pdo->prepare("SELECT class_id FROM student_class WHERE student_id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $classId = $stmt->fetchColumn();

    $assignments = [];
    $totalAssignments = 0;

    if ($classId) {
        // 3. SQL për KPI (Gjithsej detyrat aktive që nuk kanë kaluar afatin +1 ditë)
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM assignments 
            WHERE class_id = ? AND status = 'active' 
            AND due_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $countStmt->execute([$classId]);
        $totalAssignments = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalAssignments / $limit);

        // 4. SQL kryesore me Paginim dhe Filtër Afati
        // Shënim: due_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
        // kjo lejon detyrën të qëndrojë në tabelë deri në 24 orë pasi ka kaluar afati.
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as teacher_name
            FROM assignments a
            LEFT JOIN teachers t ON a.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE a.class_id = ? 
              AND a.school_id = ? 
              AND a.status = 'active'
              AND a.due_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            ORDER BY a.due_date ASC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute([$classId, $schoolId]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Gabim teknik: " . $e->getMessage());
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
ob_start();
?>

<div class="px-4 py-8 max-w-7xl mx-auto">
    
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Detyra Aktive</dt>
            <dd class="mt-1 text-3xl font-bold text-indigo-600"><?= $totalAssignments ?></dd>
            <p class="mt-2 text-xs text-gray-400">Përfshirë detyrat me afat sot</p>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6 text-center flex flex-col justify-center">
            <dt class="text-sm font-medium text-gray-500 truncate">Statusi i Klasës</dt>
            <dd class="mt-1">
                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase">Aktiv</span>
            </dd>
        </div>

        <div class="bg-white shadow rounded-2xl border border-gray-100 p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">Data e Sotme</dt>
            <dd class="mt-1 text-2xl font-bold text-gray-900"><?= date('d/m/Y') ?></dd>
            <p class="mt-2 text-xs text-indigo-500 font-medium italic">Paç fat në studime!</p>
        </div>
    </div>

    <div class="bg-white shadow rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Detyra & Përshkrimi</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Mësuesi</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Afati</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="3" class="p-12 text-center text-gray-400 italic">Nuk ka detyra aktive. ✨</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): 
                            $dueDate = strtotime($a['due_date']);
                            $isUrgent = ($dueDate - time()) < 86400;
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?= e($a['title']) ?></div>
                                    <div class="text-xs text-gray-500 mt-1 break-words max-w-xs sm:max-w-md lg:max-w-xl">
                                        <?= e($a['description']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= e($a['teacher_name'] ?? 'Admin') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?= $isUrgent ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' ?>">
                                        <?= date('d/m/Y', $dueDate) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


        <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-100">
            <div class="text-sm text-gray-500">
                Faqja <span class="font-bold"><?= $page ?></span> nga <?= $totalPages ?>
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Para</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Pas</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>