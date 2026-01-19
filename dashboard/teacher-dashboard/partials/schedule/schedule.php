<?php


if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$userId = (int) ($_SESSION['user']['id'] ?? 0);
$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);

// Get actual teacher_id
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$today     = strtolower(date('l')); // p.sh. "monday"

if (!$teacherId || !$schoolId) {
    die('Gabim: Sesioni ka skaduar.');
}

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$teacherId = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT cs.*, c.grade AS class_name, s.name AS subject_name
    FROM class_schedule cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ? AND cs.school_id = ?
");
$stmt->execute([$teacherId, $schoolId]);

$scheduleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);



$dayMap = [
    'monday'    => 'E Hënë',
    'tuesday'   => 'E Martë',
    'wednesday' => 'E Mërkurë',
    'thursday'  => 'E Enjte',
    'friday'    => 'E Premte',
];

// Funksion për ngjyrat dhe ikonat
function getSubjectStyle($subject) {
    $subject = mb_strtolower($subject);
    if (str_contains($subject, 'matematik')) return ['bg' => 'bg-orange-50', 'border' => 'border-orange-500', 'text' => 'text-orange-700', 'icon' => '📐'];
    if (str_contains($subject, 'gjuh')) return ['bg' => 'bg-blue-50', 'border' => 'border-blue-500', 'text' => 'text-blue-700', 'icon' => '📘'];
    if (str_contains($subject, 'biologji') || str_contains($subject, 'kimi')) return ['bg' => 'bg-green-50', 'border' => 'border-green-500', 'text' => 'text-green-700', 'icon' => '🧪'];
    if (str_contains($subject, 'fizik')) return ['bg' => 'bg-purple-50', 'border' => 'border-purple-500', 'text' => 'text-purple-700', 'icon' => '⚛️'];
    return ['bg' => 'bg-slate-50', 'border' => 'border-slate-400', 'text' => 'text-slate-700', 'icon' => '📝'];
}

$grouped = [];
foreach ($scheduleItems as $item) {
    $hour = (int)date('H', strtotime($item['start_time']));
    $block = ($hour < 12) ? 'Mëngjes' : (($hour < 17) ? 'Pasdite' : 'Mbrëmje');
    $grouped[strtolower($item['day'])][$block][] = $item;
}

ob_start();
?>

