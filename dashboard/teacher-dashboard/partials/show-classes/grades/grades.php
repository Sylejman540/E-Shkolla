<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$userId    = (int) ($_SESSION['user']['id'] ?? 0);

// --- AJAX SAVE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int) $_POST['student_id'];
    $column = $_POST['column'] ?? '';
    $value = $_POST['value'] ?? '';

    $allowedColumns = [
        'p1_homework', 'p1_activity', 'p1_project', 'p1_test', 'p1_final',
        'p2_homework', 'p2_activity', 'p2_project', 'p2_test', 'p2_final',
        'comment'
    ];

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
        } catch (Exception $e) { echo "error"; }
    }
    exit;
}

// --- DATA FETCHING ---
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, 
           g.p1_homework, g.p1_activity, g.p1_project, g.p1_test, g.p1_final, 
           g.p2_homework, g.p2_activity, g.p2_project, g.p2_test, g.p2_final, g.comment 
    FROM student_class sc 
    JOIN students s ON s.student_id = sc.student_id 
    LEFT JOIN grades g ON g.student_id = s.student_id AND g.subject_id = ? AND g.class_id = ?
    WHERE sc.class_id = ? 
    ORDER BY s.name ASC");
$stmt->execute([$subjectId, $classId, $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    .grade-input { 
        width: 100%; height: 48px; text-align: center; border: none; 
        background: transparent; font-size: 15px; font-weight: 700;
        display: block; cursor: text; color: #1e293b; transition: all 0.1s;
    }
    .grade-input:focus { background-color: #fff; outline: 2px solid #6366f1; z-index: 10; box-shadow: inset 0 0 10px rgba(99, 102, 241, 0.1); }
    
    /* Remove Spinners */
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    
    .table-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
    .header-cell { background-color: #f8fafc; text-transform: uppercase; font-size: 10px; font-weight: 800; color: #64748b; border-bottom: 2px solid #e2e8f0; letter-spacing: 0.05em; }
    
    .save-indicator { position: absolute; right: 0; top: 0; bottom: 0; width: 3px; background: #22c55e; opacity: 0; transition: opacity 0.3s; }
    
    @media print { 
        .no-print { display: none !important; }
        .p-8 { padding: 0 !important; }
        .table-container { border: none; box-shadow: none; }
    }
</style>

<div class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-[1500px] mx-auto">
        <div class="mb-6 flex justify-between items-end no-print">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Regjistri i Arritjeve</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider">Sistemi 1-5</span>
                    <span class="text-slate-400 text-xs font-medium">Lënda dhe Klasa e përzgjedhur</span>
                </div>
            </div>
            <button onclick="window.print()" class="bg-white border border-slate-200 px-4 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                EKSPORTO PDF
            </button>
        </div>

        <div class="table-container overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th rowspan="2" class="header-cell px-2 border-r w-10 text-center">#</th>
                            <th rowspan="2" class="header-cell px-6 border-r w-72 text-left bg-white">Nxënësi</th>
                            <th colspan="5" class="header-cell py-3 border-r border-b bg-blue-50/50 text-blue-600 text-center">Periudha I</th>
                            <th colspan="5" class="header-cell py-3 border-r border-b bg-indigo-50/50 text-indigo-600 text-center">Periudha II</th>
                            <th rowspan="2" class="header-cell px-4 border-r w-24 text-center bg-slate-100">Vjetore</th>
                            <th rowspan="2" class="header-cell px-4 text-left">Shënime / Komente</th>
                        </tr>
                        <tr>
                            <th class="header-cell border-r py-2 w-16 text-center">Det.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Akt.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-16 text-center bg-blue-100 text-blue-800">Nota</th>
                            
                            <th class="header-cell border-r py-2 w-16 text-center">Det.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Akt.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-16 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-16 text-center bg-indigo-100 text-indigo-800">Nota</th>
                        </tr>
                    </thead>
                    <tbody id="journalBody">
                    <?php if (empty($students)): ?>
                        <tr><td colspan="14" class="px-6 py-12 text-center text-slate-400 italic bg-white">Nuk u gjet asnjë nxënës.</td></tr>
                    <?php else: ?>
                        <?php $nr = 1; foreach ($students as $row): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors bg-white relative">
                            <td class="text-center text-[10px] font-bold text-slate-300 border-r bg-slate-50 w-10"><?= $nr++ ?></td>
                            <td class="px-4 border-r font-bold text-slate-700 text-[13px] uppercase truncate"><?= htmlspecialchars($row['name']) ?></td>
                            
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="homework" value="<?= $row['p1_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="activity" value="<?= $row['p1_activity'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="project" value="<?= $row['p1_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="test" value="<?= $row['p1_test'] ?>"></td>
                            <td class="border-r p-0 bg-blue-50/50"><input type="text" class="grade-input p1-final text-blue-700" value="<?= $row['p1_final'] ?>" readonly tabindex="-1"></td>

                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="homework" value="<?= $row['p2_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="activity" value="<?= $row['p2_activity'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="project" value="<?= $row['p2_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="test" value="<?= $row['p2_test'] ?>"></td>
                            <td class="border-r p-0 bg-indigo-50/50"><input type="text" class="grade-input p2-final text-indigo-700" value="<?= $row['p2_final'] ?>" readonly tabindex="-1"></td>

                            <td class="border-r p-0 bg-slate-100/50 text-center font-black yearly-avg text-slate-900 text-sm"></td>
                            
                            <td class="px-3 relative">
                                <input type="text" class="auto-save-comment w-full h-8 bg-transparent border-none text-xs italic text-slate-500 focus:outline-none" 
                                    data-student-id="<?= $row['student_id'] ?>" 
                                    placeholder="Shto vërejtje..." 
                                    value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
                                <div class="save-indicator"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function calculateRow(row) {
    const studentId = row.querySelector('.grade-input').dataset.studentId;
    ['p1', 'p2'].forEach(p => {
        const h = parseInt(row.querySelector(`[data-period="${p}"][data-field="homework"]`).value) || 0;
        const a = parseInt(row.querySelector(`[data-period="${p}"][data-field="activity"]`).value) || 0;
        const pr = parseInt(row.querySelector(`[data-period="${p}"][data-field="project"]`).value) || 0;
        const t = parseInt(row.querySelector(`[data-period="${p}"][data-field="test"]`).value) || 0;
        const finalInput = row.querySelector(`.${p}-final`);

        // Average calculation (only if at least one grade exists)
        const grades = [h, a, pr, t].filter(g => g > 0);
        if (grades.length > 0) {
            const avg = Math.round(grades.reduce((sum, g) => sum + g, 0) / grades.length);
            if (finalInput.value != avg) {
                finalInput.value = avg;
                saveData(studentId, `${p}_final`, avg);
            }
        } else { finalInput.value = ''; }
    });

    // Yearly Average
    const f1 = parseInt(row.querySelector('.p1-final').value) || 0;
    const f2 = parseInt(row.querySelector('.p2-final').value) || 0;
    const yearlyCell = row.querySelector('.yearly-avg');
    
    if (f1 > 0 && f2 > 0) {
        yearlyCell.innerText = Math.round((f1 + f2) / 2);
    } else {
        yearlyCell.innerText = f1 || f2 || '';
    }
}

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
        }
    });
}

document.getElementById('journalBody').addEventListener('input', (e) => {
    if (e.target.classList.contains('grade-input')) {
        let val = parseInt(e.target.value);
        if (val < 1) e.target.value = ''; 
        if (val > 5) e.target.value = 5; // System limit
        
        saveData(e.target.dataset.studentId, `${e.target.dataset.period}_${e.target.dataset.field}`, e.target.value);
        calculateRow(e.target.closest('tr'));
    }
});

document.getElementById('journalBody').addEventListener('blur', (e) => {
    if (e.target.classList.contains('auto-save-comment')) {
        saveData(e.target.dataset.studentId, 'comment', e.target.value);
    }
}, true);

// Init calculations on load
document.querySelectorAll('#journalBody tr').forEach(row => calculateRow(row));

// Enhanced Keyboard Navigation
document.addEventListener('keydown', (e) => {
    const active = document.activeElement;
    if (!active.classList.contains('grade-input')) return;
    
    const row = active.closest('tr');
    const cell = active.closest('td');
    const colIdx = cell.cellIndex;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        row.nextElementSibling?.cells[colIdx].querySelector('input')?.focus();
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        row.previousElementSibling?.cells[colIdx].querySelector('input')?.focus();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>