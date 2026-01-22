<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$userId    = (int) ($_SESSION['user']['id'] ?? 0);

// --- 1. SAVE LOGIC (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int) $_POST['student_id'];
    $column = $_POST['column'] ?? '';
    $value = $_POST['value'] ?? '';

    $allowedColumns = ['p1_oral', 'p1_test', 'p1_final', 'p2_oral', 'p2_test', 'p2_final', 'comment'];

    if (in_array($column, $allowedColumns)) {
        try {
            $tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $tStmt->execute([$userId]);
            $realTeacherId = $tStmt->fetchColumn();

            $sql = "INSERT INTO grades (school_id, teacher_id, student_id, class_id, subject_id, $column)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        $column = VALUES($column), 
                        teacher_id = VALUES(teacher_id), 
                        updated_at = NOW()";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$schoolId, $realTeacherId, $studentId, $classId, $subjectId, $value]);
            echo "success";
        } catch (Exception $e) {
            http_response_code(500);
            echo "error";
        }
    }
    exit;
}

// --- 2. DATA FETCHING ---
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.email, 
           g.p1_oral, g.p1_test, g.p1_final, 
           g.p2_oral, g.p2_test, g.p2_final, g.comment 
    FROM student_class sc 
    JOIN students s ON s.student_id = sc.student_id 
    LEFT JOIN grades g ON g.student_id = s.student_id 
        AND g.subject_id = ? 
        AND g.class_id = ?
    WHERE sc.class_id = ? 
    ORDER BY s.name ASC");