<div class="space-y-6 max-w-full overflow-hidden">
    <div class="flex items-center justify-between print:hidden">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Orari Javor</h1>
            <p class="text-sm text-slate-500 italic">Sot është: <span class="font-bold text-indigo-600"><?= $dayMap[$today] ?? 'Fundjavë' ?></span></p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-slate-50 transition-all shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Printo
            </button>
        </div>
    </div>

    <div class="hidden lg:block bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-3xl shadow-xl overflow-hidden print:border-none print:shadow-none">
        <div class="grid grid-cols-5 divide-x divide-slate-100 dark:divide-white/5 bg-slate-50 dark:bg-white/5 border-b dark:border-white/10">
            <?php foreach ($dayMap as $eng => $label): 
                $isToday = ($eng === $today); ?>
                <div class="px-4 py-4 text-center <?= $isToday ? 'bg-indigo-50/50 dark:bg-indigo-500/10' : '' ?>">
                    <span class="text-[11px] font-black uppercase tracking-widest <?= $isToday ? 'text-indigo-600' : 'text-slate-400' ?>">
                        <?= $label ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-5 divide-x divide-slate-100 dark:divide-white/5 min-h-[650px]">
            <?php foreach (array_keys($dayMap) as $engDay): 
                $isToday = ($engDay === $today); ?>
                <div class="p-3 space-y-6 <?= $isToday ? 'bg-indigo-50/20 dark:bg-indigo-500/5 ring-2 ring-inset ring-indigo-500/20' : '' ?>">
                    <?php if (empty($grouped[$engDay])): ?>
                        <div class="h-full flex items-center justify-center opacity-20 italic text-xs">Pushim</div>
                    <?php else: ?>
                        <?php foreach (['Mëngjes', 'Pasdite', 'Mbrëmje'] as $blockName): ?>
                            <?php if (!empty($grouped[$engDay][$blockName])): ?>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-2">
                                        <span class="h-[1px] flex-1 bg-slate-100 dark:bg-white/5"></span>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= $blockName ?></span>
                                        <span class="h-[1px] flex-1 bg-slate-100 dark:bg-white/5"></span>
                                    </div>
                                    <?php foreach ($grouped[$engDay][$blockName] as $class): 
                                        $style = getSubjectStyle($class['subject_name']); ?>
                                        <div class="group relative bg-white dark:bg-slate-800 border-l-4 <?= $style['border'] ?> rounded-xl p-3 shadow-sm hover:shadow-md transition-all border border-slate-100 dark:border-white/5">
                                            <div class="flex justify-between items-start mb-1">
                                                <span class="text-[10px] font-bold <?= $style['text'] ?> bg-opacity-10">
                                                    <?= date('H:i', strtotime($class['start_time'])) ?>
                                                </span>
                                                <span><?= $style['icon'] ?></span>
                                            </div>
                                            <div class="font-bold text-slate-800 dark:text-white text-sm truncate">
                                                <?= htmlspecialchars($class['subject_name'] ?? '--') ?>
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                                                <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-width="2"/></svg>
                                                Klasa <?= htmlspecialchars($class['class_name'] ?? '--') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lg:hidden space-y-4">
        <?php foreach ($dayMap as $engDay => $dayLabel): 
            $isToday = ($engDay === $today); ?>
            <details class="group bg-white dark:bg-slate-800 rounded-2xl border <?= $isToday ? 'border-indigo-500 ring-4 ring-indigo-500/10' : 'border-slate-100 dark:border-white/5' ?> overflow-hidden transition-all" <?= $isToday ? 'open' : '' ?>>
                <summary class="flex items-center justify-between p-4 cursor-pointer list-none">
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full <?= $isToday ? 'bg-indigo-500 animate-pulse' : 'bg-slate-300' ?>"></span>
                        <h3 class="font-bold text-slate-900 dark:text-white"><?= $dayLabel ?></h3>
                        <?php if($isToday): ?>
                            <span class="text-[10px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-black uppercase">Sot</span>
                        <?php endif; ?>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                
                <div class="p-4 pt-0 space-y-3 border-t border-slate-50 dark:border-white/5">
                    <?php if (empty($grouped[$engDay])): ?>
                        <p class="text-xs text-slate-400 italic py-4 text-center">Nuk ka mësim.</p>
                    <?php else: ?>
                        <?php foreach (['Mëngjes', 'Pasdite', 'Mbrëmje'] as $blockName): ?>
                            <?php if (!empty($grouped[$engDay][$blockName])): ?>
                                <?php foreach ($grouped[$engDay][$blockName] as $class): 
                                    $style = getSubjectStyle($class['subject_name']); ?>
                                    <div class="flex items-center gap-4 p-3 bg-slate-50 dark:bg-white/5 rounded-xl">
                                        <div class="text-center min-w-[55px]">
                                            <span class="block text-xs font-black text-slate-900 dark:text-white"><?= date('H:i', strtotime($class['start_time'])) ?></span>
                                            <span class="block text-[10px] text-slate-400 font-medium"><?= date('H:i', strtotime($class['end_time'])) ?></span>
                                        </div>
                                        <div class="w-1 h-8 rounded-full <?= $style['border'] ?> bg-current"></div>
                                        <div class="flex-1">
                                            <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                <?= $style['icon'] ?> <?= htmlspecialchars($class['subject_name']) ?>
                                            </div>
                                            <div class="text-xs text-slate-500 italic">Klasa <?= htmlspecialchars($class['class_name']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    .dark { color-scheme: light; }
    main { padding: 0 !important; margin: 0 !important; }
    details { display: block !important; border: 1px solid #eee !important; }
    summary { pointer-events: none; }
    summary svg { display: none; }
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>