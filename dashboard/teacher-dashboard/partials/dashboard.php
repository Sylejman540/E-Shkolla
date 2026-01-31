<?php
date_default_timezone_set('Europe/Tirane');
require_once __DIR__ . '/../../../db.php'; 

if (session_status() === PHP_SESSION_NONE) session_start();

/* =====================================================
    PHASE A — IDENTITY & AUTHORITY
===================================================== */
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'teacher' || empty($user['id'])) {
    http_response_code(403);
    exit('I paautorizuar.');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];

// Merr emrin e mësuesit nga tabela 'teachers'
$tStmt = $pdo->prepare("SELECT id, name FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherData = $tStmt->fetch(PDO::FETCH_ASSOC);

$teacherId   = (int)($teacherData['id'] ?? 0);
$teacherName = $teacherData['name'] ?? ($user['name'] ?? 'Mësimdhënës');

/* =====================================================
    PHASE B — CONTEXTUAL DATA & TRANSLATIONS
===================================================== */
$ditet = [
    'Monday'    => 'E Hënë', 'Tuesday'   => 'E Martë', 'Wednesday' => 'E Mërkurë',
    'Thursday'  => 'E Enjte', 'Friday'    => 'E Premte', 'Saturday'  => 'E Shtunë', 'Sunday'    => 'E Diel'
];

$muajt = [
    'Jan' => 'Jan', 'Feb' => 'Shk', 'Mar' => 'Mar', 'Apr' => 'Prill',
    'May' => 'Maj', 'Jun' => 'Qer', 'Jul' => 'Kor', 'Aug' => 'Gush',
    'Sep' => 'Sht', 'Oct' => 'Tet', 'Nov' => 'Nën', 'Dec' => 'Dhj'
];

$dita_sot = $ditet[date('l')];
$data_sot = date('d') . ' ' . $muajt[date('M')] . ' ' . date('Y');

// Viti Akademik
$yearStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE school_id = ? AND status = 'active' ORDER BY academic_year DESC LIMIT 1");
$yearStmt->execute([$schoolId]);
$academicYear = $yearStmt->fetchColumn() ?: date('Y') . '/' . (date('y')+1);

// Orari i ditës
$todayStmt = $pdo->prepare("
    SELECT cs.period_number, c.grade, s.subject_name, cs.class_id, cs.subject_id
    FROM class_schedule cs
    INNER JOIN classes c ON c.id = cs.class_id
    INNER JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.teacher_id = ? AND cs.school_id = ? AND LOWER(cs.day) = ?
    ORDER BY cs.period_number ASC
");
$todayStmt->execute([$teacherId, $schoolId, strtolower(date('l'))]);
$todayLessons = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistikat
$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS total_lessons, COUNT(DISTINCT class_id) AS total_classes FROM teacher_class WHERE teacher_id = ?");
$summaryStmt->execute([$teacherId]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// Kontrolli i Kujdestarisë (Lidhja me teachers.id)
$headStmt = $pdo->prepare("SELECT grade FROM classes WHERE class_header = ? AND status = 'active' LIMIT 1");
$headStmt->execute([$teacherId]);
$headClass = $headStmt->fetch(PDO::FETCH_ASSOC);

$isClassHeader = !empty($headClass);

/* =====================================================
    ADMIN LOGS — ONLY FOR CLASS HEADERS
===================================================== */
$adminInsights = [];
if ($isClassHeader) {
    $logStmt = $pdo->prepare("
        SELECT action_title, note, created_at
        FROM admin_logs
        WHERE school_id = ? AND context = 'class'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $logStmt->execute([$schoolId]);
    $adminInsights = $logStmt->fetchAll(PDO::FETCH_ASSOC);
}

$pendingAssignmentsStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id = ? AND status = 'submitted'");
$pendingAssignmentsStmt->execute([$teacherId]);
$pendingCount = (int)$pendingAssignmentsStmt->fetchColumn();

$riskStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE teacher_id = ? AND present = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY student_id HAVING COUNT(*) > 3");
$riskStmt->execute([$teacherId]);
$atRiskCount = $riskStmt->rowCount();

ob_start();
?>

<style>
    .dashboard-container { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
    @media print {
        .no-print, nav, .sidebar, button, .quick-links { display: none !important; }
        .max-w-6xl { max-width: 100% !important; padding: 0 !important; }
        body { background: white !important; }
        .shadow-sm { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    }
</style>

<div class="dashboard-container max-w-6xl mx-auto p-4 lg:p-6 space-y-6 animate-in fade-in duration-500 text-slate-800">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mirësevini, Prof. <?= htmlspecialchars($teacherName) ?></h1>
            <p class="text-sm text-slate-500 font-normal">Sot është e <?= $dita_sot ?>, <?= $data_sot ?> • <span class="text-indigo-600 font-medium"><?= $academicYear ?></span></p>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm hover:bg-slate-50 transition-all">Download Raportin</button>
            <a href="/E-Shkolla/teacher-relators" class="bg-white border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm hover:bg-slate-50 transition-all">Relatoret</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Planifikimi</p>
            <h3 class="text-xl font-bold text-slate-800"><?= (int)$summary['total_lessons'] ?> Orë <span class="text-xs font-normal text-slate-400">/ javë</span></h3>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Angazhimi</p>
            <h3 class="text-xl font-bold text-slate-800"><?= (int)$summary['total_classes'] ?> Klasa</h3>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Detyrat</p>
            <h3 class="text-xl font-bold text-slate-800"><?= $pendingCount ?> <span class="text-xs font-normal text-slate-400">Pezull</span></h3>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Kujdestaria</p>
            <h3 class="text-xl font-bold text-slate-800"><?= $headClass ? htmlspecialchars($headClass['grade']) : 'Asnjë' ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
                <div class="p-5 border-b border-slate-50 flex justify-between items-center">
                    <h2 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                        <span class="w-1.5 h-4 bg-indigo-600 rounded-full"></span> Orari i ditës
                    </h2>
                    <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-md uppercase tracking-wide"><?= $dita_sot ?></span>
                </div>
                <div class="p-5">
                    <?php if ($todayLessons): ?>
                        <div class="space-y-3">
                            <?php foreach ($todayLessons as $lesson): ?>
                                <div class="flex items-center gap-4 p-3 rounded-xl border border-slate-50 hover:border-indigo-100 hover:bg-indigo-50/20 transition-all group">
                                    <div class="text-center min-w-[40px]">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Ora</p>
                                        <p class="text-lg font-bold text-indigo-600"><?= (int)$lesson['period_number'] ?></p>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($lesson['subject_name']) ?></h4>
                                        <p class="text-xs text-slate-500">Klasa: <?= htmlspecialchars($lesson['grade']) ?></p>
                                    </div>
                                    <a href="/E-Shkolla/show-classes?class_id=<?= (int)$lesson['class_id'] ?>&subject_id=<?= (int)$lesson['subject_id'] ?>" class="opacity-0 group-hover:opacity-100 p-1.5 bg-white border border-slate-200 rounded-lg text-slate-400 hover:text-indigo-600 transition-all no-print">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <h3 class="font-semibold text-slate-800 text-sm">Nuk ka mësim sot</h3>
                            <p class="text-xs text-slate-500">Shijoni kohën tuaj të lirë ose përgatitni materialet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isClassHeader && !empty($adminInsights)): ?>
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2 text-base">
                        <span class="w-1.5 h-4 bg-indigo-500 rounded-full"></span> Njoftime nga Administrata
                    </h2>
                    <div class="space-y-3">
                        <?php foreach ($adminInsights as $log): ?>
                            <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-100">
                                <p class="text-xs font-bold text-indigo-700"><?= htmlspecialchars($log['action_title']) ?></p>
                                <?php if (!empty($log['note'])): ?>
                                    <p class="text-[11px] text-slate-600 mt-1"><?= htmlspecialchars($log['note']) ?></p>
                                <?php endif; ?>
                                <p class="text-[10px] text-slate-400 mt-1 italic"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2 text-base">
                    <span class="w-1.5 h-4 bg-rose-500 rounded-full"></span> Vëmendje Kritike
                </h2>
                <div class="space-y-3">
                    <?php if ($atRiskCount > 0): ?>
                        <div class="flex gap-3 p-3 rounded-xl bg-rose-50 border border-rose-100 text-rose-700">
                            <div class="text-sm">⚠️</div>
                            <div>
                                <p class="text-xs font-bold"><?= $atRiskCount ?> Nxënës në Rrezik</p>
                                <p class="text-[10px] opacity-80 italic">Mbi 3 mungesa këtë muaj.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($pendingCount > 0): ?>
                        <div class="flex gap-3 p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-700">
                            <div class="text-sm">📝</div>
                            <div>
                                <p class="text-xs font-bold"><?= $pendingCount ?> Detyra Pezull</p>
                                <p class="text-[10px] opacity-80 italic">Presin vlerësimin.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($atRiskCount === 0 && $pendingCount === 0): ?>
                        <div class="text-center py-4 text-emerald-600 font-semibold text-xs">✓ Çdo gjë është në rregull</div>
                    <?php endif; ?>
                </div>
                
                <hr class="my-5 border-slate-50">
                <div class="space-y-2 no-print">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Lidhje të shpejta</p>
                    <a href="#" class="block text-xs font-medium text-slate-600 hover:text-indigo-600 transition-colors">→ Shkarko planprogramin</a>
                    <a href="#" class="block text-xs font-medium text-slate-600 hover:text-indigo-600 transition-colors">→ Raporti mujor i klasës</a>
                </div>
            </div>
        </div>
    </div>

    <div class="hidden print:flex justify-between pt-16 text-[11px] font-bold uppercase text-slate-400">
        <div class="border-t border-slate-300 pt-2 w-40 text-center">Nënshkrimi</div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>