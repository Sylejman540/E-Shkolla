<?php
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

// FIX: Pull teacher name from the 'teachers' table to avoid "root" display
$tStmt = $pdo->prepare("SELECT id, name FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherData = $tStmt->fetch(PDO::FETCH_ASSOC);

$teacherId   = (int)($teacherData['id'] ?? 0);
$teacherName = $teacherData['name'] ?? ($user['name'] ?? 'Mësimdhënës');

/* =====================================================
   PHASE B — CONTEXTUAL DATA
===================================================== */
$yearStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE school_id = ? AND status = 'active' ORDER BY academic_year DESC LIMIT 1");
$yearStmt->execute([$schoolId]);
$academicYear = $yearStmt->fetchColumn() ?: date('Y') . '/' . (date('y')+1);

// FIX: Ensure day matches database casing and parameters are mapped correctly
$today = strtolower(date('l')); 
$todayStmt = $pdo->prepare("
    SELECT 
        cs.period_number,
        c.grade,
        s.subject_name,
        cs.class_id
    FROM class_schedule cs
    INNER JOIN classes c ON c.id = cs.class_id AND c.school_id = ?
    INNER JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.teacher_id = ?
      AND cs.school_id  = ?
      AND LOWER(cs.day) = ?
    ORDER BY cs.period_number ASC
");
$todayStmt->execute([$schoolId, $teacherId, $schoolId, $today]);
$todayLessons = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS total_lessons, COUNT(DISTINCT class_id) AS total_classes FROM teacher_class WHERE teacher_id = ?");
$summaryStmt->execute([$teacherId]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$headStmt = $pdo->prepare("SELECT grade, id FROM classes WHERE class_header = ? AND status = 'active' LIMIT 1");
$headStmt->execute([$userId]);
$headClass = $headStmt->fetch(PDO::FETCH_ASSOC);

$pendingAssignmentsStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id = ? AND status = 'submitted'");
$pendingAssignmentsStmt->execute([$teacherId]);
$pendingCount = (int)$pendingAssignmentsStmt->fetchColumn();

$riskStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE teacher_id = ? AND present = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY student_id HAVING COUNT(*) > 3");
$riskStmt->execute([$teacherId]);
$atRiskCount = $riskStmt->rowCount();

ob_start();
?>

<style>
    @media print {
        .no-print, nav, .sidebar, button, .quick-links { display: none !important; }
        .max-w-7xl { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
        body { background: white !important; }
        .shadow-sm { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    }
</style>

<div class="max-w-7xl mx-auto p-4 lg:p-8 space-y-8 animate-in fade-in duration-500">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Mirësevini, Prof. <?= htmlspecialchars($teacherName) ?></h1>
            <p class="text-slate-500 font-medium">Sot është e <?= date('l') ?>, <?= date('d M Y') ?> • <span class="text-indigo-600"><?= $academicYear ?></span></p>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition-all">Download Raportin</button>
            <a href="/E-Shkolla/teacher-relators">
                <button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition-all">Relatoret</button>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="2"/></svg>
            </div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Planifikimi</p>
            <h3 class="text-2xl font-black text-slate-800"><?= (int)$summary['total_lessons'] ?> Orë <span class="text-sm font-medium text-slate-400">/ javë</span></h3>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" stroke-width="2"/></svg>
            </div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Angazhimi</p>
            <h3 class="text-2xl font-black text-slate-800"><?= (int)$summary['total_classes'] ?> Klasa</h3>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="w-10 h-10 <?= $pendingCount > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' ?> rounded-lg flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke-width="2"/></svg>
            </div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Detyrat</p>
            <h3 class="text-2xl font-black text-slate-800"><?= $pendingCount ?> <span class="text-sm font-medium text-slate-400">Pezull</span></h3>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="w-10 h-10 <?= $headClass ? 'bg-purple-50 text-purple-600' : 'bg-slate-50 text-slate-400' ?> rounded-lg flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg>
            </div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kujdestaria</p>
            <h3 class="text-2xl font-black text-slate-800"><?= $headClass ? htmlspecialchars($headClass['grade']) : 'Asnjë' ?></h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="font-black text-slate-800 flex items-center gap-2">
                        <span class="w-2 h-5 bg-indigo-600 rounded-full"></span>
                        Orari i ditës së sotme
                    </h2>
                    <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full uppercase"><?= date('l') ?></span>
                </div>
                
                <div class="p-6">
                    <?php if ($todayLessons): ?>
                        <div class="space-y-4">
                            <?php foreach ($todayLessons as $lesson): ?>
                                <div class="flex items-center gap-4 p-4 rounded-2xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30 transition-all group">
                                    <div class="text-center min-w-[50px]">
                                        <p class="text-xs font-black text-slate-400 uppercase">Ora</p>
                                        <p class="text-xl font-black text-indigo-600"><?= (int)$lesson['period_number'] ?></p>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-slate-900"><?= htmlspecialchars($lesson['subject_name']) ?></h4>
                                        <p class="text-sm text-slate-500">Klasa: <?= htmlspecialchars($lesson['grade']) ?></p>
                                    </div>
                                    <a href="class-view.php?id=<?= $lesson['class_id'] ?>" class="opacity-0 group-hover:opacity-100 p-2 bg-white border rounded-lg text-slate-400 hover:text-indigo-600 transition-all no-print">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10">
                            <div class="bg-slate-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" stroke-width="2"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800">Nuk ka mësim sot</h3>
                            <p class="text-sm text-slate-500">Shijoni kohën tuaj të lirë ose përgatitni materialet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <h2 class="font-black text-slate-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-5 bg-rose-500 rounded-full"></span>
                    Vëmendje Kritike
                </h2>

                <div class="space-y-3">
                    <?php if ($atRiskCount > 0): ?>
                        <div class="flex gap-4 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-rose-700">
                            <div class="text-xl">⚠️</div>
                            <div>
                                <p class="text-sm font-black"><?= $atRiskCount ?> Nxënës në Rrezik</p>
                                <p class="text-xs opacity-80">Më shumë se 3 mungesa gjatë muajit të fundit.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($pendingCount > 0): ?>
                        <div class="flex gap-4 p-4 rounded-2xl bg-amber-50 border border-amber-100 text-amber-700">
                            <div class="text-xl">📝</div>
                            <div>
                                <p class="text-sm font-black"><?= $pendingCount ?> Detyra për Vlerësim</p>
                                <p class="text-xs opacity-80">Nxënësit janë në pritje të rezultateve.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($atRiskCount === 0 && $pendingCount === 0): ?>
                        <div class="text-center py-6 text-emerald-500 font-bold text-sm">✓ Çdo gjë është në rregull</div>
                    <?php endif; ?>
                </div>
                
                <hr class="my-6 border-slate-100">
                
                <div class="space-y-2 no-print">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Lidhje të shpejta</p>
                    <a href="#" class="block text-sm font-bold text-slate-600 hover:text-indigo-600 transition-colors italic">→ Shkarko planprogramin</a>
                    <a href="#" class="block text-sm font-bold text-slate-600 hover:text-indigo-600 transition-colors italic">→ Raporti mujor i klasës</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>