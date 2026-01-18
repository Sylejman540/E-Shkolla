<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$teacherId = $_SESSION['user']['id'] ?? 0;
$schoolId  = $_SESSION['user']['school_id'] ?? 0;

if (!$teacherId || !$schoolId) {
    die('Invalid session data.');
}

$stmt = $pdo->prepare("
    SELECT 
        cs.day,
        cs.start_time,
        cs.end_time,
        c.grade AS class_name,
        s.subject_name
    FROM class_schedule cs
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = 196 AND cs.school_id = 40
    ORDER BY 
        FIELD(cs.day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
        cs.start_time
");
$stmt->execute();
$scheduleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Map days
$dayMap = [
    'monday'    => 'E Hënë',
    'tuesday'   => 'E Martë',
    'wednesday' => 'E Mërkurë',
    'thursday'  => 'E Enjte',
    'friday'    => 'E Premte',
];

// Group by day
$grouped = [];
foreach ($scheduleItems as $item) {
    $grouped[$item['day']][] = $item;
}


ob_start();
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Orari i Mësimit</h1>
            <p class="text-sm text-slate-500">Plani juaj javor i orëve dhe klasave.</p>
        </div>
        <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Printo Orarin
        </button>
    </div>

    <!-- Desktop Grid -->
    <div class="hidden lg:block bg-white border border-slate-100 rounded-3xl shadow-sm overflow-hidden">
        <div class="grid grid-cols-5 divide-x divide-slate-100 border-b border-slate-100 bg-slate-50/50">
            <?php foreach ($dayMap as $eng => $label): ?>
                <div class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">
                    <?= $label ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-5 divide-x divide-slate-100 min-h-[600px] bg-white">
            <?php foreach (array_keys($dayMap) as $engDay): ?>
                <div class="p-3 space-y-3 bg-slate-50/20">
                    <?php foreach ($grouped[$engDay] ?? [] as $class): ?>
                        <div class="bg-white border-l-4 border-blue-600 rounded-xl p-3 shadow-sm ring-1 ring-slate-200/60 hover:shadow-md transition-shadow">
                            <div class="text-[10px] font-bold text-blue-600 uppercase mb-1">
                                <?= date('H:i', strtotime($class['start_time'])) ?> - <?= date('H:i', strtotime($class['end_time'])) ?>
                            </div>
                            <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($class['subject_name']) ?></div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-width="2"/>
                                </svg>
                                Klasa: <?= htmlspecialchars($class['class_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mobile version -->
    <div class="lg:hidden space-y-6">
        <?php foreach ($dayMap as $engDay => $dayLabel): ?>
            <div class="space-y-3">
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest pl-2"><?= $dayLabel ?></h3>
                <?php if (empty($grouped[$engDay])): ?>
                    <div class="bg-slate-100/50 rounded-2xl p-4 text-center text-xs text-slate-400 italic">Pushim</div>
                <?php else: ?>
                    <?php foreach ($grouped[$engDay] as $class): ?>
                        <div class="flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <div class="text-center min-w-[60px]">
                                <span class="block text-sm font-bold text-slate-800"><?= date('H:i', strtotime($class['start_time'])) ?></span>
                                <span class="block text-[10px] text-slate-400 font-medium italic"><?= date('H:i', strtotime($class['end_time'])) ?></span>
                            </div>
                            <div class="h-10 w-[2px] bg-blue-100"></div>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-900"><?= htmlspecialchars($class['subject_name']) ?></div>
                                <div class="text-xs text-slate-500">Klasa <?= htmlspecialchars($class['class_name']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>
