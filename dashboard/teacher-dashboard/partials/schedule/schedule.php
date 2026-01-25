<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/* =====================================================
   SECURITY: SESSION + ROLE + SCHOOL VALIDATION
===================================================== */

$user = $_SESSION['user'] ?? null;

if (
    !$user ||
    empty($user['id']) ||
    empty($user['school_id']) ||
    ($user['role'] ?? '') !== 'teacher'
) {
    http_response_code(403);
    exit('I paautorizuar.');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];

/* =====================================================
   PHASE 3 — CSRF READINESS (NO ENFORCEMENT)
===================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

/* =====================================================
   PHASE 3 — RATE LIMIT AWARENESS (SOFT)
===================================================== */

$_SESSION['schedule_view_count'] = ($_SESSION['schedule_view_count'] ?? 0) + 1;
$rateLimitWarning = $_SESSION['schedule_view_count'] > 50;

/* =====================================================
   FETCH TEACHER (STRICT)
===================================================== */

$tStmt = $pdo->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ? AND school_id = ?
    LIMIT 1
");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    http_response_code(404);
    exit('Mësimdhënësi nuk u gjet.');
}

/* =====================================================
   DAY SETUP
===================================================== */

$todayEng = strtolower(date('l'));

$dayMap = [
    'monday'    => 'E Hënë',
    'tuesday'   => 'E Martë',
    'wednesday' => 'E Mërkurë',
    'thursday'  => 'E Enjte',
    'friday'    => 'E Premte',
];

$validDays = array_keys($dayMap);

/* =====================================================
   FETCH SCHEDULE (SCHEMA-AWARE)
===================================================== */

