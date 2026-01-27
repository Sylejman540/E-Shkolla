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

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    http_response_code(404);
    exit('Mësimdhënësi nuk u gjet.');
}

$yearStmt = $pdo->prepare("SELECT academic_year FROM classes WHERE school_id = ? AND status = 'active' ORDER BY academic_year DESC LIMIT 1");
$yearStmt->execute([$schoolId]);
$academicYear = $yearStmt->fetchColumn() ?: '2025/26';

/* =====================================================
    PAGINATION LOGIC
===================================================== */
$itemsPerPage = 12; // Rritur meqë kartelat janë më kompakte
$currentPage  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($currentPage - 1) * $itemsPerPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM teacher_class tc
    INNER JOIN classes c ON c.id = tc.class_id AND c.school_id = ? AND c.status = 'active' AND c.academic_year = ?
    WHERE tc.teacher_id = ?
");
$countStmt->execute([$schoolId, $academicYear, $teacherId]);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

/* =====================================================
    DATA FETCHING
===================================================== */
$headerStmt = $pdo->prepare("SELECT id, grade as class_name FROM classes WHERE class_header = ? AND school_id = ? AND status = 'active' AND academic_year = ? LIMIT 1");
$headerStmt->execute([$userId, $schoolId, $academicYear]);
$pinnedClass = $headerStmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT tc.class_id, tc.subject_id, c.grade AS class_name, c.max_students, s.subject_name
    FROM teacher_class tc
    INNER JOIN classes c ON c.id = tc.class_id AND c.school_id = ? AND c.status = 'active' AND c.academic_year = ?
    INNER JOIN subjects s ON s.id = tc.subject_id
    WHERE tc.teacher_id = ?
    ORDER BY c.grade ASC
    LIMIT $itemsPerPage OFFSET $offset
");
$stmt->execute([$schoolId, $academicYear, $teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="max-w-6xl mx-auto space-y-6 pb-12 animate-in fade-in duration-500 text-slate-700">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Menaxhimi i Klasave</h1>
            <p class="text-xs text-slate-500">Viti Akademik: <span class="font-semibold text-indigo-600"><?= htmlspecialchars($academicYear) ?></span></p>
        </div>

        <div class="relative">
            <input type="text" id="classSearch" placeholder="Kërko klasën ose lëndën..." 
                class="w-full md:w-64 pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none text-sm">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2"/></svg>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        
        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-all group">
            <div class="flex-shrink-0 w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center border border-blue-100 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="flex flex-col min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Klasat Totale</p>
                <p class="text-xl font-bold text-slate-900 tabular-nums"><?= $totalItems ?></p>
            </div>
        </div>

        <div class="bg-indigo-600 rounded-xl py-4 px-5 flex items-center gap-4 shadow-md shadow-indigo-100 group transition-all hover:bg-indigo-700">
            <div class="flex-shrink-0 w-12 h-12 bg-white/20 text-white rounded-xl flex items-center justify-center backdrop-blur-md border border-white/30 group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="flex flex-col min-w-0">
                <p class="text-[10px] font-bold text-indigo-100 uppercase tracking-wider leading-none mb-1">Roli Aktual</p>
                <p class="text-lg font-bold text-white truncate"><?= $pinnedClass ? 'Kujdestar' : 'Mësimdhënës' ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-all group">
            <div class="flex-shrink-0 w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center border border-orange-100 group-hover:bg-orange-500 group-hover:text-white transition-all duration-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex flex-col min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Viti Akademik</p>
                <p class="text-xl font-bold text-slate-900"><?= htmlspecialchars($academicYear) ?></p>
            </div>
        </div>

    </div>

    <?php if ($pinnedClass): ?>
    <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 bg-white rounded-lg flex items-center justify-center text-lg font-bold text-indigo-600 border border-indigo-100 shadow-sm">
                <?= htmlspecialchars($pinnedClass['class_name']) ?>
            </div>
            <div>
                <h4 class="text-sm font-bold text-slate-900">Kujdestaria e Klasës</h4>
                <p class="text-xs text-slate-500">Menaxhimi i studentëve dhe prindërve të klasës <?= htmlspecialchars($pinnedClass['class_name']) ?>.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="space-y-4">
        <h2 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Lëndët e Caktuara</h2>
        
        <?php if (empty($classes)): ?>
        <div class="bg-white border border-slate-200 border-dashed rounded-xl p-12 text-center">
            <p class="text-sm text-slate-400">Nuk u gjet asnjë lëndë e caktuar.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="classesGrid">
            <?php foreach ($classes as $row): 
                $style = getSubjectIcon($row['subject_name']);
            ?>
            <div class="class-card group bg-white border border-slate-200 rounded-xl p-4 hover:border-indigo-300 transition-all">
                <div class="flex justify-between items-start mb-3">
                    <div class="h-8 w-8 <?= $style['bg'] ?> <?= $style['text'] ?> rounded-lg flex items-center justify-center text-sm border border-white">
                        <?= $style['icon'] ?>
                    </div>
                    <span class="text-[9px] font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded border border-slate-100">
                        <?= (int)$row['max_students'] ?> NX.
                    </span>
                </div>

                <h4 class="text-sm font-bold text-slate-900 searchable-class">Klasa <?= htmlspecialchars($row['class_name']) ?></h4>
                <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-tight mb-4 searchable-subject"><?= htmlspecialchars($row['subject_name']) ?></p>
                
                <div class="pt-3 border-t border-slate-50 flex items-center justify-between">
                    <span class="text-[9px] text-slate-400">ID: #<?= (int)$row['class_id'] ?></span>
                    <a href="/E-Shkolla/show-classes?class_id=<?= (int)$row['class_id'] ?>&subject_id=<?= (int)$row['subject_id'] ?>" 
                       class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                        MENAXHO →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-1.5 pt-6">
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" 
               class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all <?= $i === $currentPage ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="flex justify-between items-center text-[10px] text-slate-400 mt-8">
        <p>🛡️ Sesioni: <?= substr(session_id(), 0, 8) ?></p>
        <p>© <?= date('Y') ?> E-Shkolla</p>
    </div>
</div>

<script>
document.getElementById('classSearch').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.class-card').forEach(card => {
        const className = card.querySelector('.searchable-class').innerText.toLowerCase();
        const subjectName = card.querySelector('.searchable-subject').innerText.toLowerCase();
        card.style.display = (className.includes(filter) || subjectName.includes(filter)) ? 'block' : 'none';
    });
});
</script>

<style>
    .class-card { transition: transform 0.2s ease, border-color 0.2s ease; }
    .class-card:hover { transform: translateY(-2px); }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>