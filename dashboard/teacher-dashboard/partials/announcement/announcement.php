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
?>

<div class="max-w-6xl mx-auto space-y-8 pb-16 animate-in fade-in duration-500">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-8">
        <div>
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-xs font-medium text-slate-400">
                    <li>Dashboard</li>
                    <li><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg></li>
                    <li class="text-indigo-600 dark:text-indigo-400">Komunikimi</li>
                </ol>
            </nav>
            <h1 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight text-balance">Njoftimet e Shkollës</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.6)]"></span>
                Qëndroni të informuar me zhvillimet e fundit.
            </p>
        </div>

<?php if ($role === 'teacher'): ?>
    <button type="button" id="openAnnouncementModal" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
        Njoftim i ri
    </button>
<?php endif; ?>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="bg-slate-50 dark:bg-slate-900/50 border-2 border-dashed border-slate-200 dark:border-slate-800 p-16 rounded-[2.5rem] text-center">
            <div class="text-4xl mb-4 text-slate-300">📢</div>
            <p class="text-slate-500 dark:text-slate-400 font-semibold text-lg">Nuk ka njoftime aktive.</p>
            <p class="text-slate-400 dark:text-slate-500 text-sm">Gjithçka duket e qetë për momentin.</p>
        </div>
    <?php else: ?>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($announcements as $ann): ?>
        <div class="group bg-white dark:bg-slate-900 rounded-[2rem] p-7 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-indigo-200 dark:hover:border-indigo-900/50 transition-all duration-300">
            
            <div class="flex justify-between items-start mb-6">
                <span class="inline-flex items-center px-3 py-1 rounded-xl text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                    <?= htmlspecialchars($ann['target_role']) ?>
                    <?= $ann['class_name'] ? " • {$ann['class_name']}" : '' ?>
                </span>
                <span class="text-[11px] font-bold text-slate-400 bg-slate-50 dark:bg-slate-800/50 px-2 py-1 rounded-lg">
                    <?= date('d.m.Y', strtotime($ann['created_at'])) ?>
                </span>
            </div>

            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3 leading-tight group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                <?= htmlspecialchars($ann['title']) ?>
            </h3>

            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6 line-clamp-4">
                <?= nl2br(htmlspecialchars($ann['content'])) ?>
            </p>

            <div class="flex items-center justify-between pt-5 border-t border-slate-50 dark:border-slate-800/50">
                <?php if (!empty($ann['expires_at'])): ?>
                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-500/80">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        E vlefshme deri: <?= date('d/m/Y', strtotime($ann['expires_at'])) ?>
                    </div>
                <?php else: ?>
                    <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-500/80 uppercase tracking-tighter">Përhershëm</div>
                <?php endif; ?>

                <?php if ($role === 'teacher'): ?>
                    <a href="/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/delete-announcement.php?id=<?= (int)$ann['id'] ?>"
                       onclick="return confirm('A jeni të sigurt që dëshironi ta fshini këtë njoftim?')"
                       class="text-rose-500 hover:text-rose-600 text-[11px] font-black uppercase tracking-tighter hover:underline">
                        Fshij
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-center text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] bg-slate-50 dark:bg-slate-900/30 p-8 rounded-[2rem] border border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-6">
            <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Server Status: Online</span>
            <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Encrypted Session</span>
        </div>
        <p class="mt-4 md:mt-0">E-Shkolla Management System &copy; <?= date('Y') ?></p>
    </div>
</div>

<?php if ($role === 'teacher'): ?>
<div id="announcementModal" class="hidden fixed inset-0 z-[100] bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-xl w-full max-w-md p-6 shadow-2xl">
        <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white border-b pb-2">Krijo Njoftim</h2>

        <form action="/E-Shkolla/dashboard/teacher-dashboard/partials/announcement/save-announcement.php" method="POST" class="space-y-4">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Titulli</label>
                <input type="text" name="title" required placeholder="Shkruaj titullin..."
                       class="w-full border border-slate-300 dark:border-slate-700 rounded-lg p-2.5 text-sm dark:bg-slate-800 dark:text-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mesazhi</label>
                <textarea name="message" rows="4" required placeholder="Shkruaj njoftimin këtu..."
                          class="w-full border border-slate-300 dark:border-slate-700 rounded-lg p-2.5 text-sm dark:bg-slate-800 dark:text-white"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Dërguar për:</label>
                <select name="target_role" id="targetRole" class="w-full border border-slate-300 dark:border-slate-700 rounded-lg p-2.5 text-sm dark:bg-slate-800 dark:text-white">
                    <option value="all">Të gjithë</option>
                    <option value="student">Nxënësit</option>
                    <option value="parent">Prindërit</option>
                </select>
            </div>

            <div id="classSelectWrapper" class="hidden">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Zgjidh Klasën</label>
                <select name="class_id" class="w-full border border-slate-300 dark:border-slate-700 rounded-lg p-2.5 text-sm dark:bg-slate-800 dark:text-white">
                    <?php
                    $clsStmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? AND status = 'active'");
                    $clsStmt->execute([$schoolId]);
                    foreach ($clsStmt->fetchAll(PDO::FETCH_ASSOC) as $c):
                    ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['grade']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Data e Skadimit (Opsionale)</label>
                <input type="date" name="expires_at" class="w-full border border-slate-300 dark:border-slate-700 rounded-lg p-2.5 text-sm dark:bg-slate-800 dark:text-white">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" id="closeAnnouncementModal" class="px-4 py-2 text-sm font-medium text-slate-600 border rounded-lg hover:bg-slate-50 transition">
                    Anulo
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                    Ruaj Njoftimin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
        const modal = document.getElementById('announcementModal');
    const openBtn = document.getElementById('openAnnouncementModal');
    const closeBtn = document.getElementById('closeAnnouncementModal');
    const targetSelect = document.getElementById('targetRole');
    const classWrapper = document.getElementById('classSelectWrapper');

    if(openBtn) openBtn.onclick = () => modal.classList.remove('hidden');
    if(closeBtn) closeBtn.onclick = () => modal.classList.add('hidden');

    targetSelect.onchange = (e) => {
        if(e.target.value === 'all') {
            classWrapper.classList.add('hidden');
        } else {
            classWrapper.classList.remove('hidden');
        }
    };
</script>
<?php endif; ?>

<?php
if (!$isAjax) {
    $content = ob_get_clean();
    require_once __DIR__ . '/../index.php';
}
?>