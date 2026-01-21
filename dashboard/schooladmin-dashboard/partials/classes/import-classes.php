<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

$school_id = $_SESSION['user']['school_id'] ?? 0;

// Get Teachers to show IDs in the Help Box (for the CSV teacher_id column)
$stmtTeachers = $pdo->prepare("SELECT user_id, name FROM teachers WHERE school_id = ? AND status = 'active'");
$stmtTeachers->execute([$school_id]);
$availableTeachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

// Auto-generate current academic year for the help text
$currentMonth = date('n');
$currentYear = date('Y');
$autoYear = ($currentMonth >= 9) ? $currentYear . '/' . ($currentYear + 1) : ($currentYear - 1) . '/' . $currentYear;

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Importo Klasat</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Shtoni klasa të reja në masë përmes skedarit CSV.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="toggleHelp()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.444 1.103m.499 3.103a1 1 0 110-2 1 1 0 010 2zV12 17h.01"></path></svg>
                Ndihmë (ID e Mësuesve)
            </button>
            <button onclick="downloadClassTemplate()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Shkarko Modelin
            </button>
            <a href="/E-Shkolla/classes" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 dark:bg-gray-800 dark:text-slate-300 dark:border-white/10 transition-all">
                Anulo
            </a>
        </div>
    </div>

    <div id="helpBox" class="hidden mb-6 p-6 bg-slate-800 text-white rounded-2xl shadow-xl border border-slate-700">
        <h4 class="font-bold mb-4 text-emerald-400 text-sm">ID-të e Mësuesve Kujdestarë:</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <?php foreach($availableTeachers as $t): ?>
                <div class="bg-slate-700 p-2 rounded border border-slate-600 text-[11px]">
                    <span class="text-emerald-400 font-bold">ID: <?= $t['user_id'] ?></span> 
                    <span class="text-slate-300">- <?= htmlspecialchars($t['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-4 text-xs text-slate-400">Viti akademik aktual: <b><?= $autoYear ?></b>. Statusi mund të jetë 'active' ose 'inactive'.</p>
        <p class="mt-2 text-xs text-slate-500 italic">* Përdorni vetëm ID-të e listuara më sipër.</p>
    </div>

    <div id="uploadContainer" class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10 p-12 text-center shadow-sm transition-all hover:border-indigo-400">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zgjidhni skedarin CSV për Klasat</h3>
            <p class="text-sm text-slate-500 mb-6 italic">Academic Year, Base Grade dhe Parallel janë të domosdoshme.</p>
            
            <input type="file" id="csvFileInput" class="hidden" accept=".csv">
            <button onclick="document.getElementById('csvFileInput').click()" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">
                Përzgjidh Skedarin
            </button>
        </div>
    </div>

    <div id="previewContainer" class="hidden space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 flex justify-between items-center bg-slate-50/50 dark:bg-white/5">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">Preview e Klasave</h3>
                <span id="rowCount" class="text-xs font-bold px-2 py-1 bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-lg">0 Klasa</span>
            </div>
            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left border-collapse" id="previewTable">
                    <thead id="previewHead" class="bg-slate-50/80 dark:bg-white/5 sticky top-0 backdrop-blur-md"></thead>
                    <tbody id="previewBody" class="divide-y divide-slate-200 dark:divide-white/5"></tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between p-4 bg-emerald-50 dark:bg-emerald-500/5 rounded-2xl border border-emerald-100 dark:border-emerald-500/20">
            <p class="text-sm text-emerald-700 dark:text-emerald-300 px-2 font-medium">
                Kontrolloni nëse kombinimi Klasë/Paralele është korrekt para importit.
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
let classData = [];

document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: 'greedy',
        complete: function(results) {
            classData = results.data
                .map(row => normalizeClassRow(row))
                .filter(row => row.academic_year && row.base_grade);

            if (!classData.length) {
                alert('Gabim: Skedari nuk ka të dhëna valide.');
                return;
            }
            showPreview(classData);
        }
    });
});

function normalizeClassRow(row) {
    const clean = {};
    Object.keys(row).forEach(key => {
        const k = key.trim().toLowerCase();
        clean[k] = String(row[key] ?? '').trim();
    });

    return {
        academic_year: clean.academic_year || '<?= $autoYear ?>',
        base_grade: clean.base_grade || clean.grade || '',
        parallel: clean.parallel || '',
        teacher_id: clean.teacher_id || clean.user_id || null,
        max_students: clean.max_students || 30,
        status: clean.status || 'active'
    };
}

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    document.getElementById('rowCount').innerText = `${data.length} Klasa`;

    const thead = document.getElementById('previewHead');
    const tbody = document.getElementById('previewBody');
    const headers = Object.keys(data[0]);

    thead.innerHTML = `<tr>${headers.map(h => `<th class="px-4 py-3 text-xs font-bold uppercase text-slate-500">${h}</th>`).join('')}</tr>`;

    tbody.innerHTML = data.map(row => `
        <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
            ${headers.map(h => `<td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">${row[h] || '---'}</td>`).join('')}
        </tr>
    `).join('');
}

document.getElementById('submitImportBtn').onclick = async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = 'Duke u procesuar...';

    try {
        const response = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/process-import-classes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(classData)
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert(`✅ Sukses!\nU shtuan: ${result.imported} klasa.\nU anashkaluan: ${result.skipped}`);
            window.location.href = '/E-Shkolla/classes';
        } else {
            throw new Error(result.message);
        }
    } catch (e) {
        alert(e.message);
        btn.disabled = false;
        btn.innerHTML = 'Konfirmo & Importo';
    }
};

function toggleHelp() { document.getElementById('helpBox').classList.toggle('hidden'); }

function downloadClassTemplate() {
    const headers = ["academic_year","base_grade","parallel","teacher_id","max_students","status"];
    const sample = ["<?= $autoYear ?>","10","A","","30","active"];
    const csv = [headers.join(","), sample.join(",")].join("\n");
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "modeli_klasave.csv";
    link.click();
}
</script>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>