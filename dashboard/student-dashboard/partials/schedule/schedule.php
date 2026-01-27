<?php 
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../../db.php';

$userId   = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null; 

if (!$userId || $_SESSION['user']['role'] !== 'student' || !$schoolId) {
    header("Location: /login.php");
    exit();
}

try {
    // 1. Marrim të dhënat e klasës së studentit
    $stmt = $pdo->prepare("
        SELECT s.student_id, c.id as class_id, c.grade, c.academic_year
        FROM students s
        JOIN student_class sc ON s.student_id = sc.student_id
        JOIN classes c ON sc.class_id = c.id
        WHERE s.user_id = ? AND s.school_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $schoolId]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);

    $schedule = [];
    $maxPeriods = 0;

    if ($studentData) {
        $classId = $studentData['class_id'];
        // 2. Marrim orarin (Sipas fotove të tabelave që dërgove)
        $stmt = $pdo->prepare("
            SELECT cs.day, cs.period_number, s.subject_name, u.name AS teacher_name
            FROM class_schedule cs
            JOIN subjects s ON s.id = cs.subject_id
            JOIN teachers t ON t.id = cs.teacher_id
            JOIN users u ON u.id = t.user_id
            WHERE cs.class_id = ? AND cs.school_id = ? AND cs.status = 'active'
            ORDER BY cs.period_number ASC
        ");
        $stmt->execute([$classId, $schoolId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $schedule[$row['period_number']][$row['day']] = $row;
            if ($row['period_number'] > $maxPeriods) $maxPeriods = $row['period_number'];
        }
    }
} catch (PDOException $e) {
    die("Gabim teknik.");
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$daysSq = ['monday' => 'Hënë', 'tuesday' => 'Martë', 'wednesday' => 'Mërkurë', 'thursday' => 'Enjte', 'friday' => 'Premte'];

ob_start();
?>

<style>
    /* CSS për Printim - Fsheh gjithçka përveç orarit */
    @media print {
        .no-print, nav, aside, button { display: none !important; }
        .print-container { width: 100% !important; margin: 0 !important; padding: 20px !important; }
        .schedule-card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        table { border-collapse: collapse !important; width: 100% !important; }
        th, td { border: 1px solid #cbd5e1 !important; color: black !important; }
        .bg-indigo-50 { background-color: #f8fafc !important; }
    }
</style>

<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto print-container">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 no-print">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Orari Im Mësimor</h1>
            <p class="mt-1 text-sm text-slate-500">
                Klasa: <span class="font-bold text-indigo-600"><?= e($studentData['grade'] ?? 'N/A') ?></span> 
                (<?= e($studentData['academic_year'] ?? '') ?>)
            </p>
        </div>
        <button onclick="window.print()" 
                class="inline-flex items-center px-5 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition-all shadow-sm active:scale-95">
            <svg class="w-4 h-4 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            PRINTO ORARIN
        </button>
    </div>

    <div class="hidden print:block text-center mb-10 border-b pb-6">
        <h1 class="text-3xl font-black uppercase tracking-widest text-black">Orari i Mësimit</h1>
        <p class="text-lg mt-2 font-medium">Klasa: <?= e($studentData['grade'] ?? '') ?> | Viti Akademik: <?= e($studentData['academic_year'] ?? '') ?></p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden schedule-card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-4 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest w-20 border-r border-slate-100">Ora</th>
                        <?php foreach ($daysSq as $name): ?>
                            <th class="px-4 py-4 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest border-r border-slate-100"><?= $name ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($maxPeriods > 0): ?>
                        <?php for ($p = 1; $p <= $maxPeriods; $p++): ?>
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <td class="px-4 py-6 text-center bg-slate-50/20 border-r border-slate-100">
                                    <span class="text-sm font-black text-slate-400"><?= $p ?></span>
                                </td>
                                <?php foreach ($daysSq as $dayKey => $dayName): ?>
                                    <td class="px-2 py-3 border-r border-slate-50 last:border-r-0">
                                        <?php if (isset($schedule[$p][$dayKey])): 
                                            $lesson = $schedule[$p][$dayKey];
                                        ?>
                                            <div class="p-3 rounded-2xl bg-indigo-50/40 border border-indigo-100/50 text-center">
                                                <div class="text-[13px] font-black text-indigo-700 leading-tight">
                                                    <?= e($lesson['subject_name']) ?>
                                                </div>
                                                <div class="text-[10px] text-slate-500 mt-1 font-medium italic">
                                                    <?= e($lesson['teacher_name']) ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-slate-200 font-light">—</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-24 text-center">
                                <div class="inline-flex p-4 rounded-full bg-slate-50 mb-4">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <p class="text-slate-400 italic text-sm">Orari nuk është gati për këtë klasë.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="hidden print:block mt-8 text-[10px] text-slate-400 text-right italic">
        Dokument i gjeneruar automatikisht nga sistemi E-Shkolla.
    </div>
</div>

<?php 
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php'; 
?>