$stmt->execute([$subjectId, $classId, $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #journalPrintArea, #journalPrintArea * { visibility: visible; }
        #journalPrintArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    }
    .grade-input:focus { background-color: rgba(99, 102, 241, 0.05); outline: none; }
</style>

<div class="px-4 sm:px-6 lg:px-8 py-8" id="journalPrintArea">
    <div class="sm:flex sm:items-center justify-between mb-8 no-print">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Ditari i Klasës</h1>
            <p class="mt-1 text-sm text-slate-500">Përdorni tastet e shigjetave për navigim të shpejtë (Excel-style).</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="flex items-center gap-2 bg-white border border-slate-300 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Printo Ditarin
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-lg border-2 border-slate-300 dark:border-white/20 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[1100px]">
                <thead>
                    <tr class="bg-slate-100 dark:bg-slate-800 border-b-2 border-slate-300">
                        <th class="w-12 px-2 py-4 text-[10px] font-bold uppercase text-slate-600 border-r border-slate-300 text-center">Nr.</th>
                        <th class="w-64 px-4 py-4 text-xs font-bold uppercase text-slate-600 border-r border-slate-300">Nxënësi</th>
                        <th colspan="3" class="text-center text-[10px] font-bold uppercase bg-blue-50/50 border-r border-slate-300">Periudha I</th>
                        <th colspan="3" class="text-center text-[10px] font-bold uppercase bg-emerald-50/50 border-r border-slate-300">Periudha II</th>
                        <th class="w-20 text-center text-[10px] font-bold uppercase bg-amber-50 border-r border-slate-300">Vjetore</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase text-slate-600 text-center">Vërejtje</th>
                    </tr>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 text-[9px] uppercase text-slate-500">
                        <th class="border-r border-slate-300"></th>
                        <th class="border-r border-slate-300"></th>
                        <th class="border-r border-slate-200 text-center py-2">Gojë</th>
                        <th class="border-r border-slate-200 text-center py-2">Test</th>
                        <th class="border-r border-slate-300 text-center py-2 font-bold text-blue-600 bg-blue-50/30">Nota P1</th>
                        <th class="border-r border-slate-200 text-center py-2">Gojë</th>
                        <th class="border-r border-slate-200 text-center py-2">Test</th>
                        <th class="border-r border-slate-300 text-center py-2 font-bold text-emerald-600 bg-emerald-50/30">Nota P2</th>
                        <th class="border-r border-slate-300"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="journalBody" class="divide-y divide-slate-200">
                    <?php $nr = 1; foreach ($students as $row): ?>
                    <tr class="group hover:bg-slate-50 transition-colors">
                        <td class="px-2 py-0 text-center text-xs text-slate-400 border-r border-slate-200"><?= $nr++ ?></td>
                        <td class="px-4 py-0 border-r border-slate-200">
                            <div class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($row['name']) ?></div>
                        </td>
                        
                        <td class="p-0 border-r border-slate-200"><input type="number" class="grade-input w-full h-10 text-center bg-transparent border-none" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="oral" value="<?= $row['p1_oral'] ?>" min="1" max="5"></td>
                        <td class="p-0 border-r border-slate-200"><input type="number" class="grade-input w-full h-10 text-center bg-transparent border-none" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="test" value="<?= $row['p1_test'] ?>" min="1" max="5"></td>
                        <td class="p-0 border-r border-slate-300 bg-blue-50/30"><input type="number" class="p1-final w-full h-10 text-center bg-transparent border-none font-bold text-blue-700" value="<?= $row['p1_final'] ?>" readonly></td>
                        
                        <td class="p-0 border-r border-slate-200"><input type="number" class="grade-input w-full h-10 text-center bg-transparent border-none" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="oral" value="<?= $row['p2_oral'] ?>" min="1" max="5"></td>
                        <td class="p-0 border-r border-slate-200"><input type="number" class="grade-input w-full h-10 text-center bg-transparent border-none" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="test" value="<?= $row['p2_test'] ?>" min="1" max="5"></td>
                        <td class="p-0 border-r border-slate-300 bg-emerald-50/30"><input type="number" class="p2-final w-full h-10 text-center bg-transparent border-none font-bold text-emerald-700" value="<?= $row['p2_final'] ?>" readonly></td>
                        
                        <td class="p-0 border-r border-slate-300 bg-amber-50 text-center font-black text-slate-900 yearly-avg text-sm"></td>

                        <td class="px-4 py-0 relative">
                            <input type="text" class="auto-save-comment w-full h-10 bg-transparent border-none text-[11px] italic focus:ring-0" data-student-id="<?= $row['student_id'] ?>" placeholder="..." value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
                            <div class="save-indicator absolute right-1 top-4 opacity-0 transition-opacity">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-100 font-bold border-t-2 border-slate-300 no-print">
                    <tr class="h-10 text-slate-600 text-[11px]">
                        <td colspan="2" class="px-4 text-right uppercase tracking-tighter">Mesatarja e Klasës:</td>
                        <td id="avg-p1-oral" class="text-center border-r border-slate-200">0</td>
                        <td id="avg-p1-test" class="text-center border-r border-slate-200">0</td>
                        <td id="avg-p1-final" class="text-center border-r border-slate-300 bg-blue-100/50">0</td>
                        <td id="avg-p2-oral" class="text-center border-r border-slate-200">0</td>
                        <td id="avg-p2-test" class="text-center border-r border-slate-200">0</td>
                        <td id="avg-p2-final" class="text-center border-r border-slate-300 bg-emerald-100/50">0</td>
                        <td id="avg-yearly" class="text-center bg-amber-100/50 border-r border-slate-300">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
// --- Excel Style Keyboard Navigation ---
document.getElementById('journalBody').addEventListener('keydown', function(e) {
    const active = document.activeElement;
    if (!active || (!active.classList.contains('grade-input') && !active.classList.contains('auto-save-comment'))) return;

    const row = active.closest('tr');
    const td = active.closest('td');
    const colIndex = Array.from(row.cells).indexOf(td);

    if (e.key === "ArrowDown" || e.key === "Enter") {
        e.preventDefault();
        const nextRow = row.nextElementSibling;
        if (nextRow) nextRow.cells[colIndex].querySelector('input')?.focus();
    } else if (e.key === "ArrowUp") {
        e.preventDefault();
        const prevRow = row.previousElementSibling;
        if (prevRow) prevRow.cells[colIndex].querySelector('input')?.focus();
    } else if (e.key === "ArrowRight" && active.selectionEnd === active.value.length) {
        td.nextElementSibling?.querySelector('input')?.focus();
    } else if (e.key === "ArrowLeft" && active.selectionStart === 0) {
        td.previousElementSibling?.querySelector('input')?.focus();
    }
});

// --- Save Logic ---
function saveData(studentId, column, value) {
    const row = document.querySelector(`[data-student-id="${studentId}"]`).closest('tr');
    const indicator = row.querySelector('.save-indicator');
    
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('column', column);
    formData.append('value', value);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === "success") {
            indicator.style.opacity = '1';
            setTimeout(() => { indicator.style.opacity = '0'; }, 800);
            updateClassAverages();
        }
    });
}

