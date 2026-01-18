<?php
require_once __DIR__ . '/../../../../db.php';
$classId = $_GET['class_id'] ?? 0;

// 1. Marrja e të dhënave me JOIN për të shfaqur emrat në vend të ID-ve
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

// 2. Definimi i ditëve saktësisht siç vijnë nga databaza (shkronja të vogla)
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

// Emrat për shfaqje në tabelë
$dayLabels = [
    'monday' => 'E hënë',
    'tuesday' => 'E martë',
    'wednesday' => 'E mërkurë',
    'thursday' => 'E enjte',
    'friday' => 'E premte'
];

$grid = [];

// 3. Gruponi të dhënat sipas orës dhe ditës
foreach ($entries as $e) {
    $timeKey = date('H:i', strtotime($e['start_time'])) . ' - ' . date('H:i', strtotime($e['end_time']));
    // Sigurohemi që çelësi i ditës të jetë me shkronja të vogla
    $grid[$timeKey][strtolower($e['day'])] = $e;
}
?>

<div class="overflow-x-auto">
    <table class="min-w-full border-separate border-spacing-2">
        <thead>
            <tr>
                <th class="w-24 text-xs font-bold uppercase text-slate-400 text-left px-2">Ora</th>
                <?php foreach($days as $day): ?>
                    <th class="bg-slate-100 dark:bg-gray-700 rounded-lg py-2 px-4 text-sm font-semibold text-slate-700 dark:text-white">
                        <?= $dayLabels[$day] ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($grid)): ?>
                <tr>
                    <td colspan="6" class="text-center py-12">
                        <div class="flex flex-col items-center">
                            <span class="text-slate-300 dark:text-gray-600 text-4xl mb-2">📅</span>
                            <p class="text-gray-400 text-sm italic">Nuk ka orë të caktuara për këtë klasë.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($grid as $time => $dayEntries): ?>
                    <tr>
                        <td class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                            <?= $time ?>
                        </td>
                        <?php foreach($days as $day): ?>
                            <td class="relative group min-w-[140px]">
                                <?php if(isset($dayEntries[$day])): $item = $dayEntries[$day]; ?>
                                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800 shadow-sm transition hover:shadow-md relative">
                                        <div class="text-sm font-bold text-slate-800 dark:text-white truncate">
                                            <?= htmlspecialchars($item['subject_name']) ?>
                                        </div>
                                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-indigo-300 mt-1 truncate">
                                            <?= htmlspecialchars($item['teacher_name']) ?>
                                        </div>
                                        
                                        <button onclick="deleteScheduleEntry(<?= $item['id'] ?>, <?= $classId ?>)" 
                                                class="absolute -top-2 -right-2 hidden group-hover:flex bg-red-500 hover:bg-red-600 h-6 w-6 rounded-full items-center justify-center text-white shadow-lg transition-all scale-90 hover:scale-100">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="h-16 rounded-xl border-2 border-dashed border-slate-100 dark:border-gray-700/50 flex items-center justify-center text-slate-200 dark:text-gray-800 hover:border-indigo-200 dark:hover:border-indigo-900/50 transition-colors">
                                        <span class="text-lg">+</span>
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