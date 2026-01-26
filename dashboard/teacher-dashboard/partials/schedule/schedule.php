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


<div class="max-w-6xl mx-auto space-y-6 pb-12 animate-in fade-in duration-500 text-slate-700 font-inter">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-5 print:hidden">
        <div>
            <nav class="flex mb-1" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-[10px] font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-500">Orari</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Orari Im Personal</h1>
            <p class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <?= $lastUpdatedLabel ?>
            </p>
        </div>

        <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-lg">
            <button onclick="printScope('today')" class="hover:bg-white px-3 py-1.5 text-[10px] font-semibold rounded-md transition-all text-slate-600">Sot</button>
            <button onclick="printScope('all')" class="bg-indigo-600 text-white shadow-sm px-3 py-1.5 text-[10px] font-semibold rounded-md transition-all hover:bg-indigo-700">Printo Krejt</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 print:hidden">
        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm group">
            <div class="flex-shrink-0 w-11 h-11 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center border border-indigo-100 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Ngarkesa</p>
                <h3 class="text-lg font-bold text-slate-900"><?= (int)$totalHours ?> orë <span class="text-[11px] font-medium text-slate-400">/javë</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm group">
            <div class="flex-shrink-0 w-11 h-11 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center border border-emerald-100 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Lëndët</p>
                <h3 class="text-lg font-bold text-slate-900"><?= (int)$totalClasses ?> Lëndë</h3>
            </div>
        </div>

        <div class="bg-indigo-600 rounded-xl py-4 px-5 flex items-center gap-4 shadow-md shadow-indigo-100 text-white">
            <div class="flex-shrink-0 w-11 h-11 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm border border-white/30">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.5-7 3 10 1 15 1 15z"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-indigo-100 uppercase tracking-wider mb-0.5">Busiest Day</p>
                <h3 class="text-lg font-bold"><?= htmlspecialchars($busiestDayLabel) ?></h3>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <?php foreach ($dayMap as $dayKey => $label): 
            $isToday = ($dayKey === $todayEng);
        ?>
            <div class="print-scope week space-y-3">
                <div class="flex items-center justify-between border-b pb-1.5 <?= $isToday ? 'border-indigo-400' : 'border-slate-100' ?>">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide <?= $isToday ? 'text-indigo-600' : '' ?>">
                        <?= $label ?>
                    </h3>
                    <?php if($isToday): ?>
                        <span class="text-[8px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-bold">SOT</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($grouped[$dayKey])): ?>
                    <div class="bg-slate-50 border border-dashed border-slate-200 rounded-lg p-3 text-center">
                        <p class="text-[10px] font-medium italic text-slate-400">Pushim</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach ($grouped[$dayKey] as $block => $lessons): ?>
                        <div class="space-y-1.5">
                            <?php foreach ($lessons as $l):
                                $style = getSubjectStyle($l['subject_name']);
                                $slotKey = strtolower($l['day']) . '-' . (int)$l['period_number'];
                                $isConflict = isset($conflictSlots[$slotKey]) && count($conflictSlots[$slotKey]) > 1;
                            ?>
                                <div class="bg-white border border-slate-200 rounded-lg p-3 hover:border-indigo-200 transition-all shadow-sm">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <div class="w-7 h-7 rounded bg-slate-50 flex items-center justify-center text-sm border border-slate-100">
                                            <?= $style['icon'] ?>
                                        </div>
                                        <span class="text-[9px] font-bold text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">
                                            ORA <?= (int)$l['period_number'] ?>
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-slate-800 text-[12px] leading-tight mb-1 truncate"><?= htmlspecialchars($l['subject_name']) ?></h4>
                                    <p class="text-[10px] text-slate-500 font-medium">Klasa: <span class="text-slate-900"><?= htmlspecialchars($l['grade'] ?? '—') ?></span></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex justify-between items-center text-[10px] text-slate-400 pt-6 border-t border-slate-100">
        <p class="font-medium tracking-tight italic">© <?= date('Y') ?> E-Shkolla Management</p>
        <p class="font-bold">ID: <?= htmlspecialchars(substr($csrfToken,0,6)) ?></p>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    .font-inter { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }

    /* Custom overrides for thinner typography */
    h1, h2, h3, h4 { letter-spacing: -0.015em; }
    
    @media print {
        .font-inter { font-family: sans-serif; }
        .text-slate-700 { color: #334155 !important; }
    }
</style>
<script>
function printScope(scope) {
    let originalContent = document.body.innerHTML;
    let printContent = '';

    if (scope === 'today') {
        const todaySection = document.getElementById('today');
        if (!todaySection) {
            alert('Nuk ka përmbajtje për sot.');
            return;
        }
        printContent = todaySection.outerHTML;
    }

    if (scope === 'all') {
        const printableArea = document.getElementById('printable-area') 
            || document.querySelector('main') 
            || document.body;
        printContent = printableArea.outerHTML;
    }

    document.body.innerHTML = `
        <html>
            <head>
                <title>Print</title>
                <style>
                    body {
                        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                        color: #0f172a;
                    }
                    h1, h2, h3 {
                        margin-bottom: 8px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 16px;
                    }
                    th, td {
                        border: 1px solid #e2e8f0;
                        padding: 8px;
                        font-size: 12px;
                        text-align: left;
                    }
                    th {
                        background: #f1f5f9;
                        font-weight: 700;
                    }
                    @media print {
                        button, .no-print {
                            display: none !important;
                        }
                    }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
        </html>
    `;

    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>


<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';