<?php
require_once __DIR__ . '/../../../db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

/* =====================================================
   LOGJIKA E IDENTITETIT
===================================================== */
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'teacher' || empty($user['id'])) {
    http_response_code(403);
    exit('I paautorizuar.');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) exit('Mësimdhënësi nuk u gjet.');

/* =====================================================
   SQL: FILTRIMI SIPAS TEACHER_ID
===================================================== */
$relacionStmt = $pdo->prepare("
    SELECT 
        c.grade,
        sub.subject_name,
        AVG(g.grade) as mesatarja_lendes,
        (SELECT COUNT(*) FROM attendance a 
         WHERE a.teacher_id = ? 
         AND a.class_id = tc.class_id 
         AND a.subject_id = tc.subject_id
         AND a.present = 0) as total_mungesa
    FROM teacher_class tc
    JOIN classes c ON tc.class_id = c.id
    JOIN subjects sub ON tc.subject_id = sub.id
    LEFT JOIN grades g ON g.class_id = c.id 
         AND g.subject_id = sub.id 
         AND g.teacher_id = ?
    WHERE tc.teacher_id = ? AND c.school_id = ?
    GROUP BY c.id, sub.id
");
// Kalojmë teacherId për mungesat, notat dhe relacionin
$relacionStmt->execute([$teacherId, $teacherId, $teacherId, $schoolId]);
$raportet = $relacionStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-6xl mx-auto p-6 lg:p-10 space-y-6">
    
    <div class="flex justify-between items-end border-b-2 border-slate-100 pb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Raporti i Punës Individuale</h1>
            <p class="text-slate-500 text-xs mt-1">Gjeneruar më: <?= date('d.m.Y H:i') ?></p>
        </div>
        <button onclick="window.print()" class="no-print bg-blue-600 text-white px-5 py-2.5 rounded-lg text-xs font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" stroke-width="2"/></svg>
            PRINTO PDF
        </button>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase">Klasa</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase">Lënda</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase text-center">Nota Mesatare</th>
                    <th class="p-4 text-[11px] font-bold text-slate-500 uppercase text-center">Mungesa (nga ju)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if ($raportet): ?>
                    <?php foreach ($raportet as $r): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-4 font-semibold text-slate-700">Klasa <?= htmlspecialchars($r['grade']) ?></td>
                        <td class="p-4 text-slate-600"><?= htmlspecialchars($r['subject_name']) ?></td>
                        <td class="p-4 text-center">
                            <span class="font-mono font-bold <?= $r['mesatarja_lendes'] >= 4 ? 'text-emerald-600' : 'text-slate-800' ?>">
                                <?= $r['mesatarja_lendes'] ? number_format($r['mesatarja_lendes'], 2) : '—' ?>
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2 py-1 bg-rose-50 text-rose-600 rounded font-bold">
                                <?= (int)$r['total_mungesa'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-10 text-center text-slate-400 italic">Nuk u gjet asnjë rekord.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="hidden print:flex justify-between pt-16 text-[11px] font-bold uppercase text-slate-400">
        <div class="border-t border-slate-300 pt-2 w-40 text-center">Nënshkrimi</div>
        <div class="border-t border-slate-300 pt-2 w-40 text-center">Data e dorëzimit</div>
    </div>

</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>