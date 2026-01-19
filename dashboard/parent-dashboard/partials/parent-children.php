<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   AUTH GUARD
   ========================= */
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    header("Location: /E-Shkolla/login");
    exit();
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

/* =========================
   RESOLVE parent_id
   ========================= */
$stmt = $pdo->prepare("SELECT id FROM parents WHERE user_id = ? AND school_id = ?");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) {
    die('Profili i prindit nuk u gjet');
}

/* =========================
   FETCH CHILDREN
   ========================= */
$stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.name AS student_name,
        s.class_name,
        s.status,
        s.created_at
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ?
      AND s.school_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FRONTEND - CONTENT
   ========================= */
ob_start();
?>

<div class="space-y-6">
    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm p-8">
        <h2 class="text-2xl font-black text-slate-900 tracking-tight">Fëmijët e Mi 👨‍👩‍👧‍👦</h2>
        <p class="text-slate-500 font-medium mt-1">
            Menaxhoni dhe shikoni progresin e fëmijëve tuaj të regjistruar në sistem.
        </p>
    </div>

    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-50">
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Nxënësi</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Klasa</th>
                        <th class="px-8 py-5 text-xs font-bold uppercase tracking-widest text-slate-400">Statusi</th>
                        <th class="px-8 py-5 text-right text-xs font-bold uppercase tracking-widest text-slate-400">Veprime</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!empty($children)): ?>
                        <?php foreach ($children as $child): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                            <?= strtoupper(substr($child['student_name'], 0, 1)) ?>
                                        </div>
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($child['student_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-slate-600 font-medium">
                                    <?= htmlspecialchars($child['class_name']) ?>
                                </td>
                                <td class="px-8 py-5">
                                    <?php if ($child['status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                            Aktiv
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                            Inaktiv
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-5 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="/E-Shkolla/parent-dashboard?student_id=<?= $child['student_id'] ?>" 
                                           class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-colors">
                                           Paneli
                                        </a>
                                        <a href="/E-Shkolla/parent-grades?student_id=<?= $child['student_id'] ?>" 
                                           class="inline-flex items-center px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors">
                                           Notat
                                        </a>
                                        <a href="/E-Shkolla/parent-attendance?student_id=<?= $child['student_id'] ?>" 
                                           class="inline-flex items-center px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors">
                                           Prezenca
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-8 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="text-4xl">🔍</span>
                                    <p class="text-slate-400 font-medium italic">Nuk u gjet asnjë fëmijë i lidhur me llogarinë tuaj.</p>
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
require_once __DIR__ . '/../index.php';