<?php
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$schoolId = $_SESSION['user']['school_id'] ?? null;

if (!$schoolId) {
    die('Aksesi i mohuar.');
}

// Optimized Query
$stmt = $pdo->prepare("
    SELECT 
        s.name AS student_name,
        p.name AS parent_name,
        p.phone AS parent_phone,
        p.email AS parent_email
    FROM students s
    INNER JOIN student_class sc ON sc.student_id = s.student_id
    INNER JOIN parent_student ps ON ps.student_id = s.student_id
    INNER JOIN parents p ON p.id = ps.parent_id
    WHERE sc.class_id = ? AND s.school_id = ?
    ORDER BY s.name ASC
");

$stmt->execute([$classId, $schoolId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalActive = count($results);
$noEmail = 0;
$noPhone = 0;
foreach ($results as $r) {
    if (empty($r['parent_email'])) $noEmail++;
    if (empty($r['parent_phone'])) $noPhone++;
}


ob_start();
?>

<div class="max-w-7xl mx-auto p-6 lg:p-10 space-y-6 animate-in fade-in duration-500 text-slate-700 font-inter">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-5 print:hidden">
        <div>
            <nav class="flex mb-1" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-[10px] font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-500 font-semibold uppercase tracking-wider">Kontaktet</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Prindërit e Klasës Kujdestare</h1>
            <p class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                Lista zyrtare e kontakteve për klasën tuaj.
            </p>
        </div>

        <div class="flex items-center gap-2 no-print">
            <button onclick="window.print()" class="bg-white border border-slate-200 px-3 py-1.5 text-[10px] font-semibold rounded-lg transition-all text-slate-600 hover:bg-slate-50 flex items-center gap-2 shadow-sm">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Eksporto Listën
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-indigo-600 rounded-xl py-4 px-5 flex items-center gap-4 shadow-md shadow-indigo-100 text-white">
            <div class="flex-shrink-0 w-11 h-11 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm border border-white/30">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-indigo-100 uppercase tracking-widest mb-0.5">Nxënës</p>
                <h3 class="text-xl font-bold"><?= $totalActive ?> Totali</h3>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm group">
            <div class="flex-shrink-0 w-11 h-11 bg-slate-50 text-slate-400 rounded-lg flex items-center justify-center border border-slate-100 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Pa E-mail</p>
                <h3 class="text-lg font-bold text-slate-900"><?= $noEmail ?> Mungojnë</h3>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 py-4 px-5 flex items-center gap-4 shadow-sm group">
            <div class="flex-shrink-0 w-11 h-11 bg-slate-50 text-rose-400 rounded-lg flex items-center justify-center border border-slate-100 group-hover:bg-rose-50 group-hover:text-rose-600 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Pa Telefon</p>
                <h3 class="text-lg font-bold text-slate-900"><?= $noPhone ?> Mungojnë</h3>
            </div>
        </div>
    </div>

    <div class="relative no-print">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
        <input type="text" id="parentSearch" placeholder="Kërko me emër..." 
               class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[13px] focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-400 outline-none transition-all">
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse" id="parentTable">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nxënësi</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Prindi</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Telefoni</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">E-mail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($results as $row): ?>
                <tr class="hover:bg-slate-50/50 transition-colors parent-row group">
                    <td class="px-6 py-4">
                        <span class="text-[13px] font-semibold text-slate-900 searchable-data"><?= htmlspecialchars($row['student_name']) ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-[12px] font-medium text-slate-600 searchable-data"><?= htmlspecialchars($row['parent_name']) ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (!empty($row['parent_phone'])): ?>
                            <a href="tel:<?= $row['parent_phone'] ?>" class="text-[12px] text-slate-900 font-bold hover:text-indigo-600 transition inline-flex items-center gap-1.5">
                                <span class="opacity-40 text-[10px]">📞</span> <?= htmlspecialchars($row['parent_phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-[9px] font-bold text-slate-300 uppercase tracking-tighter">Nuk ka telefon</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (!empty($row['parent_email'])): ?>
                            <a href="mailto:<?= $row['parent_email'] ?>" class="text-[12px] text-indigo-500 font-medium hover:underline inline-flex items-center gap-1.5">
                                <span class="opacity-40 text-slate-900 text-[10px]">✉️</span> <?= htmlspecialchars($row['parent_email']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-[9px] font-bold text-slate-300 uppercase tracking-tighter italic">Pa adresë</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center text-[10px] text-slate-400 pt-2 font-medium">
        <p class="tracking-tight italic uppercase">© E-SHKOLLA MODULI I KONTAKTEVE</p>
        <p class="font-bold">TOTAL: <?= count($results) ?> ENTITETE</p>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    .font-inter { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

    @media print {
        .font-inter { font-family: sans-serif; }
        .no-print { display: none !important; }
        .shadow-sm, .shadow-md { shadow: none !important; }
        body { background: white !important; }
    }
</style>

<script>
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