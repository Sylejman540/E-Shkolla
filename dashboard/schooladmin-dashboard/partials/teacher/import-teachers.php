<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Rruga duhet të jetë e saktë me strukturën tuaj
require_once __DIR__ . '/../../../../db.php'; 

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Importo Mësuesit</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ngarkoni një skedar CSV për të shtuar mësuesit në masë.</p>
        </div>
        <a href="javascript:history.back()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 dark:bg-gray-800 dark:text-slate-300 dark:border-white/10 transition-all">
            Anulo
        </a>
    </div>

    <div id="uploadContainer" class="bg-white dark:bg-gray-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-white/10 p-12 text-center shadow-sm transition-all hover:border-indigo-400">
        <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-indigo-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zgjidhni skedarin CSV</h3>
            <p class="text-sm text-slate-500 mb-6">Skedari duhet të ketë kolonat: emri, email, fjalkalimi, etj.</p>
            
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
                    <thead>
                        <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                            </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-white/5">
                        </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between p-4 bg-indigo-50 dark:bg-indigo-500/5 rounded-2xl border border-indigo-100 dark:border-indigo-500/20">
            <p class="text-sm text-indigo-700 dark:text-indigo-300 px-2">
                <i class="fas fa-info-circle mr-2"></i> Ju lutem rishikoni listën para se të përfundoni importimin.
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

document.getElementById('csvFileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: true,
        complete: function(results) {
            csvData = results.data;
            showPreview(results.data);
        }
    });
});

function showPreview(data) {
    document.getElementById('uploadContainer').classList.add('hidden');
    document.getElementById('previewContainer').classList.remove('hidden');
    document.getElementById('rowCount').innerText = `${data.length} Mësues`;

    const thead = document.querySelector('#previewTable thead');
    const tbody = document.querySelector('#previewTable tbody');
    
    const headers = Object.keys(data[0]);
    thead.innerHTML = `<tr>${headers.map(h => `<th class="px-6 py-4 text-[10px] font-bold uppercase text-slate-500">${h}</th>`).join('')}</tr>`;

    tbody.innerHTML = data.map(row => {
        const isError = !row.name || !row.email;
        return `
            <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors ${isError ? 'bg-red-50 dark:bg-red-900/10' : ''}">
                ${headers.map(h => {
                    let val = row[h] || '-';
                    if(h === 'password') val = '••••••';
                    return `<td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 truncate max-w-[200px]">${val}</td>`;
                }).join('')}
            </tr>`;
    }).join('');
}

document.getElementById('submitImportBtn').onclick = async function() {
    this.disabled = true;
    this.innerHTML = '<svg class="animate-spin h-5 w-5 text-white inline mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Duke u procesuar...';

    try {
        const response = await fetch('process-import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(csvData)
        });
        const result = await response.json();
        
        if(result.status === 'success') {
            alert(`U importuan me sukses ${result.imported} mësues!`);
            window.location.href = '/E-Shkolla/teachers'; // Kthehu te lista
        } else {
            alert('Gabim: ' + result.message);
            this.disabled = false;
        }
    } catch (e) {
        alert('Ndodhi një gabim gjatë komunikimit me serverin.');
        this.disabled = false;
    }
};
</script>

<?php $content = ob_get_clean(); require_once __DIR__ . '/../../index.php'; ?>