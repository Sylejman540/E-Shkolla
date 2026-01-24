<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$school_id = $_SESSION['user']['school_id'] ?? 0;

/* ===============================
   FETCH ACTIVE TEACHERS
================================ */
$stmtTeachers = $pdo->prepare("
    SELECT user_id, name 
    FROM teachers 
    WHERE school_id = ? 
      AND status = 'active'
    ORDER BY name ASC
");
$stmtTeachers->execute([$school_id]);
$availableTeachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FETCH USED CLASS_HEADERS (Kujdestarët aktualë)
================================ */
$stmtUsed = $pdo->prepare("
    SELECT class_header
    FROM classes
    WHERE school_id = ?
      AND class_header IS NOT NULL
");
$stmtUsed->execute([$school_id]);
$usedHeaders = array_flip($stmtUsed->fetchAll(PDO::FETCH_COLUMN));

/* ===============================
   AUTO ACADEMIC YEAR
================================ */
$currentMonth = date('n');
$currentYear  = date('Y');
$autoYear     = ($currentMonth >= 9)
    ? $currentYear . '/' . ($currentYear + 1)
    : ($currentYear - 1) . '/' . $currentYear;

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 bg-slate-50 min-h-screen">

    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Importo Klasat</h1>
            <p class="mt-1 text-sm text-slate-500 italic">Shtoni klasat e reja dhe caktoni kujdestarët përmes CSV.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 mt-4 sm:mt-0 w-full sm:w-auto">
            <button onclick="toggleHelp()"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold
                    text-amber-700 bg-amber-50 border border-amber-200
                    rounded-xl hover:bg-amber-100 transition-all shadow-sm active:scale-95">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>ID e Mësuesve</span>
            </button>

            <button onclick="downloadClassTemplate()"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold
                    text-emerald-700 bg-emerald-50 border border-emerald-200
                    rounded-xl hover:bg-emerald-100 transition-all shadow-sm active:scale-95">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span>Modeli CSV</span>
            </button>

            <a href="/E-Shkolla/classes"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold
                    text-slate-700 bg-white border border-slate-200
                    rounded-xl hover:bg-slate-50 transition-all shadow-sm active:scale-95">
                <span>Anulo</span>
            </a>
    </div>
    </div>

    <div id="helpBox" class="hidden mb-6 p-6 bg-slate-900 text-white rounded-[2rem] shadow-2xl border border-slate-800">
        <div class="flex items-center justify-between mb-6">
            <h4 class="font-black text-emerald-400 text-xs uppercase tracking-widest">Lista e Mësuesve Aktivë</h4>
            <span class="text-[10px] text-slate-400 italic font-medium">* ID duhet vendosur në kolonën teacher_id</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
            <?php foreach ($availableTeachers as $t): 
                $isUsed = isset($usedHeaders[$t['user_id']]);
            ?>
                <div class="p-3 rounded-2xl border transition-all
                    <?= $isUsed
                        ? 'bg-slate-800/50 border-red-500/20 opacity-50'
                        : 'bg-slate-800 border-slate-700 hover:border-emerald-500/50' ?>
                ">
                    <div class="flex justify-between items-start mb-1">
                        <span class="font-black text-sm <?= $isUsed ? 'text-red-400' : 'text-emerald-400' ?>">
                            ID: <?= (int)$t['user_id'] ?>
                        </span>
                        <?php if ($isUsed): ?>
                            <span class="text-[9px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full font-bold uppercase">Kujdestar</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-[11px] text-slate-300 font-medium truncate">
                        <?= htmlspecialchars($t['name']) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between text-[11px]">
            <p class="text-slate-400">Viti akademik aktual: <b class="text-white"><?= $autoYear ?></b></p>
            <p class="text-slate-400">Statuset e lejuara: <code class="bg-slate-800 px-2 py-1 rounded text-emerald-400">active</code>, <code class="bg-slate-800 px-2 py-1 rounded text-slate-400">inactive</code></p>
        </div>
    </div>

    <div id="uploadContainer" 
         class="bg-white dark:bg-gray-900 rounded-[2.5rem] border-2 border-dashed border-slate-200 dark:border-white/10 p-16 text-center shadow-sm hover:border-indigo-400 transition-all group">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zgjidhni skedarin CSV të Klasave</h3>
            <p class="text-sm text-slate-500 mb-6 italic">Viti Akademik dhe Paralelja (Grade) janë fusha të kërkuara.</p>
            
            <input type="file" id="csvFileInput" class="hidden" accept=".csv">
            <button onclick="document.getElementById('csvFileInput').click()" 
                    class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">
                Përzgjidh Skedarin
            </button>
        </div>
    </div>

    <div id="previewContainer" class="hidden space-y-6">
        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-black text-slate-800 text-xs uppercase tracking-widest">Preview e Klasave</h3>
                <span id="rowCount" class="text-xs font-black px-4 py-1.5 bg-indigo-600 text-white rounded-full shadow-lg shadow-indigo-100">0 Klasa</span>
            </div>

            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left border-collapse" id="previewTable">
                    <thead id="previewHead" class="bg-slate-50/80 sticky top-0 backdrop-blur-md">
                        </thead>
                    <tbody id="previewBody" class="divide-y divide-slate-100">
                        </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-6 bg-white rounded-[2rem] border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3 text-emerald-600 font-bold text-sm px-2">
                <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                Të dhënat u lexuan. Verifikoni listën para importimit.
            </div>
            <div class="flex gap-3 w-full sm:w-auto">
                <button onclick="location.reload()" class="flex-1 sm:flex-none px-8 py-3 text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">Pastro</button>
                <button id="submitImportBtn"
                    class="flex-1 sm:flex-none bg-emerald-600 text-white px-12 py-3 rounded-2xl font-black hover:bg-emerald-500 shadow-lg shadow-emerald-100 transition-all active:scale-95">
                    Importo
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    #previewTable th { padding: 1rem 2rem; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    #previewTable td { padding: 1rem 2rem; font-size: 13px; font-weight: 500; color: #1e293b; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script>
let classData = [];

document.getElementById('csvFileInput').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: 'greedy',
        complete: res => {
            classData = res.data
                .map(r => normalize(r))
                .filter(r => r.grade);

            if (classData.length === 0) {
                alert("Gabim: Skedari është bosh ose nuk ka kolonën 'grade'.");
                return;
            }
            showPreview(classData);
        }
    });
});

