<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

/* =========================
   SESSION & BASIC CHECK
========================= */
$userId   = (int)($_SESSION['user']['id'] ?? 0);
$schoolId = (int)($_SESSION['user']['school_id'] ?? 0);

if (!$userId || !$schoolId) {
    die('Gabim: Sesioni ka skaduar.');
}

/* =========================
   GET TEACHER ID
========================= */
$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$userId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    die('Gabim: Mësimdhënësi nuk u gjet.');
}

/* =========================
   DAY SETUP
========================= */
$todayEng = strtolower(date('l'));

$dayMap = [
    'monday'    => 'E Hënë',
    'tuesday'   => 'E Martë',
    'wednesday' => 'E Mërkurë',
    'thursday'  => 'E Enjte',
    'friday'    => 'E Premte',
];

/* =========================
   FETCH SCHEDULE (PERIOD-BASED)
========================= */
$stmt = $pdo->prepare("
    SELECT 
        cs.id,
        cs.day,
        cs.period_number,
        c.grade      AS class_name,
        s.subject_name
    FROM class_schedule cs
    JOIN classes  c ON c.id = cs.class_id
    JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.teacher_id = ? 
      AND cs.school_id  = ?
    ORDER BY 
        FIELD(cs.day,'monday','tuesday','wednesday','thursday','friday'),
        cs.period_number ASC
");
$stmt->execute([$teacherId, $schoolId]);
$scheduleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   KPI PREPARATION (READ-ONLY, PERIOD-BASED)
===================================================== */

// Always defined (no notices)
$totalClasses     = 0;
$totalHours       = 0;
$busiestDayLabel  = 'N/A';
$busiestCount     = 0;

// Total periods in the week
$totalClasses = count($scheduleItems);

// One period = one hour (visual KPI)
$totalHours = $totalClasses;

// Count periods per weekday
$dayCounts = [];

foreach ($scheduleItems as $item) {
    $day = strtolower($item['day']);
    $dayCounts[$day] = ($dayCounts[$day] ?? 0) + 1;
}

// Determine busiest day
if (!empty($dayCounts)) {
    $busiestDayKey   = array_keys($dayCounts, max($dayCounts))[0];
    $busiestCount   = $dayCounts[$busiestDayKey];
    $busiestDayLabel = $dayMap[$busiestDayKey] ?? 'N/A';
}

/* =====================================================
   ORA AKTIVE — PERIOD POINTER (FINAL MVP)
===================================================== */

// Collect ONLY today’s lessons
$todayClasses = [];

foreach ($scheduleItems as $item) {
    if (strtolower($item['day']) === $todayEng) {
        $todayClasses[] = $item;
    }
}

// Determine active period (MVP default)
$activePeriod = null;

if (!empty($todayClasses)) {
    $activePeriod = min(array_column($todayClasses, 'period_number'));
}

// Assign statuses (STRICT)
foreach ($todayClasses as &$lesson) {
    if ($lesson['period_number'] < $activePeriod) {
        $lesson['status'] = 'completed';
    } elseif ($lesson['period_number'] == $activePeriod) {
        $lesson['status'] = 'ongoing';
    } else {
        $lesson['status'] = 'upcoming';
    }
}
unset($lesson);

/* =====================================================
   GROUP BY DAY + SHIFT (FOR UI RENDERING)
===================================================== */

$grouped = [];

foreach ($scheduleItems as $item) {
    $period = (int)$item['period_number'];
    $block  = ($period <= 5) ? 'Mëngjes' : 'Pasdite';
    $grouped[strtolower($item['day'])][$block][] = $item;
}

/* =====================================================
   SUBJECT STYLE HELPER (PURE FUNCTION)
===================================================== */
function getSubjectStyle(string $subject): array {
    $s = mb_strtolower($subject);

    if (str_contains($s, 'matematik')) return ['border'=>'border-orange-500','icon'=>'📐'];
    if (str_contains($s, 'gjuh'))      return ['border'=>'border-blue-500','icon'=>'📘'];
    if (str_contains($s, 'biologji') || str_contains($s, 'kimi'))
        return ['border'=>'border-green-500','icon'=>'🧪'];
    if (str_contains($s, 'fizik'))      return ['border'=>'border-purple-500','icon'=>'⚛️'];

    return ['border'=>'border-slate-400','icon'=>'📝'];
}


/* =========================
   READY FOR VIEW
========================= */
ob_start();
?>

<div class="space-y-6 max-w-full overflow-hidden pb-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Orari Im</h1>
            <p class="text-sm text-slate-500 italic">Mirësevini në modulin e planifikimit.</p>
        </div>
        
        <button onclick="window.print()" class="no-print inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Printo Orarin
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 print:hidden">
        
        <!-- Weekly Load -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-indigo-500/20 text-4xl">⏱</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Ngarkesa Javore</p>
            <div class="mt-2 flex items-end gap-2">
                <span class="text-3xl font-black text-slate-900 dark:text-white">
                    <?= round($totalHours, 1) ?>
                </span>
                <span class="text-sm text-slate-500 mb-1">orë</span>
            </div>
        </div>

        <!-- Total Classes -->
        <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 p-5 shadow-sm">
            <div class="absolute right-4 top-4 text-emerald-500/20 text-4xl">📚</div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Lëndë këtë javë</p>
            <span class="mt-2 block text-3xl font-black text-slate-900 dark:text-white">
                <?= $totalClasses ?>
            </span>
        </div>

        <!-- Busiest Day -->
        <div class="relative overflow-hidden bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-5 shadow-lg text-white sm:col-span-2 lg:col-span-1">
            <div class="absolute right-4 top-4 text-white/30 text-4xl">🔥</div>
            <p class="text-[11px] font-bold uppercase tracking-widest opacity-80">Dita më e ngarkuar</p>
            <div class="mt-2 text-lg font-black">
                <?= $busiestDayLabel ?>
                <span class="opacity-80 font-medium">(<?= $busiestCount ?>)</span>
            </div>
        </div>

    </div>


<div class="space-y-6 max-w-full overflow-hidden pb-10">

    <?php if (!empty($todayClasses)): ?>
    <div class="lg:hidden px-2">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
            </span>
            Axhenda e Sotme (<?= $dayMap[$todayEng] ?>)
        </h3>
        <div class="space-y-3">
            <?php foreach ($todayClasses as $tc): 
                $style = getSubjectStyle($tc['subject_name']);
                $statusColor = [
                    'ongoing' => 'bg-emerald-500 ring-emerald-500/20', 
                    'upcoming' => 'bg-amber-500 ring-amber-500/20', 
                    'completed' => 'bg-slate-300 dark:bg-slate-700 ring-transparent'
                ][$tc['status']];
            ?>
            <div class="relative flex items-center gap-4 p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1 <?= $tc['status'] === 'ongoing' ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-800' ?>"></div>
                <div class="text-center min-w-[55px]">
                    <div class="mt-1 flex justify-center"><span class="h-1.5 w-1.5 rounded-full <?= $statusColor ?> ring-4"></span></div>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white leading-none mb-1"><?= htmlspecialchars($tc['subject_name']) ?></h4>
                    <p class="text-[10px] text-slate-500 font-medium">Klasa <?= htmlspecialchars($tc['class_name']) ?></p>
                </div>
                <span class="text-xl"><?= $style['icon'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl lg:rounded-3xl shadow-xl overflow-hidden print:border-none print:shadow-none mx-2 lg:mx-0">
        
        <div class="hidden lg:grid grid-cols-5 divide-x divide-slate-100 dark:divide-white/5 bg-slate-50 dark:bg-white/5 border-b dark:border-white/10 text-center">
            <?php foreach ($dayMap as $eng => $label): $isToday = ($eng === $todayEng); ?>
                <div class="px-4 py-4 <?= $isToday ? 'bg-indigo-50/50 dark:bg-indigo-500/10' : '' ?>">
                    <span class="text-[11px] font-black uppercase tracking-widest <?= $isToday ? 'text-indigo-600' : 'text-slate-400' ?>"><?= $label ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 dark:divide-white/5">
            <?php foreach (array_keys($dayMap) as $engDay): 
                $isToday = ($engDay === $todayEng); 
                $hasClasses = !empty($grouped[$engDay]);
            ?>
                <div class="flex flex-col <?= $isToday ? 'bg-indigo-50/5 dark:bg-indigo-500/5' : '' ?>">
                    
                    <div class="lg:hidden flex items-center justify-between p-4 bg-slate-50/50 dark:bg-white/5 cursor-pointer" onclick="this.nextElementSibling.classList.toggle('hidden')">
                        <span class="text-xs font-bold uppercase tracking-widest <?= $isToday ? 'text-indigo-600' : 'text-slate-500' ?>">
                            <?= $dayMap[$engDay] ?>
                            <?php if($isToday): ?><span class="ml-2 text-[9px] bg-indigo-600 text-white px-2 py-0.5 rounded-full">SOT</span><?php endif; ?>
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform lg:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </div>

                    <div class="<?= $isToday ? 'block' : 'hidden' ?> lg:block p-3 space-y-6 min-h-[150px] lg:min-h-[600px]">
                        <?php if (!$hasClasses): ?>
                            <div class="h-20 lg:h-full flex items-center justify-center opacity-20 italic text-xs">Pushim</div>
                        <?php else: ?>
                            <?php foreach (['Mëngjes', 'Pasdite', 'Mbrëmje'] as $block): ?>
                                <?php if (!empty($grouped[$engDay][$block])): ?>
                                    <div class="space-y-3">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter block border-b border-slate-100 dark:border-white/5 pb-1"><?= $block ?></span>
                                        <?php foreach ($grouped[$engDay][$block] as $class): $style = getSubjectStyle($class['subject_name']); ?>
                                            <div class="group bg-white dark:bg-slate-800 border-l-4 <?= $style['border'] ?> rounded-xl p-3 shadow-sm hover:shadow-md transition-all border border-slate-100 dark:border-white/5">
                                                <div class="flex justify-between items-start mb-1">
                                                    <?= (int)$class['period_number'] ?>
                                                    <span class="text-lg"><?= $style['icon'] ?></span>
                                                </div>
                                                <div class="font-bold text-slate-800 dark:text-white text-sm truncate"><?= htmlspecialchars($class['subject_name']) ?></div>
                                                <div class="text-[10px] text-slate-500 mt-1 font-medium">Klasa <?= htmlspecialchars($class['class_name']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="space-y-6 max-w-full overflow-hidden pb-10">

    <!-- MOBILE: AXHENDA E SOTME -->
    <?php if (!empty($todayClasses)): ?>
    <div class="lg:hidden px-2">
        <h3 class="text-sm font-bold text-slate-900 mb-4">
            Axhenda e Sotme (<?= $dayMap[$todayEng] ?>)
        </h3>

        <div class="space-y-3">
            <?php foreach ($todayClasses as $tc): ?>
                <div class="relative flex items-center gap-4 p-4 bg-white rounded-2xl border shadow-sm">
                    
                    <!-- PERIOD DISPLAY (REPLACED TIME) -->
                    <div class="text-center min-w-[55px]">
                        <span class="block text-xs font-black">
                            Ora <?= (int)$tc['period_number'] ?>
                        </span>
                    </div>

                    <div class="flex-1">
                        <h4 class="text-sm font-bold leading-none mb-1">
                            <?= htmlspecialchars($tc['subject_name']) ?>
                        </h4>
                        <p class="text-[10px] text-slate-500 font-medium">
                            Klasa <?= htmlspecialchars($tc['class_name']) ?>
                        </p>
                    </div>

                    <?php if ($tc['status'] === 'ongoing'): ?>
                        <span class="text-xs font-bold text-emerald-600">AKTIVE</span>
                    <?php elseif ($tc['status'] === 'completed'): ?>
                        <span class="text-xs text-slate-400">Përfunduar</span>
                    <?php else: ?>
                        <span class="text-xs text-slate-400">Në vijim</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
/* Smooth Print Adjustments */
@media print {
    .lg\:grid-cols-5 { display: grid !important; grid-template-columns: repeat(5, 1fr) !important; }
    .hidden.lg\:block { display: block !important; }
    .lg\:min-h-\[600px\] { min-height: auto !important; }
    .divide-y-0 { divide-width: 0px !important; }
}
</style>
</div>

<style>
@media print {
    nav, aside, header, .print\:hidden, button, details summary svg { display: none !important; }
    body { background: white !important; color: black !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .lg\:block { display: block !important; }
    .lg\:hidden { display: none !important; }
    .grid-cols-5 { display: grid !important; grid-template-columns: repeat(5, 1fr) !important; width: 100% !important; }
    main { padding: 0 !important; margin: 0 !important; }
    .rounded-3xl { border-radius: 8px !important; border: 1px solid #ddd !important; }
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>