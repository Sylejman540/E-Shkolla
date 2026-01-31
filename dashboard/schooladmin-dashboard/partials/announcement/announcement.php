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

<div class="px-4 py-8 max-w-7xl mx-auto animate-in fade-in duration-500 font-sans tracking-tight" style="font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-6">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 text-[10px] font-medium uppercase tracking-widest text-slate-400">
                    <li class="inline-flex items-center">Admin</li>
                    <li><div class="flex items-center"><span class="mx-2">/</span> Komunikimi</div></li>
                </ol>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Qendra e Njoftimeve</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Menaxhoni informatat dhe njoftimet për stafin dhe nxënësit.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('announcementModal').classList.remove('hidden')" 
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-indigo-700 transition-all active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                Njoftim i Ri
            </button>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php if(empty($announcements)): ?>
            <div class="col-span-full py-20 text-center bg-slate-50 dark:bg-gray-800/30 rounded-3xl border border-dashed border-slate-200 dark:border-white/5">
                <h3 class="text-slate-900 dark:text-white font-semibold text-base">Nuk ka njoftime</h3>
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Shtoni një njoftim të ri për të komunikuar me shkollën tuaj.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($announcements as $ann): ?>
        <div class="bg-white dark:bg-gray-900 p-1 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col group overflow-hidden">
            <div class="p-5 flex-1">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex flex-wrap gap-2">
                        <?php 
                            $roleColor = $ann['target_role'] === 'all' ? 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400' : 
                                        ($ann['target_role'] === 'teacher' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 
                                        'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400');
                        ?>
                        <span class="px-2.5 py-0.5 text-[10px] font-semibold uppercase rounded-lg <?= $roleColor ?> tracking-wider">
                            <?= $ann['target_role'] === 'all' ? 'Gjithë' : ($ann['target_role'] === 'teacher' ? 'Mësues' : 'Nxënës') ?>
                        </span>
                        
                        <?php if($ann['class_name']): ?>
                        <span class="px-2.5 py-0.5 text-[10px] font-semibold uppercase rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 tracking-wider">
                            <?= htmlspecialchars($ann['class_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if($ann['expires_at']): ?>
                        <span class="text-[10px] font-medium text-amber-600 bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 rounded-lg">
                            Skadon: <?= date('d.m.y', strtotime($ann['expires_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h3 class="font-semibold text-slate-900 dark:text-white mb-2 leading-snug text-base group-hover:text-indigo-600 transition-colors">
                    <?= htmlspecialchars($ann['title']) ?>
                </h3>
                
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-3 font-normal">
                    <?= nl2br(htmlspecialchars($ann['content'])) ?>
                </p>
            </div>
            
            <div class="px-5 py-3 bg-slate-50/50 dark:bg-white/5 flex items-center justify-between border-t border-slate-100 dark:border-white/5">
                <div class="flex flex-col">
                    <span class="text-[9px] font-medium text-slate-400 uppercase tracking-tight">Publikuar</span>
                    <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300"><?= date('d M, Y', strtotime($ann['created_at'])) ?></span>
                </div>

                <button onclick="deleteAnn(<?= $ann['id'] ?>)" 
                    class="p-1.5 text-slate-400 hover:text-rose-500 transition-colors"
                    title="Fshij">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-2xl p-6 md:p-8 border border-slate-200 dark:border-white/10 shadow-xl overflow-y-auto font-sans">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Krijo Njoftim</h2>
            <button onclick="document.getElementById('announcementModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/save-announcement.php" method="POST">
            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Titulli i njoftimit</label>
                    <input type="text" name="title" required placeholder="p.sh. Pushim zyrtar"
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-indigo-600 focus:bg-white outline-none transition dark:bg-white/5 dark:border-white/10 dark:text-white" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Kush e sheh?</label>
                        <select name="target_role" id="roleSel" onchange="toggleCls()"
                            class="block w-full rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm dark:bg-gray-800 dark:border-white/10 dark:text-white outline-none focus:ring-2 focus:ring-indigo-600">
                            <option value="all">Të gjithë</option>
                            <option value="teacher">Mësuesit</option>
                            <option value="student">Nxënësit</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Skadimi</label>
                        <input type="date" name="expires_at"
                            class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-indigo-600 dark:bg-white/5 dark:border-white/10 dark:text-white outline-none" />
                    </div>
                </div>

                <div id="classSelectDiv" class="hidden animate-in slide-in-from-top-2 duration-200">
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Përzgjidh Klasën</label>
                    <select name="class_id"
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 p-2 text-sm dark:bg-gray-800 dark:border-white/10 dark:text-white outline-none focus:ring-2 focus:ring-indigo-600">
                        <option value="">Të gjitha klasat</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Mesazhi</label>
                    <textarea name="message" rows="4" required placeholder="Shkruani detajet..."
                        class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-indigo-600 dark:bg-white/5 dark:border-white/10 dark:text-white outline-none resize-none"></textarea>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button"
                    onclick="document.getElementById('announcementModal').classList.add('hidden')"
                    class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 transition">
                    Anulo
                </button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-6 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-700 active:scale-95 transition">
                    Dërgo Njoftimin
                </button>
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
        div.querySelector('select').value = ""; 
    }
}

function deleteAnn(id) {
    if(confirm('Njoftimi do të fshihet përgjithmonë.')) {
        window.location.href = `/E-Shkolla/dashboard/schooladmin-dashboard/partials/announcement/delete-announcement.php?id=${id}`;
    }
}
</script>

<?php 
if (!$isAjax) { $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; }
?>