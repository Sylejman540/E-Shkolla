<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$userId = $_SESSION['user']['id'] ?? null;

if (!$userId || $_SESSION['user']['role'] !== 'student') {
    header("Location: /E-Shkolla/login");
    exit();
}

/* 1. Merr ID-në e studentit */
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Të dhënat e nxënësit nuk u gjetën.');
}

$studentId = $student['student_id'];

/* 2. Merr klasat */
$stmt = $pdo->prepare("
    SELECT c.*
    FROM classes c
    JOIN student_class sc ON sc.class_id = c.id
    WHERE sc.student_id = ?
");
$stmt->execute([$studentId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Orari Im Mësimor</h1>
        <p class="text-slate-500 text-sm mt-1">Klikoni "Shiko orarin" për të parë detajet javor të klasës.</p>
    </div>

    <div class="bg-white shadow-sm border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Viti / Klasa</th>
                        <th class="hidden md:table-cell px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Detajet</th>
                        <th class="px-4 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Veprimi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $row): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($row['grade']) ?></div>
                                    <div class="text-xs text-slate-500 md:hidden"><?= htmlspecialchars($row['academic_year']) ?></div>
                                </td>
                                <td class="hidden md:table-cell px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <?= htmlspecialchars($row['academic_year']) ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm">
                                    <button onclick="toggleSchedule(<?= (int)$row['id'] ?>)" 
                                            class="inline-flex items-center px-3 py-2 md:px-4 md:py-2 bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold rounded-xl transition-all active:scale-95">
                                        <span class="sm:inline">Shiko orarin</span>
                                        <svg class="w-5 h-5 sm:ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="9 5l7 7-7 7"></path></svg>
                                    </button>
                                </td>
                            </tr>

                            <tr id="schedule-<?= (int)$row['id'] ?>" class="hidden bg-slate-50/50">
                                <td colspan="3" class="px-2 py-4 md:px-6 md:py-8">
                                    <?php
                                        $stmt = $pdo->prepare("
                                            SELECT cs.day, cs.start_time, cs.end_time, s.subject_name, t.name AS teacher_name
                                            FROM class_schedule cs
                                            JOIN subjects s ON s.id = cs.subject_id
                                            JOIN teachers t ON t.id = cs.teacher_id
                                            WHERE cs.class_id = ?
                                            ORDER BY cs.start_time
                                        ");
                                        $stmt->execute([(int)$row['id']]);
                                        $scheduleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        $grid = [];
                                        $dayMap = [
                                            'Monday' => 'monday', 'E Hënë' => 'monday',
                                            'Tuesday' => 'tuesday', 'E Martë' => 'tuesday',
                                            'Wednesday' => 'wednesday', 'E Mërkurë' => 'wednesday',
                                            'Thursday' => 'thursday', 'E Enjte' => 'thursday',
                                            'Friday' => 'friday', 'E Premte' => 'friday'
                                        ];

                                        foreach ($scheduleData as $lesson) {
                                            $timeKey = date("H:i", strtotime($lesson['start_time'])) . " - " . date("H:i", strtotime($lesson['end_time']));
                                            $normalizedDay = $dayMap[$lesson['day']] ?? strtolower($lesson['day']);
                                            $grid[$timeKey][$normalizedDay] = $lesson;
                                        }
                                    ?>

                                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                                        <table class="min-w-full table-fixed md:table-auto divide-y divide-slate-200">
                                            <thead class="bg-slate-50">
                                                <tr>
                                                    <th class="w-20 md:w-32 px-2 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Ora</th>
                                                    <?php 
                                                    $daysSq = ['monday' => 'Hën', 'tuesday' => 'Mar', 'wednesday' => 'Mër', 'thursday' => 'Enj', 'friday' => 'Pre'];
                                                    foreach ($daysSq as $day): ?>
                                                        <th class="px-2 py-3 text-center text-[10px] font-bold text-slate-400 uppercase"><?= $day ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php if (!empty($grid)): ?>
                                                    <?php foreach ($grid as $time => $days): ?>
                                                        <tr>
                                                            <td class="px-2 py-4 whitespace-nowrap text-[10px] md:text-xs font-bold text-slate-600 bg-slate-50/30"><?= $time ?></td>
                                                            <?php foreach (array_keys($daysSq) as $dayCode): ?>
                                                                <td class="px-1 py-3 md:px-3 text-center">
                                                                    <?php if (isset($days[$dayCode])): ?>
                                                                        <div class="p-1 md:p-2 rounded-lg bg-blue-50/50">
                                                                            <div class="text-[10px] md:text-sm font-black text-blue-700 leading-tight break-words">
                                                                                <?= htmlspecialchars($days[$dayCode]['subject_name']) ?>
                                                                            </div>
                                                                            <div class="hidden md:block text-[9px] text-slate-500 mt-1">
                                                                                <?= htmlspecialchars($days[$dayCode]['teacher_name']) ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-slate-200">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="py-12 text-center text-slate-400 text-sm italic">Nuk ka orar të regjistruar.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-slate-400">Nuk jeni regjistruar në asnjë klasë.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>

<script>
function toggleSchedule(classId) {
    const row = document.getElementById('schedule-' + classId);
    const isHidden = row.classList.contains('hidden');
    
    // Close all other schedules first for a cleaner UI
    document.querySelectorAll('[id^="schedule-"]').forEach(el => el.classList.add('hidden'));
    
    if (isHidden) {
        row.classList.remove('hidden');
    }
}
</script>