function normalize(row) {
    const clean = {};
    Object.keys(row).forEach(k => clean[k.toLowerCase().trim()] = String(row[k] ?? '').trim());

    return {
        academic_year: clean.academic_year || '<?= $autoYear ?>',
        grade: clean.grade || '',
        teacher_id: clean.teacher_id || 'NULL',
        max_students: clean.max_students || 30,
        status: clean.status || 'active'
    };
}

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    document.getElementById('rowCount').innerText = data.length + ' Klasa';

    const headers = Object.keys(data[0]);
    
    // Injekto Header-at
    document.getElementById('previewHead').innerHTML = 
        `<tr>${headers.map(h => `<th>${h.replace('_', ' ')}</th>`).join('')}</tr>`;

    // Injekto Rreshtat
    document.getElementById('previewBody').innerHTML = data.map(r => `
        <tr class="hover:bg-slate-50 transition-colors">
            ${headers.map(h => {
                let val = r[h];
                if(h === 'status') {
                    const isAct = val.toLowerCase() === 'active';
                    return `<td><span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase ${isAct ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${val}</span></td>`;
                }
                if(h === 'teacher_id' && val === 'NULL') {
                    return `<td class="text-slate-400 italic font-normal">Pa caktuar</td>`;
                }
                return `<td>${val}</td>`;
            }).join('')}
        </tr>
    `).join('');
}

document.getElementById('submitImportBtn').onclick = async function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<span class="flex items-center gap-2 italic">Duke u procesuar...</span>`;

    try {
        const res = await fetch(
            '/E-Shkolla/dashboard/schooladmin-dashboard/partials/classes/process-import.php',
            {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(classData)
            }
        );

        const out = await res.json();
        if (out.status === 'success') {
            alert(`Sukses! U shtuan ${out.imported} klasa të reja.`);
            location.href = '/E-Shkolla/classes';
        } else {
            throw new Error(out.message || 'Gabim gjatë importit.');
        }
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.innerHTML = 'Konfirmo & Importo';
    }
};

function downloadClassTemplate() {
    const headers = ["academic_year", "grade", "teacher_id", "max_students", "status"];
    const row = ["<?= $autoYear ?>", "10-A", "1", "30", "active"];
    const csv = [headers.join(","), row.join(",")].join("\n");

    const blob = new Blob(["\ufeff" + csv], {type:"text/csv;charset=utf-8;"});
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "modeli_klasave_e_shkolla.csv";
    a.click();
}

function toggleHelp() {
    document.getElementById('helpBox').classList.toggle('hidden');
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>