<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$school_id = $_SESSION['user']['school_id'] ?? 0;
ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Importo Orarin</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ngarkoni skedarin. Lënda do të caktohet automatikisht bazuar te mësuesi.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="toggleHelp()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.444 1.103m.499 3.103a1 1 0 110-2 1 1 0 010 2zV12 17h.01"></path></svg>
                ID Reference
            </button>
            <button onclick="downloadScheduleTemplate()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Shkarko Modelin
            </button>
            <a href="/E-Shkolla/schedule" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 dark:bg-gray-800 dark:text-slate-300 dark:border-white/10 transition-all">Anulo</a>
        </div>
    </div>

    <div id="helpBox" class="hidden mb-6 p-6 bg-slate-800 text-white rounded-2xl shadow-xl border border-slate-700 animate-in fade-in duration-300">
        <div class="flex items-center justify-between mb-6">
            <h4 class="font-bold text-emerald-400 text-sm uppercase tracking-widest">Kërkimi i ID-ve</h4>
            <div class="flex bg-slate-700 p-1 rounded-lg border border-slate-600">
                <button onclick="switchTab('classes')" class="tab-btn px-4 py-1.5 text-xs font-bold rounded-md bg-slate-600">Klasat</button>
                <button onclick="switchTab('teachers')" class="tab-btn px-4 py-1.5 text-xs font-bold rounded-md">Mësuesit</button>
            </div>
        </div>

        <div id="tab-classes" class="tab-content grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php
            $stmt = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ? ORDER BY grade ASC");
            $stmt->execute([$school_id]);
            while($row = $stmt->fetch()): ?>
                <div class="flex justify-between items-center bg-slate-700/50 p-2.5 rounded-lg border border-slate-600 group">
                    <span class="text-[11px] truncate text-slate-200"><?= htmlspecialchars($row['grade']) ?></span>
                    <span class="text-emerald-400 font-mono text-xs font-bold cursor-pointer hover:text-white" onclick="copyId('<?= $row['id'] ?>', this)">ID:<?= $row['id'] ?></span>
                </div>
            <?php endwhile; ?>
        </div>

        <div id="tab-teachers" class="tab-content hidden grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE school_id = ? AND role = 'teacher' ORDER BY name ASC");
            $stmt->execute([$school_id]);
            while($row = $stmt->fetch()): ?>
                <div class="flex justify-between items-center bg-slate-700/50 p-2.5 rounded-lg border border-slate-600 group">
                    <span class="text-[11px] truncate text-slate-200"><?= htmlspecialchars($row['name']) ?></span>
                    <span class="text-emerald-400 font-mono text-xs font-bold cursor-pointer hover:text-white" onclick="copyId('<?= $row['id'] ?>', this)">ID:<?= $row['id'] ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div id="uploadContainer" class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10 p-12 text-center">
        <div class="max-w-md mx-auto">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ngarkoni CSV</h3>
            <p class="text-sm text-slate-500 mb-6 italic">Kolonat: class_id, day, period_number, teacher_id</p>
            <input type="file" id="csvFileInput" class="hidden" accept=".csv">
            <button onclick="document.getElementById('csvFileInput').click()" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold shadow-lg">Përzgjidh Skedarin</button>
        </div>
    </div>

    <div id="previewContainer" class="hidden space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 overflow-hidden">
            <table class="w-full text-left border-collapse" id="previewTable">
                <thead id="previewHead" class="bg-slate-50 dark:bg-white/5"></thead>
                <tbody id="previewBody" class="divide-y divide-slate-200 dark:divide-white/5"></tbody>
            </table>
        </div>
        <div class="flex items-center justify-end gap-3 p-4 bg-emerald-50 dark:bg-emerald-500/5 rounded-2xl border border-emerald-100">
            <button onclick="location.reload()" class="px-6 py-2.5 text-sm font-semibold text-slate-600">Pastro</button>
            <button id="submitImportBtn" class="bg-emerald-600 text-white px-8 py-2.5 rounded-xl font-bold">Importo</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script>
let scheduleData = [];

function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('bg-slate-600'));
    event.currentTarget.classList.add('bg-slate-600');
    document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
    document.getElementById('tab-' + tabName).classList.remove('hidden');
}

function copyId(id, el) {
    navigator.clipboard.writeText(id);
    const original = el.innerText;
    el.innerText = 'Copied!';
    setTimeout(() => el.innerText = original, 1000);
}

function toggleHelp() { document.getElementById('helpBox').classList.toggle('hidden'); }

document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    Papa.parse(file, {
        header: true,
        skipEmptyLines: 'greedy',
        complete: function(results) {
            scheduleData = results.data
                .map(row => normalizeRow(row))
                .filter(row => row.class_id && row.day && row.period_number && row.teacher_id);
            showPreview(scheduleData);
        }
    });
});

function normalizeRow(row) {
    const clean = {};
    Object.keys(row).forEach(key => {
        clean[key.trim().toLowerCase().replace(/\s+/g, '_')] = String(row[key] ?? '').trim();
    });
    return {
        class_id: clean.class_id || '',
        day: clean.day || '',
        period_number: clean.period_number || '',
        teacher_id: clean.teacher_id || ''
    };
}

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    const headers = Object.keys(data[0]);
    document.getElementById('previewHead').innerHTML = `<tr>${headers.map(h => `<th class="px-6 py-4 text-xs font-black uppercase text-slate-500">${h}</th>`).join('')}</tr>`;
    document.getElementById('previewBody').innerHTML = data.map(row => `<tr>${headers.map(h => `<td class="px-6 py-4 text-sm text-slate-600">${row[h]}</td>`).join('')}</tr>`).join('');
}

document.getElementById('submitImportBtn').onclick = async function () {
    const btn = this;
    btn.disabled = true;
    try {
        const response = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/process-import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(scheduleData)
        });
        const result = await response.json();
        alert(`Importimi u krye!\nU shtuan: ${result.imported}`);
        window.location.href = '/E-Shkolla/schedule';
    } catch (e) {
        alert('Gabim: ' + e.message);
        btn.disabled = false;
    }
};

function downloadScheduleTemplate() {
    const csv = "class_id,day,period_number,teacher_id\n12,Hene,1,101";
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "template_orari.csv";
    link.click();
}
</script>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>