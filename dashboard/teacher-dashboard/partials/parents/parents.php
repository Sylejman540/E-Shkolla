<?php
date_default_timezone_set('Europe/Tirane');
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user']['id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$userId || !$schoolId) {
    die('Aksesi i mohuar.');
}

// 1. Gjejmë klasën ku mësuesi është kujdestar (class_header)
$classStmt = $pdo->prepare("
    SELECT c.id, c.grade, c.academic_year 
    FROM classes c
    INNER JOIN teachers t ON c.class_header = t.id
    WHERE t.user_id = ? AND c.school_id = ? LIMIT 1
");
$classStmt->execute([$userId, $schoolId]);
$myClass = $classStmt->fetch(PDO::FETCH_ASSOC);

if (!$myClass) {
    die('<div class="p-10 text-center text-slate-500">Ju nuk jeni caktuar si kujdestar klase.</div>');
}

$classId = $myClass['id'];

// --- LOGJIKA E STATISTIKAVE (Për të gjithë klasën) ---
$statsStmt = $pdo->prepare("
    SELECT p.email, p.phone
    FROM students s
    INNER JOIN student_class sc ON sc.student_id = s.student_id
    LEFT JOIN parent_student ps ON ps.student_id = s.student_id
    LEFT JOIN parents p ON p.id = ps.parent_id
    WHERE sc.class_id = ? AND s.school_id = ?
");
$statsStmt->execute([$classId, $schoolId]);
$allData = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = count($allData);
$noEmail = 0;
$noPhone = 0;
foreach ($allData as $row) {
    if (empty($row['email'])) $noEmail++;
    if (empty($row['phone'])) $noPhone++;
}

// --- LOGJIKA E PAGINIMIT ---
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalStudents / $limit);

// 2. Marrim nxënësit vetëm për faqen aktuale
$stmt = $pdo->prepare("
    SELECT 
        s.name AS student_name,
        p.name AS parent_name,
        p.phone AS parent_phone,
        p.email AS parent_email
    FROM students s
    INNER JOIN student_class sc ON sc.student_id = s.student_id
    LEFT JOIN parent_student ps ON ps.student_id = s.student_id
    LEFT JOIN parents p ON p.id = ps.parent_id
    WHERE sc.class_id = ? AND s.school_id = ?
    ORDER BY s.name ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$classId, $schoolId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-7xl mx-auto p-6 lg:p-10 space-y-6 animate-in fade-in duration-500 font-inter">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-5 print:hidden">
        <div>
            <nav class="flex mb-1" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-[10px] font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-500 font-semibold uppercase tracking-wider">Kujdestaria</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                Klasa: <?= htmlspecialchars($myClass['grade']) ?>
            </h1>
        </div>

        <button onclick="window.print()" class="bg-white border border-slate-200 px-4 py-2 text-[11px] font-bold rounded-xl transition-all text-slate-600 hover:bg-slate-50 flex items-center gap-2 shadow-sm no-print">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            PRINTOLISTËN
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-indigo-600 rounded-2xl py-5 px-6 flex items-center gap-4 text-white shadow-lg shadow-indigo-100">
            <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center border border-white/10 text-xl">👥</div>
            <div>
                <p class="text-[10px] font-bold text-indigo-100 uppercase tracking-widest mb-0.5">Nxënës në total</p>
                <h3 class="text-2xl font-black"><?= $totalStudents ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 py-5 px-6 flex items-center gap-4 shadow-sm">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center border border-amber-100 font-bold italic">@</div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Mungon Email</p>
                <h3 class="text-xl font-bold text-slate-900"><?= $noEmail ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 py-5 px-6 flex items-center gap-4 shadow-sm">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center border border-rose-100">📞</div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Mungon Tel</p>
                <h3 class="text-xl font-bold text-slate-900"><?= $noPhone ?></h3>
            </div>
        </div>
    </div>

    <div class="relative no-print">
        <input type="text" id="parentSearch" placeholder="Kërko nxënësin ose prindin..." 
               class="w-full pl-5 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl text-[13px] focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-400 outline-none transition-all shadow-sm">
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse" id="parentTable">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200">
                    <th class="px-8 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nxënësi</th>
                    <th class="px-8 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Prindi</th>
                    <th class="px-8 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Telefoni</th>
                    <th class="px-8 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Email</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($results as $row): ?>
                <tr class="hover:bg-slate-50/50 transition-colors parent-row group">
                    <td class="px-8 py-4 text-[13px] font-semibold text-slate-900 searchable-data"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td class="px-8 py-4 text-[13px] text-slate-600 searchable-data"><?= htmlspecialchars($row['parent_name'] ?? '---') ?></td>
                    <td class="px-8 py-4 text-[13px] font-bold text-slate-900"><?= htmlspecialchars($row['parent_phone'] ?? '---') ?></td>
                    <td class="px-8 py-4 text-[13px] text-slate-500"><?= htmlspecialchars($row['parent_email'] ?? '---') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="px-8 py-4 bg-slate-50/50 border-t border-slate-200 flex items-center justify-between no-print">
            <div class="text-[11px] text-slate-500 font-medium">
                Faqja <?= $page ?> nga <?= $totalPages ?>
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-slate-100 transition-all">Prapa</a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-slate-100 transition-all">Para</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Search Filter (Current Page only)
    document.getElementById('parentSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.parent-row').forEach(row => {
            const content = Array.from(row.querySelectorAll('.searchable-data'))
                            .map(el => el.textContent.toLowerCase())
                            .join(' ');
            row.style.display = content.includes(term) ? '' : 'none';
        });
    });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>