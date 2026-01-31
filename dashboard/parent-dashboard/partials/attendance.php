<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'parent') {
    header('Location: /login.php');
    exit;
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

try {
    // 1. Get Parent ID
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int) $stmt->fetchColumn();

    if (!$parentId) die('Profili i prindit nuk u gjet');

    // 2. Get Children
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, c.grade
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        JOIN classes c ON s.class_id = c.id
        WHERE 
            ps.parent_id = ?
            AND s.school_id = ?
            AND s.status = 'active'
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);


    if (!$children) die('Nuk ka fëmijë të lidhur');

    $studentId = (int) ($_GET['student_id'] ?? $children[0]['student_id']);

    // Find current student info
    $currentStudent = null;
    foreach ($children as $c) {
        if ((int)$c['student_id'] === $studentId) {
            $currentStudent = $c;
            break;
        }
    }
    if (!$currentStudent) die('Akses i paautorizuar');

    // 3. Fetch Last 7 Days Attendance
    $stmt = $pdo->prepare("
        SELECT 
            lesson_date, 
            COUNT(*) AS total_hours, 
            SUM(present) AS present_count, 
            SUM(missing) AS missing_count
        FROM attendance
        WHERE student_id = ? AND school_id = ?
          AND lesson_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY lesson_date
        ORDER BY lesson_date DESC
    ");
    $stmt->execute([$studentId, $schoolId]);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Day names translation
    $daysAl = [
        'Monday' => 'E hënë', 'Tuesday' => 'E martë', 'Wednesday' => 'E mërkurë',
        'Thursday' => 'E enjte', 'Friday' => 'E premte', 'Saturday' => 'E shtunë', 'Sunday' => 'E diel'
    ];

} catch (Exception $e) {
    die("<div class='p-6 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6 pb-12 px-4 text-sm font-normal text-slate-600">
    
    <div class="bg-white rounded-[24px] border border-slate-100 shadow-sm p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-indigo-50 rounded-full opacity-40"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Prezenca Ditore 🗓️</h2>
                <p class="text-slate-400 text-xs mt-0.5">
                    Klasa: <span class="font-bold text-slate-600"><?= htmlspecialchars($currentStudent['grade']) ?></span> | 
                    Nxënësi: <span class="font-bold text-indigo-500"><?= htmlspecialchars($currentStudent['name']) ?></span>
                </p>
            </div>
            
            <?php if (count($children) > 1): ?>
            <div class="flex gap-1.5 p-1 bg-slate-50 rounded-xl border border-slate-100">
                <?php foreach ($children as $child): ?>
                    <a href="?student_id=<?= $child['student_id'] ?>" 
                       class="px-4 py-1.5 rounded-lg text-[10px] font-bold transition-all <?= (int)$child['student_id'] === $studentId ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-400 hover:text-slate-600' ?>">
                        <?= htmlspecialchars($child['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
        $weekTotal = array_sum(array_column($attendanceData, 'total_hours'));
        $weekMissing = array_sum(array_column($attendanceData, 'missing_count'));
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-indigo-50/50 border border-indigo-100 rounded-2xl p-4">
            <p class="text-[10px] uppercase font-bold text-indigo-400 tracking-wider">Totale (7 ditë)</p>
            <p class="text-xl font-black text-indigo-600"><?= $weekTotal ?> <span class="text-xs font-normal">orë</span></p>
        </div>
        <div class="bg-rose-50/50 border border-rose-100 rounded-2xl p-4">
            <p class="text-[10px] uppercase font-bold text-rose-400 tracking-wider">Mungesa</p>
            <p class="text-xl font-black text-rose-600"><?= $weekMissing ?> <span class="text-xs font-normal">orë</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <?php if (!empty($attendanceData)): ?>
            <?php foreach ($attendanceData as $day): 
                $dayNameEn = date('l', strtotime($day['lesson_date']));
                $dayNameAl = $daysAl[$dayNameEn] ?? $dayNameEn;
                $hasAbsence = $day['missing_count'] > 0;
            ?>
                <div class="bg-white rounded-[20px] border border-slate-100 p-5 shadow-sm flex flex-col justify-between group transition-hover hover:border-indigo-100">
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="px-2 py-1 bg-slate-50 text-slate-500 text-[9px] font-bold uppercase rounded-lg border border-slate-100">
                                <?= $dayNameAl ?>
                            </span>
                            <div class="h-1.5 w-1.5 rounded-full <?= $hasAbsence ? 'bg-rose-500' : 'bg-emerald-500' ?>"></div>
                        </div>

                        <h4 class="font-bold text-slate-800 text-sm leading-snug">
                            <?= date('d M, Y', strtotime($day['lesson_date'])) ?>
                        </h4>
                        
                        <div class="mt-4 space-y-2">
                            <div class="flex justify-between text-[11px]">
                                <span class="text-slate-400">Orë të planifikuara:</span>
                                <span class="font-bold text-slate-700"><?= $day['total_hours'] ?></span>
                            </div>
                            <div class="w-full bg-slate-50 h-1 rounded-full overflow-hidden">
                                <?php $percent = ($day['total_hours'] > 0) ? ($day['present_count'] / $day['total_hours']) * 100 : 0; ?>
                                <div class="h-full bg-indigo-400" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-50 flex items-center justify-between">
                        <div class="flex gap-3">
                            <div class="flex flex-col">
                                <span class="text-[8px] uppercase font-bold text-slate-300">Prezent</span>
                                <span class="text-[11px] font-bold text-emerald-500"><?= $day['present_count'] ?> orë</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[8px] uppercase font-bold text-slate-300">Mungesë</span>
                                <span class="text-[11px] font-bold text-rose-500"><?= $day['missing_count'] ?> orë</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold uppercase <?= $hasAbsence ? 'text-rose-500' : 'text-emerald-500' ?>">
                            <?= $hasAbsence ? 'Me Mungesa' : 'I rregullt' ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-16 bg-white rounded-[32px] border border-dashed border-slate-200 text-center">
                <p class="text-slate-400 text-xs italic">Nuk u gjet asnjë rekord i prezencës për 7 ditët e fundit.</p>
            </div>
        <?php endif; ?>
    </div>

    <p class="text-center text-[10px] text-slate-400 italic">
        Sistemi shfaq vetëm të dhënat për 7 ditët e fundit kalendarike.
    </p>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>