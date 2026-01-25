<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

/* =====================================================
   SECURITY & DATA INTEGRITY
===================================================== */

$user = $_SESSION['user'] ?? null;

if (!$user || empty($user['id']) || empty($user['school_id']) || ($user['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('I paautorizuar.');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];

// Authoritative Teacher Check
$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    http_response_code(404);
    exit('Mësimdhënësi nuk u gjet.');
}

// Academic Year Context
$yearStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE school_id = ? AND status = 'active' ORDER BY academic_year DESC LIMIT 1");
$yearStmt->execute([$schoolId]);
$academicYear = $yearStmt->fetchColumn() ?: 'E pa specifikuar';

/* =====================================================
   DATA FETCHING
===================================================== */

// 1. Pinned Class (If Head Teacher)
$headerStmt = $pdo->prepare("SELECT id, grade as class_name, max_students FROM classes WHERE class_header = ? AND school_id = ? AND status = 'active' AND academic_year = ? LIMIT 1");
$headerStmt->execute([$userId, $schoolId, $academicYear]);
$pinnedClass = $headerStmt->fetch(PDO::FETCH_ASSOC);

// 2. All Subject Classes
$stmt = $pdo->prepare("
    SELECT tc.class_id, tc.subject_id, c.grade AS class_name, c.max_students, s.subject_name
    FROM teacher_class tc
    INNER JOIN classes c ON c.id = tc.class_id AND c.school_id = ? AND c.status = 'active' AND c.academic_year = ?
    INNER JOIN subjects s ON s.id = tc.subject_id
    WHERE tc.teacher_id = ?
    ORDER BY c.grade ASC
");
$stmt->execute([$schoolId, $academicYear, $teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   UI HELPERS
===================================================== */

function getSubjectIcon(string $subject): array {
    $s = mb_strtolower($subject);
    if (str_contains($s,'matematik')) return ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'icon' => '📐'];
    if (str_contains($s,'gjuh')) return ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'icon' => '📘'];
    if (str_contains($s,'shkenc') || str_contains($s,'kimi') || str_contains($s,'biologji')) return ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => '🧪'];
    if (str_contains($s,'fizik')) return ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'icon' => '⚛️'];
    return ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'icon' => '📝'];
}

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-8 pb-16 animate-in fade-in duration-500">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-6">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-xs font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-600 font-bold">Klasat e Mia</li>
                </ol>
            </nav>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Menaxhimi i Klasave</h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                Viti Akademik: <span class="font-bold text-slate-700"><?= htmlspecialchars($academicYear) ?></span>
            </p>
        </div>

        <div class="relative group">
            <input type="text" id="classSearch" placeholder="Kërko klasën ose lëndën..." 
                class="w-full md:w-72 pl-10 pr-4 py-2.5 bg-slate-100 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none text-sm">
            <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:border-indigo-200 transition-all">
            <div class="flex justify-between items-start mb-4">
                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-width="2"/></svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Aktiviteti</span>
            </div>
            <p class="text-sm font-medium text-slate-500">Gjithsej Klasa</p>
            <h3 class="text-3xl font-black text-slate-900"><?= count(array_unique(array_column($classes, 'class_id'))) ?></h3>
        </div>

        <div class="bg-indigo-600 rounded-2xl p-6 shadow-xl shadow-indigo-100 text-white relative overflow-hidden group">
            <div class="relative z-10">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-white/20 rounded-lg backdrop-blur-md">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke-width="2"/></svg>
                    </div>
                </div>
                <p class="text-xs font-bold text-indigo-100 uppercase">Statusi Im</p>
                <h3 class="text-2xl font-black"><?= $pinnedClass ? 'Kujdestar Klasë' : 'Mësimdhënës' ?></h3>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        </div>
    </div>

    <div class="space-y-6">
        
        <?php if ($pinnedClass): ?>
        <div class="space-y-3">
            <h2 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Përgjegjësia Kryesore</h2>
            <div class="bg-white border-2 border-indigo-500 rounded-3xl p-6 flex flex-col md:flex-row items-center justify-between gap-6 shadow-sm">
                <div class="flex items-center gap-6 text-center md:text-left">
                    <div class="h-16 w-16 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl font-black text-indigo-600 border border-indigo-100">
                        <?= htmlspecialchars($pinnedClass['class_name']) ?>
                    </div>
                    <div>
                        <h4 class="text-xl font-black text-slate-900">Kujdestari e Klasës <?= htmlspecialchars($pinnedClass['class_name']) ?></h4>
                        <p class="text-sm text-slate-500 italic">Menaxhimi i studentëve, mungesave dhe kontakteve me prindërit.</p>
                    </div>
                </div>
                <a href="" 
                   class="w-full md:w-auto px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-lg text-center">
                    Shiko Detajet e Klasës
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="space-y-3">
            <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Lëndët e Caktuara</h2>
            
            <?php if (empty($classes)): ?>
            <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl p-12 text-center">
                <p class="text-slate-400 italic">Nuk u gjet asnjë klasë e caktuar për këtë vit akademik.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="classesGrid">
                <?php foreach ($classes as $row): 
                    $style = getSubjectIcon($row['subject_name']);
                ?>
                <div class="class-card group bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-xl hover:-translate-y-1 transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <div class="h-10 w-10 <?= $style['bg'] ?> <?= $style['text'] ?> rounded-xl flex items-center justify-center text-xl shadow-sm border border-white group-hover:scale-110 transition-transform">
                            <?= $style['icon'] ?>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded-md border border-slate-100">
                            <?= (int)$row['max_students'] ?> NXËNËS
                        </span>
                    </div>

                    <h4 class="font-black text-slate-800 searchable-class">Klasa <?= htmlspecialchars($row['class_name']) ?></h4>
                    <p class="text-xs font-bold text-indigo-600 uppercase tracking-wide mb-4 searchable-subject"><?= htmlspecialchars($row['subject_name']) ?></p>
                    
                    <div class="pt-4 border-t border-slate-50 flex items-center justify-between">
                        <span class="text-[10px] font-medium text-slate-400 italic">ID: #<?= (int)$row['class_id'] . (int)$row['subject_id'] ?></span>
                        <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>&subject_id=<?= (int)$row['subject_id'] ?>" 
                           class="text-[11px] font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-tighter">
                           Menaxho →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center text-[11px] text-slate-400 bg-slate-50 p-6 rounded-2xl border border-slate-100">
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-1">🛡️ Moduli i Sigurt</span>
            <span class="flex items-center gap-1 uppercase">🔑 Sesioni: <?= substr(session_id(), 0, 8) ?>...</span>
        </div>
        <p class="mt-2 md:mt-0 italic text-center">Sistemi për Menaxhimin e Arsimit © <?= date('Y') ?></p>
    </div>

</div>

<script>
// Real-time Search Logic
document.getElementById('classSearch').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.class-card').forEach(card => {
        const className = card.querySelector('.searchable-class').innerText.toLowerCase();
        const subjectName = card.querySelector('.searchable-subject').innerText.toLowerCase();
        
        if (className.includes(filter) || subjectName.includes(filter)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<style>
    /* Smooth Transitions */
    .class-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #f8fafc; }
    ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>