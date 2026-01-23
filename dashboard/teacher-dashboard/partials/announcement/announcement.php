<?php
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['id'] ?? null;

if (!$schoolId || !$teacherId) {
    die('Aksesi i mohuar.');
}

// 1. Fetching logic: Only announcements made by this teacher
$stmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE teacher_id = ? 
    AND (expires_at IS NULL OR expires_at >= CURDATE()) 
    ORDER BY created_at DESC
");
$stmt->execute([$teacherId]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
if (!$isAjax) { ob_start(); }
?>

<div class="p-6 lg:p-10 max-w-7xl mx-auto space-y-10">
    
    <div class="flex justify-between items-end border-b border-slate-200 pb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Komunikimi</h1>
            <p class="text-slate-500 text-sm mt-1">Dërgoni njoftime për nxënësit dhe prindërit.</p>
        </div>
        <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-100 hover:bg-indigo-500 transition-all active:scale-95 uppercase tracking-widest">
            Njoftim i Ri
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php if (empty($announcements)): ?>
            <div class="col-span-full bg-white border-2 border-dashed border-slate-200 rounded-3xl p-20 text-center">
                <p class="text-slate-400 font-bold italic uppercase text-[10px] tracking-[0.2em]">Nuk ka njoftime aktive</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
            <div class="group bg-white p-8 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between relative transition-all hover:border-indigo-400">
                <?php if($ann['expires_at']): ?>
                    <div class="absolute top-0 right-0 px-3 py-1 bg-rose-500 text-white rounded-bl-xl text-[9px] font-black uppercase tracking-tighter">
                        Skadon: <?= date('d/m', strtotime($ann['expires_at'])) ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <span class="px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.15em] rounded bg-slate-900 text-white">
                            <?= $ann['target_audience'] === 'all' ? 'Të gjithë' : ($ann['target_audience'] === 'students' ? 'Nxënës' : 'Prindër') ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-bold"><?= date('d/m/Y', strtotime($ann['created_at'])) ?></span>
                    </div>
                    <h3 class="font-black text-slate-900 mb-3 text-xl leading-tight tracking-tight italic"><?= htmlspecialchars($ann['title']) ?></h3>
                    <p class="text-sm text-slate-500 leading-relaxed font-medium line-clamp-4"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-50 flex justify-between items-center">
                    <button onclick="deleteAnn(<?= $ann['id'] ?>)" class="text-rose-500 text-[10px] font-black uppercase tracking-widest hover:underline">Fshij</button>
                    <span class="text-[9px] font-bold text-slate-300 italic">Mësimdhënësi ID: #<?= $teacherId ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-md">
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 border border-slate-200 shadow-2xl transition-all">
        <h2 class="text-2xl font-black mb-8 text-slate-900 tracking-tight">Krijo Njoftim</h2>
        <form action="/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/save-announcement.php" method="POST" class="space-y-4">
            
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
function deleteAnn(id) {
    if(confirm('A jeni të sigurt? Kjo nuk mund të kthehet prapa.')) {
        window.location.href = `/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/delete-announcement.php?id=${id}`;
    }
}
</script>

<?php 
if (!$isAjax) { $content = ob_get_clean(); require_once __DIR__ . '/../index.php'; }
?>