$stmt = $pdo->prepare("
    SELECT 
        cs.id,
        cs.day,
        cs.period_number,
        cs.class_id,
        cs.subject_id,
        cs.academic_year,
        cs.created_at,
        c.grade,
        c.class_header,
        c.academic_year AS class_academic_year,
        s.subject_name
    FROM class_schedule cs
    INNER JOIN classes c ON c.id = cs.class_id AND c.school_id = ?
    INNER JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.teacher_id = ?
      AND cs.school_id  = ?
    ORDER BY 
        FIELD(cs.day,'monday','tuesday','wednesday','thursday','friday'),
        cs.period_number ASC
");

$stmt->execute([$schoolId, $teacherId, $schoolId]);
$scheduleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   ACADEMIC YEAR SNAPSHOT
===================================================== */

$byAcademicYear = [];
foreach ($scheduleItems as $row) {
    $year = $row['academic_year']
        ?? $row['class_academic_year']
        ?? 'E pa specifikuar';
    $byAcademicYear[$year][] = $row;
}

$availableYears = array_keys($byAcademicYear);
$currentYear = $availableYears[0] ?? 'E pa specifikuar';
$selectedYear = $_GET['year'] ?? $currentYear;

$scheduleItems = $byAcademicYear[$selectedYear] ?? [];

/* =====================================================
   KPI CALCULATIONS
===================================================== */

$totalClasses = count($scheduleItems);
$totalHours   = $totalClasses;

$dayCounts = [];
foreach ($scheduleItems as $i) {
    $d = strtolower($i['day']);
    if (!in_array($d, $validDays, true)) continue;
    $dayCounts[$d] = ($dayCounts[$d] ?? 0) + 1;
}

$busiestDayLabel = 'N/A';
$busiestCount = 0;

if ($dayCounts) {
    $busiestKey = array_keys($dayCounts, max($dayCounts))[0];
    $busiestDayLabel = $dayMap[$busiestKey] ?? 'N/A';
    $busiestCount = $dayCounts[$busiestKey];
}

/* =====================================================
   LAST UPDATED
===================================================== */

$lastUpdated = null;
foreach ($scheduleItems as $row) {
    if (!empty($row['created_at'])) {
        $lastUpdated = max($lastUpdated ?? $row['created_at'], $row['created_at']);
    }
}

$lastUpdatedLabel = $lastUpdated
    ? "Përditësuar së fundmi: " . date('d.m.Y H:i', strtotime($lastUpdated))
    : "Përditësuar së fundmi: E pa specifikuar";

/* =====================================================
   GROUPING + FREE PERIODS
===================================================== */

$grouped = [];
$periodsByDay = [];

foreach ($scheduleItems as $item) {
    $day = strtolower($item['day']);
    if (!in_array($day, $validDays, true)) continue;

    $period = (int)$item['period_number'];
    $block  = ($period <= 5) ? 'Mëngjes' : 'Pasdite';

    $grouped[$day][$block][] = $item;
    $periodsByDay[$day][] = $period;
}

$freePeriods = [];
foreach ($periodsByDay as $day => $periods) {
    sort($periods);
    for ($i = 1; $i < count($periods); $i++) {
        if ($periods[$i] - $periods[$i - 1] > 1) {
            $freePeriods[$day][] = [
                'from' => $periods[$i - 1] + 1,
                'to'   => $periods[$i] - 1
            ];
        }
    }
}

/* =====================================================
   CONFLICT DETECTION
===================================================== */

$conflictSlots = [];
foreach ($scheduleItems as $i) {
    $key = strtolower($i['day']) . '-' . (int)$i['period_number'];
    $conflictSlots[$key][] = $i;
}

/* =====================================================
   SUBJECT STYLE
===================================================== */

function getSubjectStyle(string $subject): array {
    $s = mb_strtolower($subject);
    if (str_contains($s,'matematik')) return ['border'=>'border-orange-500','icon'=>'📐'];
    if (str_contains($s,'gjuh')) return ['border'=>'border-blue-500','icon'=>'📘'];
    if (str_contains($s,'biologji') || str_contains($s,'kimi')) return ['border'=>'border-green-500','icon'=>'🧪'];
    if (str_contains($s,'fizik')) return ['border'=>'border-purple-500','icon'=>'⚛️'];
    return ['border'=>'border-slate-400','icon'=>'📝'];
}

/* =====================================================
   VIEW
===================================================== */

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-8 pb-16 animate-in fade-in duration-500">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-6 print:hidden">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-xs font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-600">Orari i Mësimit</li>
                </ol>
            </nav>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Orari Im Personal</h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <?= $lastUpdatedLabel ?>
            </p>
        </div>

        <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl">
            <button onclick="printScope('today')" class="hover:bg-white hover:shadow-sm px-4 py-2 text-xs font-bold rounded-lg transition-all text-slate-700">Sot</button>
            <button onclick="printScope('all')" class="bg-indigo-600 text-white shadow-md px-4 py-2 text-xs font-bold rounded-lg transition-all">Printo Krejt</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 print:hidden">
        <div class="group bg-white rounded-2xl border border-slate-200 p-6 transition-all hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Javore</span>
            </div>
            <p class="text-sm font-medium text-slate-500">Ngarkesa Totale</p>
            <h3 class="text-3xl font-black text-slate-900"><?= (int)$totalHours ?> <span class="text-lg font-normal text-slate-400 italic">orë</span></h3>
        </div>

        <div class="group bg-white rounded-2xl border border-slate-200 p-6 transition-all hover:border-emerald-200 hover:shadow-md">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Lëndë</span>
            </div>
            <p class="text-sm font-medium text-slate-500">Lëndë të Ndryshme</p>
            <h3 class="text-3xl font-black text-slate-900"><?= (int)$totalClasses ?></h3>
        </div>

        <div class="bg-indigo-600 rounded-2xl p-6 shadow-xl shadow-indigo-200 text-white relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-md">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.5-7 3 10 1 15 1 15z"></path></svg>
                    </div>
                </div>
                <p class="text-sm font-medium text-indigo-100">Dita më e ngarkuar</p>
                <h3 class="text-2xl font-black"><?= htmlspecialchars($busiestDayLabel) ?> <span class="text-sm font-light opacity-80">(<?= (int)$busiestCount ?> orë)</span></h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <?php foreach ($dayMap as $dayKey => $label): 
            $isToday = ($dayKey === $todayEng);
        ?>
            <div class="print-scope week <?= $isToday ? 'print-scope today' : '' ?> space-y-4">
                <div class="flex items-center justify-between border-b pb-2 <?= $isToday ? 'border-indigo-500' : 'border-slate-100' ?>">
                    <h3 class="font-black text-slate-800 tracking-tight <?= $isToday ? 'text-indigo-600' : '' ?>">
                        <?= $label ?>
                    </h3>
                    <?php if($isToday): ?>
                        <span class="text-[10px] bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full font-bold">SOT</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($grouped[$dayKey])): ?>
                    <div class="bg-slate-50 border border-dashed border-slate-200 rounded-xl p-4 text-center">
                        <p class="text-xs italic text-slate-400">Pushim</p>
                    </div>
                <?php else: ?>
                    
                    <?php if (!empty($freePeriods[$dayKey])): ?>
                        <div class="bg-amber-50/50 border border-amber-100 rounded-lg p-2 flex items-center gap-2">
                             <span class="text-amber-500">☕</span>
                             <span class="text-[10px] text-amber-700 leading-tight">
                                <strong>Dritare:</strong> 
                                <?= implode(', ', array_map(fn($g) => "Ora {$g['from']}", $freePeriods[$dayKey])) ?>
                             </span>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-4">
                    <?php foreach ($grouped[$dayKey] as $block => $lessons): ?>
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-slate-300 uppercase tracking-widest block pl-1"><?= $block ?></span>
                            
                            <?php foreach ($lessons as $l):
                                $style = getSubjectStyle($l['subject_name']);
                                $slotKey = strtolower($l['day']) . '-' . (int)$l['period_number'];
                                $isConflict = isset($conflictSlots[$slotKey]) && count($conflictSlots[$slotKey]) > 1;
                                $classLabel = trim(($l['grade'] ?? '') . ($l['class_header'] ?? ''));
                            ?>
                                <div class="relative bg-white border border-slate-200 rounded-xl p-4 transition-all hover:shadow-lg hover:-translate-y-1 group
                                    <?= $isConflict ? 'ring-2 ring-amber-400 border-transparent bg-amber-50' : '' ?>">
                                    
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-lg shadow-sm border border-slate-100 group-hover:scale-110 transition-transform">
                                            <?= $style['icon'] ?>
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded-md border border-slate-100">
                                            ORA <?= (int)$l['period_number'] ?>
                                        </span>
                                    </div>

                                    <h4 class="font-bold text-slate-800 text-sm leading-tight mb-1"><?= htmlspecialchars($l['subject_name']) ?></h4>
                                    
                                    <div class="flex items-center gap-2 text-[11px] text-slate-500 mb-3">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span>Klasa: <span class="font-bold text-slate-700"><?= $classLabel ?: '—' ?></span></span>
                                    </div>

                                    <div class="flex items-center justify-between border-t border-slate-50 pt-2">
                                        <span class="text-[10px] font-medium text-slate-400 italic">Snapshot <?= substr($csrfToken,0,4) ?></span>
