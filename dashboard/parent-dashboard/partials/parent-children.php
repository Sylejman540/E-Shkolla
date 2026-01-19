<?php
declare(strict_types=1);

/* =====================================================
   1. BACKEND LOGIC (Marrja e të dhënave)
===================================================== */
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth Guard - Sigurohemi që prindi është i kyçur
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int)$_SESSION['user']['id'];
$schoolId = (int)$_SESSION['user']['school_id'];

try {
    // 1. Gjejmë ID-në e prindit në tabelën 'parents'
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$userId, $schoolId]);
    $parentId = (int)$stmt->fetchColumn();

    if (!$parentId) {
        throw new Exception("Profili i prindit nuk u gjet në sistem.");
    }

    // 2. Marrja e listës së fëmijëve të lidhur me këtë prind
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id, 
            s.name AS student_name, 
            s.class_name, 
            s.status,
            (SELECT ROUND(AVG(grade), 2) FROM grades WHERE student_id = s.student_id) as avg_grade
        FROM parent_student ps
        JOIN students s ON s.student_id = ps.student_id
        WHERE ps.parent_id = ? AND s.school_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$parentId, $schoolId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("<div class='p-10 text-red-600 font-bold'>Gabim: " . $e->getMessage() . "</div>");
}

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-8 pb-10">
    
    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 bg-indigo-50 rounded-full opacity-50"></div>
        
        <div class="relative z-10">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Fëmijët e Mi 👨‍👩‍👧‍👦</h1>
            <p class="text-slate-500 font-medium mt-2 max-w-2xl">
                Mirësevini në portalin e prindit. Më poshtë keni listën e fëmijëve tuaj. 
                Zgjidhni butonin <strong>"Paneli"</strong> për të parë ecurinë e detajuar për secilin prej tyre.
            </p>
        </div>
    </div>

    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400">Nxënësi</th>
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400 text-center">Klasa</th>
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400 text-center">Mesatarja</th>
                        <th class="px-8 py-6 text-xs font-bold uppercase tracking-widest text-slate-400 text-center">Statusi</th>
                        <th class="px-8 py-6 text-right text-xs font-bold uppercase tracking-widest text-slate-400">Veprime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!empty($children)): ?>
                        <?php foreach ($children as $child): ?>
                            <tr class="hover:bg-slate-50/30 transition-all group">
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-indigo-100">
                                            <?= strtoupper(substr($child['student_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-slate-800 text-base group-hover:text-indigo-600 transition-colors">
                                                <?= htmlspecialchars($child['student_name']) ?>
                                            </span>
                                            <span class="text-xs text-slate-400 font-medium">ID: #<?= $child['student_id'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <span class="px-4 py-1.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold uppercase">
                                        <?= htmlspecialchars($child['class_name']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <span class="text-lg font-black text-slate-900">
                                        <?= $child['avg_grade'] ?: '—' ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <?php if ($child['status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                            Aktiv
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                            Jo Aktiv
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex justify-end gap-2">
                                        <a href="/E-Shkolla/parent-dashboard?student_id=<?= $child['student_id'] ?>" 
                                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-2xl text-xs font-bold hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-200 transition-all active:scale-95">
                                            <span>📊</span> Paneli
                                        </a>
                                        
                                        <a href="/E-Shkolla/parent-grades?student_id=<?= $child['student_id'] ?>" 
                                           class="p-2.5 bg-white border border-slate-200 text-slate-500 rounded-2xl hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm" title="Notat">
                                            📝
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl">🔍</div>
                                    <div class="space-y-1">
                                        <p class="text-slate-900 font-bold text-lg">Nuk u gjet asnjë fëmijë</p>
                                        <p class="text-slate-400 text-sm">Ju lutem kontaktoni administratën e shkollës për t'u lidhur me nxënësin.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
// Thirret Layout-i kryesor i faqes
require_once __DIR__ . '/../index.php';
?>