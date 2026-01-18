<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$role = $user['role'] ?? 'teacher';

// Fetch dynamic teacher data if applicable
$subjects = [];
if ($role === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT s.subject_name 
        FROM teacher_class tc
        JOIN subjects s ON tc.subject_id = s.id
        WHERE tc.teacher_id = (SELECT id FROM teachers WHERE user_id = ?)
        GROUP BY s.id
    ");
    $stmt->execute([$userId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Cilësimet e Llogarisë</h1>
        <p class="text-slate-500">Menaxhoni të dhënat tuaja personale dhe preferencat e aplikacionit.</p>
    </div>

    <form action="update-settings.php" method="POST" enctype="multipart/form-data" class="space-y-8">
        
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-bold text-slate-800">Informacioni Personal</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2 flex items-center gap-6 mb-4">
                    <div class="h-20 w-20 rounded-2xl bg-slate-100 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden">
                        <?php if(!empty($user['profile_photo'])): ?>
                            <img src="/uploads/profiles/<?= $user['profile_photo'] ?>" class="h-full w-full object-cover">
                        <?php else: ?>
                            <svg class="h-10 w-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Foto Profili</label>
                        <input type="file" name="profile_photo" class="mt-2 text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Emri i Plotë</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                           class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Adresa Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                           class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-bold text-slate-800">Siguria</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Fjalëkalimi i Ri</label>
                    <input type="password" name="new_password" placeholder="Lëreni bosh nëse nuk ndryshon"
                           class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Konfirmo Fjalëkalimin</label>
                    <input type="password" name="confirm_password" 
                           class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="font-bold text-slate-800">Të dhënat e institucionit</h2>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <?php if ($role === 'teacher'): ?>
                    <div class="flex justify-between py-2 border-b border-slate-50">
                        <span class="text-slate-500">ID Profesionale:</span>
                        <span class="font-bold text-slate-700"><?= $user['id'] ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-50">
                        <span class="text-slate-500">Shkolla:</span>
                        <span class="font-bold text-slate-700"><?= htmlspecialchars($user['school_name'] ?? 'E-Shkolla Qendrore') ?></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-slate-500">Lëndët:</span>
                        <div class="flex gap-2">
                            <?php foreach($subjects as $s): ?>
                                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold"><?= htmlspecialchars($s) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($role === 'school_admin'): ?>
                    <div class="p-4 bg-blue-50 rounded-2xl flex items-center justify-between">
                        <span class="text-blue-800 font-medium">Panel i Menaxhimit</span>
                        <div class="flex gap-3">
                            <a href="/manage-teachers.php" class="bg-white text-blue-700 px-4 py-2 rounded-xl text-xs font-bold border border-blue-100 hover:bg-blue-100">Mësuesit</a>
                            <a href="/manage-classes.php" class="bg-white text-blue-700 px-4 py-2 rounded-xl text-xs font-bold border border-blue-100 hover:bg-blue-100">Klasat</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4 pt-4 border-t border-slate-200">
            <button type="reset" class="px-6 py-3 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Anulo</button>
            <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">Ruaj Ndryshimet</button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>