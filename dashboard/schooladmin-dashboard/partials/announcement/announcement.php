<?php
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;

// Fetch active announcements
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

// Fetch active classes
$classesStmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? AND status = 'active'");
$classesStmt->execute([$schoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
if (!$isAjax) { ob_start(); }
?>

<div class="px-4 py-8 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Komunikimi</h1>
            <p class="text-sm text-slate-500 font-medium">Njoftimet aktive që shfaqen në dashboard.</p>
        </div>
        <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" 
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
            Njoftim i Ri
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php if(empty($announcements)): ?>
            <div class="col-span-full py-20 text-center bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10">
                <p class="text-slate-400 font-medium">Nuk ka njoftime aktive për momentin.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($announcements as $ann): ?>
        <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex flex-col justify-between relative group hover:border-indigo-300 transition-colors">
            <?php if($ann['expires_at']): ?>
                <div class="absolute top-3 right-3 px-2 py-1 bg-amber-50 dark:bg-amber-900/20 rounded text-[9px] text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider">
                    Skadon: <?= date('d/m', strtotime($ann['expires_at'])) ?>
                </div>
            <?php endif; ?>
            
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <span class="px-2 py-1 text-[10px] font-bold uppercase rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400">
                        <?= $ann['target_role'] === 'all' ? 'Të gjithë' : ($ann['target_role'] === 'teacher' ? 'Mësuesit' : 'Nxënësit') ?> 
                        <?= $ann['class_name'] ? "• Klasa {$ann['class_name']}" : "" ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">
                        <?= date('d M, Y', strtotime($ann['created_at'])) ?>
                    </span>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white mb-2 leading-tight text-lg"><?= htmlspecialchars($ann['title']) ?></h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 line-clamp-4"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
            </div>
            
            <div class="border-t border-slate-50 dark:border-white/5 pt-4 flex justify-end">
                <button onclick="deleteAnn(<?= $ann['id'] ?>)" class="text-rose-500 text-xs font-bold uppercase tracking-widest hover:text-rose-700 transition px-2 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/10">Fshij</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-white/10 shadow-2xl">
        <h2 class="text-xl font-bold mb-6 text-slate-900 dark:text-white">Krijo Njoftim</h2>
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/save-announcement.php" method="POST" class="space-y-5">
            
            <div>
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Titulli i njoftimit</label>
                <input type="text" name="title" required placeholder="p.sh. Pushim zyrtar" class="w-full p-3.5 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Kush e sheh?</label>
                    <select name="target_role" id="roleSel" onchange="toggleCls()" class="w-full p-3.5 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500">
                        <option value="all">Të gjithë</option>
                        <option value="teacher">Mësuesit</option>
                        <option value="student">Nxënësit</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Data e Skadimit</label>
                    <input type="date" name="expires_at" class="w-full p-3.5 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div id="classSelectDiv" class="hidden animate-in slide-in-from-top-2 duration-200">
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Përzgjidh Klasën Specifike</label>
                <select name="class_id" class="w-full p-3.5 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Të gjitha klasat (Gjithë nxënësit)</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Mesazhi</label>
                <textarea name="message" rows="4" required placeholder="Shkruani detajet e njoftimit këtu..." class="w-full p-3.5 mt-1 rounded-xl bg-slate-50 dark:bg-gray-800 border-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('announcementModal').classList.add('hidden')" class="flex-1 p-4 bg-slate-100 dark:bg-gray-800 rounded-2xl font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-gray-700 transition">Anulo</button>
                <button type="submit" class="flex-1 p-4 bg-indigo-600 text-white rounded-2xl font-bold shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-500 transition active:scale-95">Dërgo Njoftimin</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCls() {
    const isStudent = document.getElementById('roleSel').value === 'student';
    const div = document.getElementById('classSelectDiv');
    div.classList.toggle('hidden', !isStudent);
    if(!isStudent) {
        div.querySelector('select').value = ""; // Reset class selection if not student
    }
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