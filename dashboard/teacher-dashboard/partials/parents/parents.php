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

$stmt = $pdo->prepare("
    SELECT 
        s.name AS student_name,
        p.name AS parent_name,
        p.phone AS parent_phone,
        p.email AS parent_email
    FROM students s
    INNER JOIN student_class sc 
        ON sc.student_id = s.student_id
    INNER JOIN parent_student ps 
        ON ps.student_id = s.student_id
    INNER JOIN parents p 
        ON p.id = ps.parent_id
    WHERE sc.class_id = ?
      AND s.school_id = ?
    ORDER BY s.name ASC
");

$stmt->execute([$classId, $schoolId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);


ob_start();
?>

<div class="max-w-6xl mx-auto px-4 py-10">
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Kontaktet e Prindërve</h1>
            <p class="text-slate-500 mt-1">Lista zyrtare e kontakteve për prindërit e klasës sate.</p>
        </div>
        <button onclick="window.print()" class="bg-white border border-slate-200 px-5 py-2.5 rounded-2xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition flex items-center gap-2 shadow-sm">
            <span>🖨️</span> Printo Listën
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-[2.5rem] overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100">
                    <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Nxënësi</th>
                    <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Prindi</th>
                    <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Telefoni</th>
                    <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-[0.2em]">E-mail</th>
                    <th class="px-8 py-5 text-right text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Veprime</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center text-slate-400 italic">
                            <div class="flex flex-col items-center gap-2">
                                <span class="text-3xl">👥</span>
                                <span>Nuk u gjet asnjë lidhje prind-nxënës për këtë klasë.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="px-8 py-5">
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($row['student_name']) ?></span>
                            </td>
                            
                            <td class="px-8 py-5">
                                <span class="font-semibold text-slate-700">
                                    <?= htmlspecialchars($row['parent_first_name'] . ' ' . $row['parent_last_name']) ?>
                                </span>
                            </td>

                            <td class="px-8 py-5">
                                <?php if (!empty($row['parent_phone'])): ?>
                                    <a href="tel:<?= $row['parent_phone'] ?>" class="text-sm text-slate-600 hover:text-blue-600 font-bold flex items-center gap-2">
                                        <span class="text-slate-300">📞</span> <?= htmlspecialchars($row['parent_phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-300 italic italic">Nuk ka numër</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-5">
                                <?php if (!empty($row['parent_email'])): ?>
                                    <a href="mailto:<?= $row['parent_email'] ?>" class="text-sm text-slate-600 hover:text-blue-600 font-medium flex items-center gap-2">
                                        <span class="text-slate-300">✉️</span> <?= htmlspecialchars($row['parent_email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-300 italic italic">Pa email</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-all transform translate-x-2 group-hover:translate-x-0">
                                    <a href="tel:<?= $row['parent_phone'] ?>" class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl hover:bg-emerald-600 hover:text-white transition shadow-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-8 px-6 py-4 bg-slate-900 rounded-2xl flex items-center justify-between shadow-lg shadow-slate-200">
        <span class="text-slate-400 text-xs font-black uppercase tracking-widest">Statistikat e klasës</span>
        <span class="text-white font-bold text-sm">Gjithsej: <?= count($results) ?> lidhje aktive</span>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';