<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../db.php'; 

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
   FETCH USED CLASS_HEADERS
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

<div class="px-4 sm:px-6 lg:px-8 py-8">

    <!-- HEADER -->
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                Importo Klasat
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Shtoni klasa të reja në masë përmes skedarit CSV.
            </p>
        </div>

        <div class="flex gap-3">
            <button onclick="toggleHelp()"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                       text-amber-700 bg-amber-50 border border-amber-200
                       rounded-xl hover:bg-amber-100 shadow-sm">
                Ndihmë (ID e Mësuesve)
            </button>

            <button onclick="downloadClassTemplate()"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                       text-emerald-700 bg-emerald-50 border border-emerald-200
                       rounded-xl hover:bg-emerald-100 shadow-sm">
                Shkarko Modelin
            </button>

            <a href="/E-Shkolla/classes"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                      text-slate-700 bg-white border border-slate-200
                      rounded-xl hover:bg-slate-50 shadow-sm">
                Anulo
            </a>
        </div>
    </div>

    <!-- HELP BOX -->
    <div id="helpBox"
         class="hidden mb-6 p-6 bg-slate-800 text-white
                rounded-2xl shadow-xl border border-slate-700">

        <h4 class="font-bold mb-4 text-emerald-400 text-sm">
            ID-të e Mësuesve Kujdestarë
        </h4>

        <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <?php foreach ($availableTeachers as $t): 
                $isUsed = isset($usedHeaders[$t['user_id']]);
            ?>
                <div class="p-2 rounded border text-[11px]
                    <?= $isUsed
                        ? 'bg-slate-900 border-red-500/40 opacity-60 line-through'
                        : 'bg-slate-700 border-slate-600' ?>
                ">
                    <span class="font-bold <?= $isUsed ? 'text-red-400' : 'text-emerald-400' ?>">
                        ID: <?= (int)$t['user_id'] ?>
                    </span>
                    <span class="text-slate-300">
                        – <?= htmlspecialchars($t['name']) ?>
                    </span>

                    <?php if ($isUsed): ?>
                        <span class="block text-[10px] text-red-400 mt-1">
                            (tashmë kujdestar)
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            Viti akademik aktual: <b><?= $autoYear ?></b>.
            Statusi: <code>active</code> ose <code>inactive</code>.
        </p>
    </div>

    <!-- UPLOAD -->
    <div id="uploadContainer"
         class="bg-white dark:bg-gray-900 rounded-3xl
                border-2 border-dashed border-slate-200 dark:border-white/10
                p-12 text-center shadow-sm hover:border-indigo-400">

        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
            Zgjidhni skedarin CSV për Klasat
        </h3>

        <p class="text-sm text-slate-500 mb-6 italic">
            Kolonat e nevojshme: academic_year, grade
        </p>

        <input type="file" id="csvFileInput" class="hidden" accept=".csv">

        <button onclick="document.getElementById('csvFileInput').click()"
            class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold
                   hover:bg-indigo-500 shadow-lg">
            Përzgjidh Skedarin
        </button>
    </div>

    <!-- PREVIEW -->
    <div id="previewContainer" class="hidden space-y-6 mt-8">
        <div class="bg-white dark:bg-gray-900 rounded-2xl border
                    border-slate-200 dark:border-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b flex justify-between">
                <h3 class="font-bold text-sm uppercase">Preview</h3>
                <span id="rowCount" class="text-xs font-bold">0</span>
            </div>

            <div class="overflow-x-auto max-h-[500px]">
                <table class="w-full" id="previewTable">
                    <thead id="previewHead"></thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between">
            <button onclick="location.reload()" class="text-slate-600">
                Pastro
            </button>
            <button id="submitImportBtn"
                class="bg-emerald-600 text-white px-8 py-2.5 rounded-xl font-bold">
                Konfirmo & Importo
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script>
let classData = [];

document.getElementById('csvFileInput').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: true,
        complete: res => {
            classData = res.data
                .map(r => normalize(r))
                .filter(r => r.grade);

            showPreview(classData);
        }
    });
});

function normalize(row) {
    const clean = {};
    Object.keys(row).forEach(k => clean[k.toLowerCase().trim()] = String(row[k]).trim());

    return {
        academic_year: clean.academic_year || '<?= $autoYear ?>',
        grade: clean.grade || '',
        teacher_id: clean.teacher_id || null,
        max_students: clean.max_students || 30,
        status: clean.status || 'active'
    };
}

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');

    document.getElementById('rowCount').innerText = data.length + ' Klasa';

    const headers = Object.keys(data[0]);
    document.getElementById('previewHead').innerHTML =
        `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

    document.getElementById('previewBody').innerHTML =
        data.map(r =>
            `<tr>${headers.map(h => `<td>${r[h] ?? ''}</td>`).join('')}</tr>`
        ).join('');
}

document.getElementById('submitImportBtn').onclick = async () => {
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
        alert(`U shtuan ${out.imported} klasa`);
        location.href = '/E-Shkolla/classes';
    } else {
        alert(out.message);
    }
};

function downloadClassTemplate() {
    const csv = [
        "academic_year,grade,teacher_id,max_students,status",
        "<?= $autoYear ?>,10,1,30,active"
    ].join("\n");

    const blob = new Blob(["\ufeff" + csv], {type:"text/csv"});
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "modeli_klasave.csv";
    a.click();
}

function toggleHelp() {
    document.getElementById('helpBox').classList.toggle('hidden');
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
