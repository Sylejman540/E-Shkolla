<?php
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['id'] ?? null;

if (!$schoolId) {
    die('Aksesi i mohuar.');
}

// 1. Logic: Save New Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notice'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $audience = $_POST['target_role'] ?? 'all';
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (school_id, class_id, teacher_id, title, message, target_audience, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$schoolId, $classId, $teacherId, $title, $message, $audience, $expires]);
        $success = "Njoftimi u publikua me sukses.";
    }
}

// 2. Fetch Active Announcements for this Class
$stmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE class_id = ? 
    AND (expires_at IS NULL OR expires_at >= CURDATE()) 
    ORDER BY created_at DESC
");
$stmt->execute([$classId]);
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="p-6 lg:p-10 max-w-7xl mx-auto space-y-10">
    
    <div class="flex justify-between items-center border-b border-slate-200 pb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Komunikimi</h1>
            <p class="text-slate-500 text-sm mt-1">Menaxhoni njoftimet aktive për këtë klasë.</p>
        </div>
        <button onclick="document.getElementById('noticeModal').classList.remove('hidden')" 
                class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-100 hover:bg-indigo-500 transition-all active:scale-95 flex items-center gap-2">
            <span>➕</span> Njoftim i Ri
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php if (empty($notices)): ?>
            <div class="col-span-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl p-20 text-center">
                <p class="text-slate-400 font-bold italic">Nuk ka njoftime aktive për momentin.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notices as $n): ?>
            <div class="group bg-white p-7 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between relative transition-all hover:border-indigo-300">
                <?php if($n['expires_at']): ?>
                    <div class="absolute top-3 right-3 px-2 py-1 bg-rose-50 rounded text-[9px] text-rose-500 font-black uppercase tracking-tighter">
                        Skadon: <?= date('d/m', strtotime($n['expires_at'])) ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <div class="flex justify-between items-center mb-5">
                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest rounded bg-indigo-50 text-indigo-600">
                            <?= $n['target_audience'] === 'all' ? 'Të gjithë' : ($n['target_audience'] === 'students' ? 'Nxënësit' : 'Prindërit') ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-bold"><?= date('d/m/Y', strtotime($n['created_at'])) ?></span>
                    </div>
                    <h3 class="font-black text-slate-900 mb-3 text-lg leading-tight tracking-tight"><?= htmlspecialchars($n['title']) ?></h3>
                    <p class="text-sm text-slate-600 leading-relaxed line-clamp-4"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                </div>

                <div class="mt-8 pt-5 border-t border-slate-50 flex justify-between items-center">
                    <button onclick="deleteNotice(<?= $n['id'] ?>)" class="text-rose-500 text-[10px] font-black uppercase tracking-widest hover:text-rose-700 transition">Fshij</button>
                    <span class="text-[9px] font-bold text-slate-300">ID: #<?= $n['id'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="noticeModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-3xl p-8 border border-slate-200 shadow-2xl scale-in-center">
        <h2 class="text-2xl font-black mb-6 text-slate-900 tracking-tight">Krijo Njoftim</h2>
        <form method="POST" class="space-y-5">
            
            <div>
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Titulli</label>
                <input type="text" name="title" required placeholder="p.sh. Mbledhje me prindër" 
                       class="w-full p-4 mt-1 rounded-xl bg-slate-50 border-none focus:ring-4 focus:ring-indigo-500/10 transition text-sm font-semibold">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Kush e sheh?</label>
                    <select name="target_role" class="w-full p-4 mt-1 rounded-xl bg-slate-50 border-none text-sm font-bold appearance-none">
                        <option value="all">Të gjithë</option>
                        <option value="students">Nxënësit</option>
                        <option value="parents">Prindërit</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Skadon më</label>
                    <input type="date" name="expires_at" class="w-full p-4 mt-1 rounded-xl bg-slate-50 border-none text-sm font-bold">
                </div>
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-1">Mesazhi</label>
                <textarea name="message" rows="5" required placeholder="Shkruani detajet e njoftimit..." 
                          class="w-full p-4 mt-1 rounded-xl bg-slate-50 border-none focus:ring-4 focus:ring-indigo-500/10 transition text-sm font-medium leading-relaxed"></textarea>
            </div>
            
            <div class="flex gap-3 pt-6">
                <button type="button" onclick="document.getElementById('noticeModal').classList.add('hidden')" 
                        class="flex-1 p-4 bg-slate-100 rounded-xl font-bold text-slate-600 text-xs uppercase tracking-widest hover:bg-slate-200 transition">Anulo</button>
                <button type="submit" name="send_notice" 
                        class="flex-1 p-4 bg-indigo-600 text-white rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition">Dërgo</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteNotice(id) {
    if(confirm('A jeni të sigurt? Njoftimi do të fshihet.')) {
        // Add your delete logic path here
        window.location.href = `delete-notice.php?id=${id}&class_id=<?= $classId ?>`;
    }
}
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../index.php'; 
?>