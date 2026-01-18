<?php
require_once __DIR__ . '/../../../../db.php';
$classId = $_GET['class_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT cs.*, s.subject_name, t.name as teacher_name 
    FROM class_schedule cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN teachers t ON cs.teacher_id = t.id
    WHERE cs.class_id = ?
    ORDER BY cs.start_time ASC
");
$stmt->execute([$classId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$grid = [];

foreach ($entries as $e) {
    $timeKey = date('H:i', strtotime($e['start_time'])) . ' - ' . date('H:i', strtotime($e['end_time']));
    $grid[$timeKey][$e['day']] = $e;
}
?>

<div class="overflow-x-auto">
    <table class="min-w-full border-separate border-spacing-2">
        <thead>
            <tr>
                <th class="w-24 text-xs font-bold uppercase text-slate-400 text-left px-2">Ora</th>
                <?php foreach($days as $day): ?>
                    <th class="bg-slate-100 dark:bg-gray-700 rounded-lg py-2 px-4 text-sm font-semibold dark:text-white"><?= $day ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($grid)): ?>
                <tr><td colspan="6" class="text-center py-8 text-gray-400">Nuk ka të dhëna për këtë klasë.</td></tr>
            <?php else: ?>
                <?php foreach($grid as $time => $dayEntries): ?>
                    <tr>
                        <td class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400"><?= $time ?></td>
                        <?php foreach($days as $day): ?>
                            <td class="relative group">
                                <?php if(isset($dayEntries[$day])): $item = $dayEntries[$day]; ?>
                                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800 shadow-sm transition hover:shadow-md">
                                        <div class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($item['subject_name']) ?></div>
                                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-indigo-300 mt-1"><?= htmlspecialchars($item['teacher_name']) ?></div>
                                        
                                        <button onclick="deleteScheduleEntry(<?= $item['id'] ?>, <?= $classId ?>)" class="absolute top-1 right-1 hidden group-hover:flex bg-white dark:bg-red-900 h-5 w-5 rounded-full items-center justify-center text-red-500 shadow-sm">
                                            &times;
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="h-16 rounded-xl border-2 border-dashed border-slate-100 dark:border-gray-700 flex items-center justify-center text-slate-300">
                                        <span class="text-xs">+</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>