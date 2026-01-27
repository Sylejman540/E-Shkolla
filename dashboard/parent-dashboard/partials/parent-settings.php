<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

/* =========================
   AUTH
========================= */
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'parent') {
    header('Location: /login.php');
    exit;
}

$userId = (int)$user['id'];

/* =========================
   AJAX HANDLERS
========================= */
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    /* ---- UPDATE NAME ---- */
    if ($action === 'update_profile') {
        $name = trim($data['value'] ?? '');

        if ($name === '') {
            echo json_encode(['status'=>'error','message'=>'Emri është i detyrueshëm.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$name, $userId]);

        $_SESSION['user']['name'] = $name;

        echo json_encode(['status'=>'success']);
        exit;
    }

    /* ---- UPDATE PASSWORD ---- */
    if ($action === 'update_password') {
        $current = $data['current_password'] ?? '';
        $new     = $data['new_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            echo json_encode(['status'=>'error','message'=>'Fjalëkalimi aktual është i pasaktë.']);
            exit;
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$newHash, $userId]);

        echo json_encode(['status'=>'success']);
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Veprim i panjohur.']);
    exit;
}

/* =========================
   FETCH VIEW DATA
========================= */
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        u.email,
        t.id AS teacher_id,
        s.subject_name
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    LEFT JOIN teacher_class tc ON t.id = tc.teacher_id
    LEFT JOIN subjects s ON tc.subject_id = s.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die('Përdoruesi nuk u gjet.');
}

$userData = $rows[0];
$subjects = array_filter(array_unique(array_column($rows, 'subject_name')));

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Cilësimet e Llogarisë
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Menaxhoni të dhënat tuaja si mësues dhe sigurinë e llogarisë.
        </p>
    </div>

    <div class="space-y-6">

        <!-- PROFILE -->
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
                Profili i Mësuesit
            </h3>

            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Emri i plotë
                    </label>

                    <div contenteditable
                         class="editable-name block w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-transparent focus:border-indigo-500 focus:bg-white dark:focus:bg-gray-800 outline-none transition-all text-slate-700 dark:text-slate-200"
                         data-original="<?= htmlspecialchars($userData['name']) ?>">
                        <?= htmlspecialchars($userData['name']) ?>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        Email
                    </label>
                    <div class="block w-full px-4 py-3 rounded-xl bg-slate-100/50 dark:bg-white/5 text-slate-500 dark:text-slate-400">
                        <?= htmlspecialchars($userData['email']) ?>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400 italic">
                    Kliko mbi emër për ta ndryshuar. Ruhet automatikisht.
                </p>
            </div>
        </div>

        <!-- INSTITUTION -->
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
                Të dhënat e Institucionit
            </h3>

            <div class="space-y-4">
                <div class="flex justify-between py-2 border-b border-slate-50 dark:border-white/5">
                    <span class="text-sm text-slate-500">ID Profesionale:</span>
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">
                        ID-<?= $userData['teacher_id'] ?? 'N/A' ?>
                    </span>
                </div>

                <div class="space-y-2">
                    <span class="text-sm text-slate-500">Lëndët tuaja:</span>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($subjects): foreach ($subjects as $s): ?>
                            <span class="bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 px-3 py-1 rounded-lg text-xs font-bold">
                                <?= htmlspecialchars($s) ?>
                            </span>
                        <?php endforeach; else: ?>
                            <span class="text-xs text-slate-400">Asnjë lëndë e caktuar.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECURITY -->
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">
                Siguria
            </h3>

            <div class="flex justify-between items-center p-4 rounded-2xl bg-slate-50 dark:bg-white/5">
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    Fjalëkalimi
                </p>
                <button onclick="toggleModal(true)"
                        class="px-4 py-2 text-xs font-bold bg-white dark:bg-gray-800 border border-slate-200 dark:border-white/10 rounded-lg">
                    NDRYSHO
                </button>
            </div>
        </div>

    </div>
</div>

<div id="passwordModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60" onclick="toggleModal(false)"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-2xl">
        <h3 class="text-xl font-bold mb-6">Ndrysho Fjalëkalimin</h3>
        <form id="passwordForm" class="space-y-4">
            <input type="password" name="current_password" placeholder="Fjalëkalimi Aktual" required class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5">
            <input type="password" name="new_password" placeholder="Fjalëkalimi i Ri" required class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5">
            <input type="password" name="confirm_password" placeholder="Konfirmo Fjalëkalimin" required class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5">

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="toggleModal(false)" class="flex-1 px-4 py-3 bg-slate-100 rounded-xl">
                    Anulo
                </button>
                <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl">
                    Përditëso
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModal(show) {
    document.getElementById('passwordModal').classList.toggle('hidden', !show);
}

document.querySelector('.editable-name').addEventListener('focusout', async (e) => {
    const el = e.target;
    const newValue = el.innerText.trim();
    if (!newValue || newValue === el.dataset.original) return;

    const res = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest' },
        body: JSON.stringify({ action:'update_profile', value:newValue })
    }).then(r => r.json());

    if (res.status === 'success') {
        el.dataset.original = newValue;
    } else {
        el.innerText = el.dataset.original;
        alert(res.message);
    }
});

document.getElementById('passwordForm').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    if (data.new_password !== data.confirm_password) return alert('Fjalëkalimet nuk përputhen');

    const res = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest' },
        body: JSON.stringify({ action:'update_password', ...data })
    }).then(r => r.json());

    if (res.status === 'success') {
        toggleModal(false);
        e.target.reset();
    } else alert(res.message);
};
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
