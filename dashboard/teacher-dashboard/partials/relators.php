<?php
require_once __DIR__ . '/../../../db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();

/* =====================================================
   PHASE A — IDENTITY (Fixes "Undefined array key")
===================================================== */
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'teacher' || empty($user['id'])) {
    http_response_code(403);
    exit('I paautorizuar.');
}

$userId   = (int)$user['id'];
$schoolId = (int)$user['school_id'];

// Marrim ID-në e mësimdhënësit nga tabela teachers
$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? AND school_id = ? LIMIT 1");
$tStmt->execute([$userId, $schoolId]);
$teacherId = (int)$tStmt->fetchColumn();

if (!$teacherId) {
    exit('Mësimdhënësi nuk u gjet.');
}

/* =====================================================
   PHASE B — SQL QUERY (Fixes "Column not found")
===================================================== */
// Kemi ndryshuar st.id në s.id (ose student_id) varësisht nga tabela juaj
$relacionStmt = $pdo->prepare("
    SELECT 
        c.grade,
        sub.subject_name,
        COUNT(DISTINCT tc.class_id) as nxenes_total, 
        AVG(g.grade) as mesatarja_lendes,
        (SELECT COUNT(*) FROM attendance a 
         WHERE a.teacher_id = tc.teacher_id 
         AND a.class_id = tc.class_id 
         AND a.present = 0) as total_mungesa
    FROM teacher_class tc
    JOIN classes c ON tc.class_id = c.id
    JOIN subjects sub ON tc.subject_id = sub.id
    LEFT JOIN grades g ON g.class_id = c.id AND g.subject_id = sub.id
    WHERE tc.teacher_id = ? AND c.school_id = ?
    GROUP BY c.id, sub.id
");
$relacionStmt->execute([$teacherId, $schoolId]);
$raportet = $relacionStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-7xl mx-auto p-4 lg:p-8 space-y-8 animate-in fade-in duration-500">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Relatoret & Raportet</h1>
            <p class="text-slate-500 font-medium italic">Pasqyra e suksesit dhe vijueshmërisë</p>
        </div>
        <div class="no-print">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-sm font-bold shadow-lg hover:bg-indigo-700 transition-all">
                Exporto si PDF
            </button>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="p-6 text-xs font-black text-slate-400 uppercase tracking-widest">Klasa</th>
                    <th class="p-6 text-xs font-black text-slate-400 uppercase tracking-widest">Lënda</th>
                    <th class="p-6 text-xs font-black text-slate-400 uppercase tracking-widest">Nota Mesatare</th>
                    <th class="p-6 text-xs font-black text-slate-400 uppercase tracking-widest">Mungesa</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if ($raportet): ?>
                    <?php foreach ($raportet as $r): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="p-6 font-bold text-slate-900"><?= htmlspecialchars($r['grade']) ?></td>
                        <td class="p-6 text-slate-600"><?= htmlspecialchars($r['subject_name']) ?></td>
                        <td class="p-6">
                            <span class="px-3 py-1 rounded-lg font-black <?= $r['mesatarja_lendes'] >= 4 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
                                <?= $r['mesatarja_lendes'] ? number_format($r['mesatarja_lendes'], 2) : '0.00' ?>
                            </span>
                        </td>
                        <td class="p-6 text-rose-600 font-bold"><?= (int)$r['total_mungesa'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-10 text-center text-slate-400 italic">Nuk u gjetën të dhëna për raportet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php'; // Sigurohuni që kjo rrugë është e saktë për index.php tuaj
?>