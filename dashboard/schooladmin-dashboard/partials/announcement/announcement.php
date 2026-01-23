<?php
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Query për njoftimet
$stmt = $pdo->prepare("
    SELECT a.*, c.grade as class_name 
    FROM announcements a 
    LEFT JOIN classes c ON a.class_id = c.id 
    WHERE a.school_id = ? 
    ORDER BY a.created_at DESC
");
$stmt->execute([$schoolId]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query për klasat (për dropdown)
$classesStmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? AND status = 'active'");
$classesStmt->execute([$schoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Kontrolli AJAX për layout-in tënd
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
if (!$isAjax) { ob_start(); }
?>

<div class="px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Komunikimi</h1>
        <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-semibold hover:bg-indigo-500 transition shadow-lg">
            Njoftim i Ri
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($announcements as $ann): ?>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex justify-between mb-4">
                    <span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-indigo-100 text-indigo-700">
                        <?= $ann['target_role'] ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($ann['created_at'])) ?></span>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white mb-2"><?= htmlspecialchars($ann['title']) ?></h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
            </div>
            <div class="border-t pt-4 text-right">
                <button onclick="deleteAnn(<?= $ann['id'] ?>)" class="text-rose-500 text-xs font-bold uppercase">Fshij</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-3xl p-8 border border-slate-200 dark:border-white/10">
        <h2 class="text-xl font-bold mb-6 text-slate-900 dark:text-white">Krijo Njoftim</h2>
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/save-announcement.php" method="POST" class="space-y-4">
            <input type="text" name="title" required placeholder="Titulli" class="w-full p-3 rounded-xl bg-slate-100 dark:bg-gray-800 border-none">
            
            <div class="grid grid-cols-2 gap-4">
                <select name="target_role" id="roleSel" onchange="toggleCls()" class="w-full p-3 rounded-xl bg-slate-100 dark:bg-gray-800 border-none">
                    <option value="all">Të gjithë</option>
                    <option value="teacher">Mësuesit</option>
                    <option value="student">Nxënësit</option>
                </select>
                <select name="class_id" id="classSel" class="hidden w-full p-3 rounded-xl bg-slate-100 dark:bg-gray-800 border-none">
                    <option value="">Gjithë nxënësit</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['grade'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <textarea name="message" rows="4" required placeholder="Mesazhi..." class="w-full p-3 rounded-xl bg-slate-100 dark:bg-gray-800 border-none"></textarea>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('announcementModal').classList.add('hidden')" class="flex-1 p-3 bg-slate-100 dark:bg-gray-800 rounded-xl font-semibold">Anulo</button>
                <button type="submit" class="flex-1 p-3 bg-indigo-600 text-white rounded-xl font-semibold shadow-lg">Dërgo</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCls() {
    document.getElementById('classSel').classList.toggle('hidden', document.getElementById('roleSel').value !== 'student');
}
function deleteAnn(id) {
    if(confirm('A dëshironi ta fshini?')) window.location.href = `/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/delete-announcement?id=${id}`;
}
</script>

<?php 
if (!$isAjax) { $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; }
?>