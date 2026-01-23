<?php
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Query: Marrim vetëm njoftimet që nuk kanë skaduar ende (ose s'kanë datë skadimi)
$stmt = $pdo->prepare("
    SELECT a.*, c.grade as class_name 
    FROM announcements a 
    LEFT JOIN classes c ON a.class_id = c.id 
    WHERE a.school_id = ? 
    AND (a.expires_at IS NULL OR a.expires_at >= CURDATE()) 
    ORDER BY a.created_at DESC
");
$stmt->execute([$schoolId]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$classesStmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? AND status = 'active'");
$classesStmt->execute([$schoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
if (!$isAjax) { ob_start(); }
?>

<div class="px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Komunikimi</h1>
            <p class="text-sm text-slate-500">Njoftimet aktive që shfaqen në dashboard.</p>
        </div>
        <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            Njoftim i Ri
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($announcements as $ann): ?>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex flex-col justify-between relative">
            <?php if($ann['expires_at']): ?>
                <div class="absolute top-2 right-2 px-2 py-1 bg-slate-100 dark:bg-gray-800 rounded text-[9px] text-slate-500 font-bold uppercase">
                    Skadon: <?= date('d/m', strtotime($ann['expires_at'])) ?>
                </div>
            <?php endif; ?>
            
            <div>
                <div class="flex justify-between mb-4">
                    <span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                        <?= $ann['target_role'] ?> <?= $ann['class_name'] ? "({$ann['class_name']})" : "" ?>
                    </span>
                    <span class="text-xs text-slate-400 font-medium"><?= date('d/m/Y', strtotime($ann['created_at'])) ?></span>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white mb-2 leading-tight"><?= htmlspecialchars($ann['title']) ?></h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
            </div>
            <div class="border-t border-slate-50 dark:border-white/5 pt-4 text-right">
                <button onclick="deleteAnn(<?= $ann['id'] ?>)" class="text-rose-500 text-xs font-bold uppercase hover:text-rose-700 transition">Fshij</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-3xl p-8 border border-slate-200 dark:border-white/10 shadow-2xl">
        <h2 class="text-xl font-bold mb-6 text-slate-900 dark:text-white">Krijo Njoftim</h2>
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/save-announcement.php" method="POST" class="space-y-4">
            
            <div>
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Titulli</label>
                <input type="text" name="title" required placeholder="p.sh. Pushim zyrtar" class="w-full p-3 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Kush e sheh?</label>
                    <select name="target_role" id="roleSel" onchange="toggleCls()" class="w-full p-3 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none">
                        <option value="all">Të gjithë</option>
                        <option value="teacher">Mësuesit</option>
                        <option value="student">Nxënësit</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Skadon më (Opsionale)</label>
                    <input type="date" name="expires_at" class="w-full p-3 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none">
                </div>
            </div>

            <div id="classSelectDiv" class="hidden">
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Përzgjidh Klasën</label>
                <select name="class_id" class="w-full p-3 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none">
                    <option value="">Gjithë nxënësit</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Mesazhi</label>
                <textarea name="message" rows="4" required placeholder="Shkruani njoftimin..." class="w-full p-3 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none"></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('announcementModal').classList.add('hidden')" class="flex-1 p-3 bg-slate-100 dark:bg-gray-800 rounded-xl font-semibold text-slate-600 dark:text-slate-300">Anulo</button>
                <button type="submit" class="flex-1 p-3 bg-indigo-600 text-white rounded-xl font-semibold shadow-lg hover:bg-indigo-500 transition">Dërgo</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCls() {
    const isStudent = document.getElementById('roleSel').value === 'student';
    document.getElementById('classSelectDiv').classList.toggle('hidden', !isStudent);
}
function deleteAnn(id) {
    if(confirm('A jeni të sigurt? Njoftimi do të fshihet përgjithmonë.')) {
        window.location.href = `/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/delete-announcement.php?id=${id}`;
    }
}
</script>

<?php 
if (!$isAjax) { $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; }
?>