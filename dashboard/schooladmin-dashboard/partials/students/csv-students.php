<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Fetch classes for the "Help" box
$school_id = $_SESSION['user']['school_id'] ?? 0;
$stmtClasses = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?  ORDER BY grade ASC");
$stmtClasses->execute([$school_id]);
$availableClasses = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-8 gap-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Importo Nxënësit</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ngarkoni një skedar CSV për të shtuar nxënësit në masë.</p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
        <button onclick="toggleHelp()" 
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition-all shadow-sm active:scale-95">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.444 1.103m.499 3.103a1 1 0 110-2 1 1 0 010 2zV12 17h.01"></path>
            </svg>
            Ndihmë
        </button>

        <button onclick="downloadStudentTemplate()" 
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-all shadow-sm active:scale-95">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Shkarko Modelin
        </button>

        <a href="/E-Shkolla/students" 
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 dark:bg-gray-800 dark:text-slate-300 dark:border-white/10 transition-all shadow-sm active:scale-95">
            Anulo
        </a>
    </div>
    </div>

   <div id="helpBox" class="hidden mb-6 p-6 bg-slate-800 text-white rounded-2xl shadow-xl border border-slate-700">
    <h4 class="font-bold mb-4 text-emerald-400 text-sm">Udhëzime për Import CSV</h4>

    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2">
        <?php foreach($availableClasses as $class): ?>
            <div class="bg-slate-700 p-2 rounded border border-slate-600 text-[11px]">
                <span class="text-emerald-400 font-bold">ID: <?= $class['id'] ?></span>
                <span class="text-slate-300">- <?= htmlspecialchars($class['grade']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="mt-4 text-xs text-slate-300">
        Kolonat e detyrueshme: <b>name, email, class_id</b>
    </p>

    <p class="mt-2 text-xs text-slate-400">
        Vlerat e lejuara: <b>gender</b> (male | female), <b>status</b> (active | inactive)
    </p>

    <p class="mt-2 text-xs text-slate-500 italic">
        Formati i datëlindjes: YYYY-MM-DD • Emrat e kolonave duhet të jenë saktësisht si në model.
    </p>
</div>


    <div id="uploadContainer" class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10 p-12 text-center shadow-sm transition-all hover:border-indigo-400">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zgjidhni skedarin CSV të Nxënësve</h3>
            <p class="text-sm text-slate-500 mb-6 italic">Emri, Email-i dhe ID e Klasës janë fusha të kërkuara.</p>
            
            <input type="file" id="csvFileInput" class="hidden" accept=".csv">
            <button onclick="document.getElementById('csvFileInput').click()" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">
                Përzgjidh Skedarin
            </button>
        </div>
    </div>

    <div id="previewContainer" class="hidden space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-white/10 flex justify-between items-center bg-slate-50/50 dark:bg-white/5">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">Preview e Nxënësve</h3>
                <span id="rowCount" class="text-xs font-bold px-2 py-1 bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-lg">0 Rreshta</span>
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
                Të dhënat u lexuan me sukses. Verifikoni listën para importimit final.
            </p>
            <div class="flex gap-3">
                <button onclick="location.reload()" class="px-6 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-800 dark:text-slate-400">Pastro</button>
                <button id="submitImportBtn" class="bg-emerald-600 text-white px-8 py-2.5 rounded-xl font-bold hover:bg-emerald-500 shadow-lg shadow-emerald-500/20 transition-all">
                    Importo
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script>
let studentData = [];

/* --- CSV Logic --- */
document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: 'greedy',
        complete: function(results) {
            studentData = results.data
                .map(row => normalizeStudentRow(row))
                .filter(row => row.name && row.email && row.class_id);

            if (!studentData.length) {
                alert('Skedari nuk përmban të dhëna valide (mungon Emri, Email ose Class ID).');
                return;
            }
            showPreview(studentData);
        }
    });
});

function normalizeStudentRow(row) {
    const clean = {};
    Object.keys(row).forEach(key => {
        const k = key.trim().toLowerCase();
        clean[k] = String(row[key] ?? '').trim();
    });

    return {
        name: clean.name || '',
        email: clean.email || '',
        class_id: parseInt(clean.class_id || clean.class || 0),
        date_birth: clean.date_birth || clean.birthday || '',
        gender: clean.gender || 'male',
        student_code: clean.student_code || clean.code || '',
        password: clean.password || 'Nxenesi2026!'
    };
}

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    document.getElementById('rowCount').innerText = `${data.length} Nxënës`;

    const thead = document.getElementById('previewHead');
    const tbody = document.getElementById('previewBody');
    const headers = Object.keys(data[0]);

    thead.innerHTML = `<tr>${headers.map(h => `<th class="px-4 py-3 text-xs font-bold uppercase text-slate-500">${h}</th>`).join('')}</tr>`;

    tbody.innerHTML = data.map(row => `
        <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
            ${headers.map(h => {
                let val = row[h];
                if (h === 'password') val = '••••••';
                return `<td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300 truncate max-w-[150px]">${val}</td>`;
            }).join('')}
        </tr>
    `).join('');
}

/* --- Submission --- */
document.getElementById('submitImportBtn').onclick = async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = 'Duke u procesuar...';

    try {
        const response = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/students/process-import-students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(studentData)
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert(`✅ Importimi u krye!\nU shtuan: ${result.imported}\nU anashkaluan (ekzistues): ${result.skipped}`);
            window.location.href = '/E-Shkolla/students';
        } else {
            throw new Error(result.message || 'Gabim gjatë importimit.');
        }
    } catch (e) {
        alert(e.message);
        btn.disabled = false;
        btn.innerHTML = 'Konfirmo & Importo';
    }
};

/* --- Helpers --- */
function toggleHelp() { document.getElementById('helpBox').classList.toggle('hidden'); }

function downloadStudentTemplate() {
    const headers = ["name","email","class_id","date_birth","gender","student_code","password"];
    const sample = ["Filan Fisteku","filan@email.com","1","2010-05-20","male","STU-001","Pass123!"];
    const csv = [headers.join(","), sample.join(",")].join("\n");
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "modeli_nxenesve.csv";
    link.click();
}
</script>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>