<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Sigurohu që këto paths janë të sakta sipas strukturës tënde
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

/* 2. Merr klasat ku është i regjistruar ky nxënës */
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

<!DOCTYPE html>
<html lang="sq" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orari Im | E-Shkolla</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/E-Shkolla/images/icon.png" type="image/png">
</head>
<body class="h-full antialiased text-slate-900">
            
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-800">Orari Im Mësimor</h1>
                <p class="text-slate-500 text-sm">Klikoni mbi klasën për të parë detajet e orarit javor.</p>
            </div>

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Viti Akademik</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Klasa</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Veprimi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (!empty($classes)): ?>
                            <?php foreach ($classes as $row): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-700">
                                        <?= htmlspecialchars($row['academic_year']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        Klasa: <?= htmlspecialchars($row['grade']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button onclick="toggleSchedule(<?= (int)$row['id'] ?>)" 
                                                class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-600 hover:bg-blue-100 font-semibold rounded-lg transition-colors">
                                            Shiko orarin
                                        </button>
                                    </td>
                                </tr>

                                <tr id="schedule-<?= (int)$row['id'] ?>" class="hidden bg-slate-50">
                                    <td colspan="3" class="px-4 py-6">
                                        <?php
                                            // Merr orarin specifik për këtë klasë
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

                                        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                                            <table class="min-w-full divide-y divide-slate-200">
                                                <thead class="bg-slate-50">
                                                    <tr>
                                                        <th class="px-3 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Ora</th>
                                                        <?php 
                                                        $daysSq = ['monday' => 'E Hënë', 'tuesday' => 'E Martë', 'wednesday' => 'E Mërkurë', 'thursday' => 'E Enjte', 'friday' => 'E Premte'];
                                                        foreach ($daysSq as $day): ?>
                                                            <th class="px-3 py-3 text-center text-[10px] font-bold text-slate-400 uppercase"><?= $day ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <?php if (!empty($grid)): ?>
                                                        <?php foreach ($grid as $time => $days): ?>
                                                            <tr>
                                                                <td class="px-3 py-4 whitespace-nowrap text-xs font-bold text-slate-700 bg-slate-50/50"><?= $time ?></td>
                                                                <?php foreach (array_keys($daysSq) as $dayCode): ?>
                                                                    <td class="px-3 py-4 text-center">
                                                                        <?php if (isset($days[$dayCode])): ?>
                                                                            <div class="text-sm font-bold text-blue-700 leading-tight">
                                                                                <?= htmlspecialchars($days[$dayCode]['subject_name']) ?>
                                                                            </div>
                                                                            <div class="text-[10px] text-slate-500 mt-1">
                                                                                Prof. <?= htmlspecialchars($days[$dayCode]['teacher_name']) ?>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <span class="text-slate-300">—</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="py-8 text-center text-slate-400 text-sm italic">
                                                                Nuk ka orar të regjistruar për këtë klasë.
                                                            </td>
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
                                <td colspan="3" class="px-6 py-12 text-center">
                                    <div class="text-slate-400 font-medium">Nuk jeni regjistruar në asnjë klasë ende.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>
<script>
function toggleSchedule(classId) {
    const row = document.getElementById('schedule-' + classId);
    if (row.classList.contains('hidden')) {
        // Mbyll të tjerat nëse dëshiron (opsionale)
        document.querySelectorAll('[id^="schedule-"]').forEach(el => el.classList.add('hidden'));
        row.classList.remove('hidden');
    } else {
        row.classList.add('hidden');
    }
}
</script>

</body>
</html>