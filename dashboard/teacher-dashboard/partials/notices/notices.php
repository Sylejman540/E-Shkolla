<?php
require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$classId = $_GET['class_id'] ?? null;
$schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = $_SESSION['user']['teacher_id'] ?? null;

if (!$schoolId || !$teacherId) {
    die('Aksesi i mohuar.');
}

// 1. Ruajtja e Njoftimit të Ri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notice'])) {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (school_id, class_id, teacher_id, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$schoolId, $classId, $teacherId, $title, $message]);
        $success = "Njoftimi u dërgua me sukses!";
    }
}

// 2. Marrja e Njoftimeve të kaluara
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE class_id = ? ORDER BY created_at DESC");
$stmt->execute([$classId]);
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-900">Njoftimet e Klasës</h1>
        <p class="text-slate-500">Komuniko zyrtarisht me prindërit e klasës sate.</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl font-bold">
            ✅ <?= $success ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm sticky top-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4">Dërgo Njoftim</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Titulli</label>
                        <input type="text" name="title" required placeholder="Psh: Mbledhje..." 
                               class="w-full rounded-xl border-slate-200 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Mesazhi</label>
                        <textarea name="message" required rows="5" placeholder="Shkruani detajet e njoftimit..." 
                                  class="w-full rounded-xl border-slate-200 focus:ring-blue-500 text-sm"></textarea>
                    </div>
                    <button type="submit" name="send_notice" 
                            class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-100">
                        Publiko Njoftimin
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest px-2">Historia e Komunikimit</h3>
            
            <?php if (empty($notices)): ?>
                <div class="bg-slate-50 border border-dashed border-slate-300 rounded-3xl p-12 text-center">
                    <p class="text-slate-400 italic">Nuk keni dërguar ende asnjë njoftim.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notices as $n): ?>
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm hover:border-blue-200 transition">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-black text-slate-800 text-lg"><?= htmlspecialchars($n['title']) ?></h4>
                            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-lg">
                                <?= date('d/m/Y - H:i', strtotime($n['created_at'])) ?>
                            </span>
                        </div>
                        <p class="text-slate-600 text-sm leading-relaxed mb-4">
                            <?= nl2br(htmlspecialchars($n['message'])) ?>
                        </p>
                        <div class="pt-4 border-t border-slate-50 flex items-center gap-4">
                            <span class="text-xs font-bold text-blue-600 flex items-center gap-1">
                                <span>👁️</span> Të gjithë prindërit
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php'; 