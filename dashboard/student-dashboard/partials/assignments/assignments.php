<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/* =========================
   1. AUTH & SESSION CHECK
========================= */
$userId   = $_SESSION['user']['id'] ?? null;
$userRole = $_SESSION['user']['role'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || $userRole !== 'student' || !$schoolId) {
    header("Location: /login.php");
    exit();
}

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

try {
    /* =========================
       2. GET STUDENT & CLASS ID
    ========================= */
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die("Gabim: Profili i studentit nuk u gjet.");
    }

    $stmt = $pdo->prepare("SELECT class_id FROM student_class WHERE student_id = ? LIMIT 1");
    $stmt->execute([$student['student_id']]);
    $classId = $stmt->fetchColumn();

    $assignments = [];
    if (!$classId) {
        $info_message = "Nuk jeni i regjistruar në asnjë klasë.";
    } else {
        /* =========================
           3. FETCH ASSIGNMENTS
        ========================= */
        $query = "
        SELECT 
            a.id, 
            a.title, 
            a.description, 
            a.due_date,
            GROUP_CONCAT(DISTINCT sub.subject_name ORDER BY sub.subject_name SEPARATOR ', ') AS subject_names,
            u.name AS teacher_name
        FROM assignments a
        INNER JOIN users u ON u.id = a.teacher_id
        LEFT JOIN class_subject cs ON cs.class_id = a.class_id
        LEFT JOIN subjects sub ON sub.id = cs.subject_id
        WHERE a.class_id = :class_id
        AND a.school_id = :school_id
        GROUP BY a.id, a.title, a.description, a.due_date, u.name
        ORDER BY a.due_date ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':class_id'  => (int)$classId,
            ':school_id' => (int)$schoolId
        ]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log('Assignments student view error: ' . $e->getMessage());
    die('Ndodhi një gabim teknik. Ju lutem provoni përsëri.');
}


ob_start();
?>

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">📚 Detyrat e Mia</h1>
        <p class="text-sm text-gray-500">Lista e detyrave aktive për klasën tuaj.</p>
    </div>

    <?php if (isset($info_message)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <p class="text-blue-700"><?= e($info_message) ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <?php if (empty($assignments)): ?>
                <div class="p-12 text-center text-gray-400">
                    Nuk ka detyra aktive për momentin.
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lënda</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Detyra</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Mësuesi</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Afati</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($assignments as $a): 
                            $dueDateTimestamp = strtotime($a['due_date']);
                            $isOverdue = $dueDateTimestamp < time();
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-600">
                                    <?php 
                                        // FIX: Changed 'subject_name' to 'subject_names' to match your SQL alias
                                        echo e($a['subject_names'] ?: 'Përgjithshme'); 
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?= e($a['title']) ?></div>
                                    <div class="text-xs text-gray-400"><?= e($a['description']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= e($a['teacher_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?= $isOverdue ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' ?>">
                                        <?= date('d/m/Y', $dueDateTimestamp) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>