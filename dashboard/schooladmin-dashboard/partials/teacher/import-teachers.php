<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

// Marrim klasat për t'i treguar te "Ndihmë"
$school_id = $_SESSION['user']['school_id'] ?? 0;
$stmtClasses = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
$stmtClasses->execute([$school_id]);
$availableClasses = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Importo Mësuesit</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ngarkoni një skedar CSV për të shtuar mësuesit në masë.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="toggleHelp()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.444 1.103m.499 3.103a1 1 0 110-2 1 1 0 010 2zV12 17h.01"></path></svg>
                Ndihmë
            </button>
            <button onclick="downloadCSVTemplate()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Shkarko Modelin
            </button>
            <a href="/E-Shkolla/teachers" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 dark:bg-gray-800 dark:text-slate-300 dark:border-white/10 transition-all">
                Anulo
            </a>
        </div>
    </div>

    <div id="helpBox" class="hidden mb-6 p-6 bg-slate-800 text-white rounded-2xl shadow-xl border border-slate-700">
        <h4 class="font-bold mb-4 text-emerald-400 text-sm">ID-të e Klasave për Shkollën Tuaj:</h4>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
            <?php foreach($availableClasses as $class): ?>
                <div class="bg-slate-700 p-2 rounded border border-slate-600 text-[11px]">
                    <span class="text-emerald-400 font-bold">ID: <?= $class['id'] ?></span> 
                    <span class="text-slate-300">- <?= htmlspecialchars($class['grade']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="uploadContainer" class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10 p-12 text-center shadow-sm transition-all hover:border-indigo-400">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zgjidhni skedarin CSV</h3>
            <p class="text-sm text-slate-500 mb-6 italic">Detektim automatik për ndarësin ( , ose ; )</p>
            
            <input type="file" id="csvFileInput" class="hidden" accept=".csv">
            <button onclick="document.getElementById('csvFileInput').click()" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">
                Përzgjidh Skedarin
            </button>
        </div>
    </div>

    <div id="previewContainer" class="hidden space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 flex justify-between items-center bg-slate-50/50 dark:bg-white/5">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">Paraqitja e të dhënave</h3>
                <span id="rowCount" class="text-xs font-bold px-2 py-1 bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-lg">0 Rreshta</span>
            </div>
            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left border-collapse" id="previewTable">
                    <thead id="previewHead"></thead>
                    <tbody id="previewBody" class="divide-y divide-slate-200 dark:divide-white/5"></tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between p-4 bg-emerald-50 dark:bg-emerald-500/5 rounded-2xl border border-emerald-100 dark:border-emerald-500/20">
            <p class="text-sm text-emerald-700 dark:text-emerald-300 px-2 font-medium">
                Të dhënat u lexuan. Kontrolloni kolonat para se të klikoni importin.
            </p>
            <div class="flex gap-3">
                <button onclick="location.reload()" class="px-6 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-800">Pastro</button>
                <button id="submitImportBtn" class="bg-emerald-600 text-white px-8 py-2.5 rounded-xl font-bold hover:bg-emerald-500 shadow-lg shadow-emerald-500/20 transition-all">
                    Konfirmo & Importo
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script>
let csvData = [];

/* ===============================
   CSV FILE READ
================================ */
document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: 'greedy',
        complete: function(results) {

            // Normalize + clean rows
            csvData = results.data
                .map(row => normalizeRow(row))
                .filter(row => row.name && row.email && row.class_id);

            if (!csvData.length) {
                alert('Skedari CSV nuk përmban të dhëna valide.');
                return;
            }

            showPreview(csvData);
        }
    });
});

/* ===============================
   NORMALIZE CSV HEADERS
================================ */
function normalizeRow(row) {
    const clean = {};
    Object.keys(row).forEach(key => {
        const k = key.trim().toLowerCase();
        clean[k] = String(row[key] ?? '').trim();
    });

    return {
        name: clean.name || '',
        email: clean.email || '',
        password: clean.password || '',
        phone: clean.phone || '',
        class_id: parseInt(clean.class_id || clean.class || 0),
        gender: clean.gender || 'other',
        subject: clean.subject || 'E pacaktuar',
        status: clean.status || 'active',
        description: clean.description || '',
        profile_photo: 'assets/img/default-avatar.png'
    };
}

/* ===============================
   PREVIEW TABLE
================================ */
function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    document.getElementById('rowCount').innerText = `${data.length} Mësues`;

    const thead = document.getElementById('previewHead');
    const tbody = document.getElementById('previewBody');

    const headers = Object.keys(data[0]);

    thead.innerHTML = `<tr>
        ${headers.map(h => `<th class="px-4 py-3 text-xs font-bold uppercase">${h}</th>`).join('')}
    </tr>`;

    tbody.innerHTML = data.map(row => `
        <tr>
            ${headers.map(h => {
                let val = row[h];
                if (h === 'password') val = '••••••';
                return `<td class="px-4 py-2 text-sm truncate max-w-[140px]">${val}</td>`;
            }).join('')}
        </tr>
    `).join('');
}

/* ===============================
   SUBMIT IMPORT
================================ */
document.getElementById('submitImportBtn').onclick = async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = 'Duke u procesuar...';

    try {
        if (!Array.isArray(csvData) || !csvData.length) {
            throw new Error('Nuk ka të dhëna për import.');
        }

        const response = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/process-import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(csvData)
        });

        if (!response.ok) {
            throw new Error('HTTP Error ' + response.status);
        }

        const text = await response.text();
        let result;

        try {
            result = JSON.parse(text);
        } catch {
            console.error('SERVER RESPONSE:', text);
            throw new Error('Serveri nuk ktheu JSON valid.');
        }

if (result.status === 'success') {

    let message = `Importimi përfundoi me sukses.\n\n`;

    if (result.imported > 0) {
        message += `✅ Të rinj të shtuar: ${result.imported}\n`;
    }

    if (result.skipped > 0) {
        message += `ℹ️ Tashmë ekzistonin në sistem: ${result.skipped}\n`;
    }

    alert(message);
    window.location.href = '/E-Shkolla/teachers';
}


    } catch (e) {
        console.error(e);
        alert(e.message);
        btn.disabled = false;
        btn.innerHTML = 'Konfirmo & Importo';
    }
};

/* ===============================
   HELPERS
================================ */
function toggleHelp() {
    document.getElementById('helpBox').classList.toggle('hidden');
}

function downloadCSVTemplate() {
    const headers = ["name","email","password","phone","class_id","gender","subject","status","description"];
    const sample = ["Arta Krasniqi","arta@school.com","Pass123!","049111222","1","female","Matematikë","active","Mësuese"];

    const csv = [headers.join(","), sample.join(",")].join("\n");
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "modeli_mesuesve.csv";
    link.click();
}
</script>


<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>