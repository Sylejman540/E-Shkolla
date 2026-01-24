<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$userId = $_SESSION['user']['id'] ?? null;

// --- 1. HANDLE AJAX REQUESTS (Update Logic) ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    // Action: Update Name
    if ($action === 'update_profile') {
        $field = $data['field'];
        $value = trim($data['value']);

        if ($field === 'name' && !empty($value)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            if ($stmt->execute([$value, $userId])) {
                $_SESSION['user']['name'] = $value; // Sync session
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gabim gjatë përditësimit.']);
            }
        }
        exit;
    }

    // Action: Update Password
    if ($action === 'update_password') {
        $current = $data['current_password'];
        $new = $data['new_password'];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userHash = $stmt->fetchColumn();

        if (!password_verify($current, $userHash)) {
            echo json_encode(['status' => 'error', 'message' => 'Fjalëkalimi aktual është i pasaktë.']);
            exit;
        }

        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$newHash, $userId])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dështoi ndryshimi i fjalëkalimit.']);
        }
        exit;
    }
}

// --- 2. FETCH DATA FOR PAGE LOAD ---
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Cilësimet e Llogarisë</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menaxhoni emrin tuaj publik dhe sigurinë e llogarisë.</p>
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Profili</h3>
            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Emri i plotë</label>
                <div contenteditable class="editable-name block w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-white/5 border border-transparent focus:border-indigo-500 focus:bg-white dark:focus:bg-gray-800 outline-none transition-all text-slate-700 dark:text-slate-200"
                     data-original="<?= htmlspecialchars($user['name']) ?>">
                    <?= htmlspecialchars($user['name']) ?>
                </div>
                <p class="text-[11px] text-slate-400 italic">Kliko mbi emër për ta ndryshuar. Ruhet automatikisht.</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-sm p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Siguria</h3>
            <div class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-100 dark:border-white/5">
                <div class="flex items-center gap-4">
                    <div class="p-2.5 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Fjalëkalimi</p>
                        <p class="text-xs text-slate-500">I mbrojtur me kriptim AES-256</p>
                    </div>
                </div>
                <button onclick="toggleModal(true)" class="px-4 py-2 text-xs font-bold bg-white dark:bg-gray-800 border border-slate-200 dark:border-white/10 rounded-lg hover:bg-slate-50 transition-all shadow-sm active:scale-95">
                    NDRYSHO
                </button>
            </div>
        </div>
    </div>
</div>

<div id="passwordModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal(false)"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-2xl border border-slate-200 dark:border-white/10">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Ndrysho Fjalëkalimin</h3>
        
        <form id="passwordForm" class="space-y-4">
            <div>
                <label class="text-xs font-bold text-slate-400 uppercase">Fjalëkalimi Aktual</label>
                <input type="password" name="current_password" required class="mt-1 w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5 border-none focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-400 uppercase">Fjalëkalimi i Ri</label>
                <input type="password" name="new_password" required class="mt-1 w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5 border-none focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-400 uppercase">Konfirmo Fjalëkalimin</label>
                <input type="password" name="confirm_password" required class="mt-1 w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-white/5 border-none focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
            </div>
            
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="toggleModal(false)" class="flex-1 px-4 py-3 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl dark:bg-gray-800 dark:text-slate-300">Anulo</button>
                <button type="submit" class="flex-1 px-4 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-500 transition shadow-lg shadow-indigo-500/30">Përditëso</button>
            </div>
        </form>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

<script>
// --- UI HELPERS ---
function toggleModal(show) {
    document.getElementById('passwordModal').classList.toggle('hidden', !show);
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `${type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'} text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-medium transform transition-all duration-300 translate-y-10 opacity-0`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// --- LOGIC: UPDATE NAME ---
document.querySelector('.editable-name').addEventListener('focusout', async (e) => {
    const el = e.target;
    const oldValue = el.getAttribute('data-original');
    const newValue = el.innerText.trim();

    if (newValue === oldValue || newValue === "") return;

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'update_profile', field: 'name', value: newValue })
        });
        const res = await response.json();
        if (res.status === 'success') {
            el.setAttribute('data-original', newValue);
            showToast('Emri u ruajt!');
        } else { throw new Error(res.message); }
    } catch (err) {
        el.innerText = oldValue;
        showToast(err.message, 'error');
    }
});

// --- LOGIC: UPDATE PASSWORD ---
document.getElementById('passwordForm').onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));

    if (data.new_password !== data.confirm_password) {
        return showToast('Fjalëkalimet e reja nuk përputhen!', 'error');
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'update_password', ...data })
        });
        const res = await response.json();
        if (res.status === 'success') {
            showToast('Fjalëkalimi u ndryshua!');
            toggleModal(false);
            e.target.reset();
        } else { showToast(res.message, 'error'); }
    } catch (err) {
        showToast('Gabim gjatë lidhjes me serverin.', 'error');
    }
};
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>