<a
    href="/E-Shkolla/show-classes?class_id=<?= (int)$l['class_id'] ?>&subject_id=<?= (int)$l['subject_id'] ?>"
    class="text-[10px] text-indigo-500 font-bold hover:underline"
>
    Detajet →
</a>

                 </div>

                                    <?php if ($isConflict): ?>
                                        <div class="absolute -top-2 -right-2 bg-amber-500 text-white text-[8px] font-black px-2 py-0.5 rounded-full shadow-lg animate-bounce">
                                            KONFLIKT
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center text-[11px] text-slate-400 bg-slate-50 p-6 rounded-2xl border border-slate-100">
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">🛡️ SSL Active</span>
            <span class="flex items-center gap-1">🔑 CSRF: <?= htmlspecialchars(substr($csrfToken,0,8)) ?>...</span>
        </div>
        <p class="mt-2 md:mt-0 italic">Moduli i Menaxhimit të Mësimdhënësit © <?= date('Y') ?></p>
    </div>

</div>



<script>
function printScope(scope) {
    document.body.classList.remove('print-today','print-week','print-all');
    document.body.classList.add('print-' + scope);
    window.print();
}
</script>


<style>
    /* Custom Scrollbar for better look */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f1f1f1; }
    ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

    @media print {
        body { background: white !important; }
        .print-scope { margin-bottom: 2rem; }
        body.print-today .print-scope.week:not(.today) { display:none; }
        body.print-week .print-scope.today { display:block; }
        body.print-all .print-scope { display:block; }
        .print\:hidden { display:none !important; }
    }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';