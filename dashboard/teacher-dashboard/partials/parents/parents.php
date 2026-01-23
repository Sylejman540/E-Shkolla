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

<div class="p-6 lg:p-10 max-w-7xl mx-auto space-y-10">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 pb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Kontaktet e Prindërve</h1>
            <p class="text-slate-500 text-sm mt-1">Lista zyrtare dhe mjetet e komunikimit për prindërit e klasës.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="no-print inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Printo Orarin
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-indigo-600 min-h-[140px] p-6 rounded-2xl text-white shadow-lg shadow-indigo-100 flex items-center justify-between transition-transform hover:scale-[1.01]">
            <div>
                <p class="text-[10px] font-bold opacity-70 uppercase tracking-[0.2em] mb-1">Lidhje Aktive</p>
                <p class="text-4xl font-black"><?= $totalActive ?></p>
            </div>
            <span class="text-5xl opacity-20">📊</span>
        </div>

        <div class="bg-white border border-slate-200 min-h-[140px] p-6 rounded-2xl shadow-sm flex items-center justify-between transition-transform hover:scale-[1.01]">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Pa E-mail</p>
                <p class="text-4xl font-black text-slate-900"><?= $noEmail ?></p>
            </div>
            <span class="text-5xl">📧</span>
        </div>

        <div class="bg-white border border-slate-200 min-h-[140px] p-6 rounded-2xl shadow-sm flex items-center justify-between transition-transform hover:scale-[1.01]">
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-[0.2em] mb-1">Pa Telefon</p>
                <p class="text-4xl font-black text-slate-900"><?= $noPhone ?></p>
            </div>
            <span class="text-5xl">📱</span>
        </div>
    </div>

    <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl flex items-center gap-4">
        <div class="relative flex-1">
            <span class="absolute left-4 top-3.5 text-slate-400">🔍</span>
            <input type="text" id="parentSearch" placeholder="Kërko me emër nxënësi ose prindi..." 
                   class="w-full pl-12 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all shadow-sm">
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse" id="parentTable">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200">
                    <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Nxënësi</th>
                    <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Prindi</th>
                    <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Telefoni</th>
                    <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">E-mail</th>
                    <th class="px-8 py-5 text-right text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Veprime</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center text-slate-400 italic font-medium">
                            Nuk u gjet asnjë lidhje prind-nxënës.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                        <tr class="hover:bg-indigo-50/30 transition-colors group parent-row">
                            <td class="px-8 py-5 font-bold text-slate-900 searchable-data"><?= htmlspecialchars($row['student_name']) ?></td>
                            <td class="px-8 py-5 text-slate-600 font-semibold searchable-data"><?= htmlspecialchars($row['parent_name']) ?></td>
                            <td class="px-8 py-5">
                                <?php if (!empty($row['parent_phone'])): ?>
                                    <a href="tel:<?= $row['parent_phone'] ?>" class="text-sm text-slate-900 font-bold hover:text-indigo-600 transition flex items-center gap-2">
                                        <span class="opacity-30">📞</span> <?= htmlspecialchars($row['parent_phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded">Mungon</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5">
                                <?php if (!empty($row['parent_email'])): ?>
                                    <a href="mailto:<?= $row['parent_email'] ?>" class="text-sm text-indigo-600 font-medium hover:underline flex items-center gap-2">
                                        <span class="opacity-30 text-slate-900">✉️</span> <?= htmlspecialchars($row['parent_email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest bg-slate-50 px-2 py-1 rounded">Pa Email</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-2 bg-white border border-slate-100 rounded-lg shadow-sm hover:text-indigo-600 transition" title="Edito">✏️</button>
                                    <button class="p-2 bg-white border border-slate-100 rounded-lg shadow-sm hover:text-rose-600 transition" title="Fshij">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between items-center text-slate-400">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em]">Sistemi i Menaxhimit të Shkollës</p>
        <p class="text-xs font-medium">Totali: <?= count($results) ?> nxënës</p>
    </div>
</div>

<script>
    // Robust Live Search
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