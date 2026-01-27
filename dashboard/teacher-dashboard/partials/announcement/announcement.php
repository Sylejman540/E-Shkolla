<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

$user     = $_SESSION['user'] ?? null;
$role     = $user['role'] ?? null;
$userId   = (int)($user['id'] ?? 0);
$schoolId = (int)($user['school_id'] ?? 0);

if (!$userId || !$schoolId || !$role) {
    http_response_code(403);
    exit('Akses i ndaluar');
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if (!$isAjax) { ob_start(); }

/* =========================
   FETCH ANNOUNCEMENTS
========================= */
if ($role === 'teacher') {
    $stmt = $pdo->prepare("SELECT a.*, c.grade AS class_name FROM announcements a LEFT JOIN classes c ON c.id = a.class_id WHERE a.school_id = ? AND a.teacher_id = ? AND (a.expires_at IS NULL OR a.expires_at >= CURDATE()) ORDER BY a.created_at DESC");
    $stmt->execute([$schoolId, $userId]);
} else {
    // Shared logic for Student/Parent
    $target = ($role === 'student') ? 'student' : 'parent';
    $stmt = $pdo->prepare("SELECT DISTINCT a.*, c.grade AS class_name FROM announcements a INNER JOIN students s ON s.user_id = ? INNER JOIN classes c ON c.id = s.class_id WHERE a.school_id = ? AND a.class_id = s.class_id AND a.target_role IN (?, 'all') AND (a.expires_at IS NULL OR a.expires_at >= CURDATE()) ORDER BY a.created_at DESC");
    $stmt->execute([$userId, $schoolId, $target]);
}

$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$isAjax) { ob_start(); }
?>

<div class="max-w-6xl mx-auto space-y-6 pb-12 animate-in fade-in duration-500 font-inter text-slate-700">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
        <div>
            <nav class="flex mb-1" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-[10px] font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-500 font-semibold tracking-wide uppercase">Njoftimet</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Njoftimet e Shkollës</h1>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                Informacionet e fundit zyrtare.
            </p>
        </div>

        <?php if ($role === 'teacher'): ?>
            <button type="button" id="openAnnouncementModal" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-1.5 text-sm text-white shadow-sm hover:bg-indigo-500 transition-all active:scale-95">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Shto Njoftim
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="bg-slate-50 dark:bg-slate-900/50 border border-dashed border-slate-200 dark:border-slate-800 p-12 rounded-2xl text-center">
            <p class="text-slate-400 dark:text-slate-500 font-medium text-sm italic">Nuk ka njoftime aktive për momentin.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($announcements as $ann): ?>
                <div class="bg-white dark:bg-slate-900 rounded-xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-indigo-200 dark:hover:border-indigo-900/50 transition-all duration-200 group">
                    
                    <div class="flex justify-between items-start mb-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                            <?= htmlspecialchars($ann['target_role']) ?>
                            <?= $ann['class_name'] ? " • {$ann['class_name']}" : '' ?>
                        </span>
                        <span class="text-[10px] font-medium text-slate-400">
                            <?= date('d.m.Y', strtotime($ann['created_at'])) ?>
                        </span>
                    </div>

                    <h3 class="text-[15px] font-bold text-slate-800 dark:text-white mb-2 leading-tight group-hover:text-indigo-600 transition-colors">
                        <?= htmlspecialchars($ann['title']) ?>
                    </h3>

                    <p class="text-[12px] text-slate-500 dark:text-slate-400 leading-normal mb-5 line-clamp-3">
                        <?= htmlspecialchars($ann['content']) ?>
                    </p>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-50 dark:border-slate-800/50">
                        <?php if (!empty($ann['expires_at'])): ?>
                            <div class="flex items-center gap-1 text-[10px] font-semibold text-amber-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Skadon: <?= date('d/m/y', strtotime($ann['expires_at'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="text-[9px] font-bold text-emerald-600 uppercase tracking-tighter">Pa skadim</div>
                        <?php endif; ?>

                        <?php if ($role === 'teacher'): ?>
                            <a href="/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/delete-announcement.php?id=<?= (int)$ann['id'] ?>"
                               onclick="return confirm('A jeni të sigurt?')"
                               class="text-rose-500 hover:text-rose-700 text-[10px] font-bold uppercase tracking-tighter">
                                Fshij
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="flex justify-between items-center text-[10px] text-slate-400 font-medium pt-4">
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Live</span>
            <span>ID: <?= strtoupper(substr(session_id(), 0, 8)) ?></span>
        </div>
        <p>© <?= date('Y') ?> E-Shkolla Management</p>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    .font-inter { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
    
    /* Optimize clamp for reduced size */
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;  
        overflow: hidden;
    }
</style>

<div id="announcementModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 transition-all duration-300">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md"></div>
    
    <div class="relative bg-white dark:bg-slate-900 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-800 animate-in zoom-in-95 duration-200">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Krijo Njoftim</h2>
                <p class="text-[11px] text-slate-500">Plotësoni detajet e njoftimit më poshtë</p>
            </div>
            <button type="button" id="closeAnnouncementModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/save-announcement.php" method="POST" class="p-6 space-y-5 font-inter">
            
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Titulli i Njoftimit</label>
                <input type="text" name="title" required placeholder="p.sh. Provimi i radhës..." 
                    class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-slate-400">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Mesazhi i plotë</label>
                <textarea name="message" rows="4" required placeholder="Shkruani përmbajtjen këtu..." 
                    class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all resize-none placeholder:text-slate-400"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Dërguar për:</label>
                    <div class="relative">
                        <select name="target_role" id="targetRole" 
                            class="w-full appearance-none bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer">
                            <option value="all">Të gjithë</option>
                            <option value="student">Nxënësit</option>
                            <option value="parent">Prindërit</option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Data e skadimit</label>
                    <input type="date" name="expires_at" 
                        class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                </div>
            </div>

            <div id="classSelectWrapper" class="hidden space-y-1.5 animate-in slide-in-from-top-2 duration-200">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Zgjidh Klasën specifike</label>
                <div class="relative">
                    <select name="class_id" 
                        class="w-full appearance-none bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer">
                        <?php
                        $clsStmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? AND status = 'active'");
                        $clsStmt->execute([$schoolId]);
                        foreach ($clsStmt->fetchAll(PDO::FETCH_ASSOC) as $c):
                        ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button type="button" id="closeAnnouncementModal" 
                    class="flex-1 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">
                    Anulo
                </button>
                <button type="submit" 
                    class="flex-1 px-4 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-500/20 transition-all active:scale-[0.98]">
                    Publiko Njoftimin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Scripti mbetet funksionalisht i njëjtë
    const modal = document.getElementById('announcementModal');
    const openBtn = document.getElementById('openAnnouncementModal');
    const closeBtn = document.getElementById('closeAnnouncementModal');
    const targetSelect = document.getElementById('targetRole');
    const classWrapper = document.getElementById('classSelectWrapper');

    if(openBtn) openBtn.onclick = () => modal.classList.remove('hidden');
    if(closeBtn) closeBtn.onclick = () => modal.classList.add('hidden');

    targetSelect.onchange = (e) => {
        classWrapper.classList.toggle('hidden', e.target.value === 'all');
    };
</script>

<?php
if (!$isAjax) {
    $content = ob_get_clean();
    require_once __DIR__ . '/../index.php';
}
?>