// --- Calculation Logic ---
function calculateRow(row) {
    const studentId = row.querySelector('.grade-input').dataset.studentId;
    
    ['p1', 'p2'].forEach(p => {
        const oral = parseFloat(row.querySelector(`[data-period="${p}"][data-field="oral"]`).value) || 0;
        const test = parseFloat(row.querySelector(`[data-period="${p}"][data-field="test"]`).value) || 0;
        const finalInput = row.querySelector(`.${p}-final`);

        if (oral > 0 && test > 0) {
            const avg = Math.round((oral + test) / 2);
            if (finalInput.value != avg) {
                finalInput.value = avg;
                saveData(studentId, `${p}_final`, avg);
            }
        } else {
            finalInput.value = '';
        }
    });

    const f1 = parseFloat(row.querySelector('.p1-final').value) || 0;
    const f2 = parseFloat(row.querySelector('.p2-final').value) || 0;
    const yearlyCell = row.querySelector('.yearly-avg');
    if (f1 > 0 && f2 > 0) {
        yearlyCell.innerText = Math.round((f1 + f2) / 2);
    } else {
        yearlyCell.innerText = '';
    }
}

function updateClassAverages() {
    const columns = [
        { selector: '[data-period="p1"][data-field="oral"]', target: 'avg-p1-oral' },
        { selector: '[data-period="p1"][data-field="test"]', target: 'avg-p1-test' },
        { selector: '.p1-final', target: 'avg-p1-final' },
        { selector: '[data-period="p2"][data-field="oral"]', target: 'avg-p2-oral' },
        { selector: '[data-period="p2"][data-field="test"]', target: 'avg-p2-test' },
        { selector: '.p2-final', target: 'avg-p2-final' }
    ];

    columns.forEach(col => {
        const inputs = Array.from(document.querySelectorAll(col.selector));
        const values = inputs.map(i => parseFloat(i.value || i.innerText)).filter(v => v > 0);
        const avg = values.length ? (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2) : 0;
        document.getElementById(col.target).innerText = avg;
    });

    const yearlyValues = Array.from(document.querySelectorAll('.yearly-avg')).map(c => parseFloat(c.innerText)).filter(v => v > 0);
    document.getElementById('avg-yearly').innerText = yearlyValues.length ? (yearlyValues.reduce((a, b) => a + b, 0) / yearlyValues.length).toFixed(2) : 0;
}

document.getElementById('journalBody').addEventListener('input', (e) => {
    if (e.target.classList.contains('grade-input')) {
        let val = parseInt(e.target.value);
        if (val < 1) e.target.value = '';
        if (val > 5) e.target.value = 5;
        
        const sid = e.target.dataset.studentId;
        const col = `${e.target.dataset.period}_${e.target.dataset.field}`;
        saveData(sid, col, e.target.value);
        calculateRow(e.target.closest('tr'));
    }
});

document.getElementById('journalBody').addEventListener('blur', (e) => {
    if (e.target.classList.contains('auto-save-comment')) {
        saveData(e.target.dataset.studentId, 'comment', e.target.value);
    }
}, true);

// Initial Load
document.querySelectorAll('#journalBody tr').forEach(row => calculateRow(row));
updateClassAverages();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>