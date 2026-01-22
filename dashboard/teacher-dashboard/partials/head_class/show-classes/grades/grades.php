<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);
$userId    = (int) ($_SESSION['user']['id'] ?? 0);

// --- LOGJIKA E RUAJTJES (POST) ---
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

// --- MARRJA E TË DHËNAVE ---
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
        display: block; cursor: text; color: #1e293b;
    }
    .grade-input:focus { background-color: #fff; outline: 2px solid #2563eb; z-index: 10; box-shadow: inset 0 0 0 1px #2563eb; }
    
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    
    .table-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    .header-cell { background-color: #f1f5f9; text-transform: uppercase; font-size: 11px; font-weight: 800; color: #475569; border-bottom: 2px solid #e2e8f0; }
    
    @media print { .no-print { display: none !important; } }
</style>

<div class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-[1440px] mx-auto">
        <div class="mb-8 flex justify-between items-center no-print">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Regjistri i Arritjeve</h1>
                <p class="text-slate-500 font-bold">Sistemi i Kosovës (1-5)</p>
            </div>
        <button onclick="window.print()" class="no-print inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Printo Raportin
        </button>
        </div>

        <div class="table-container overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th rowspan="2" class="header-cell px-4 border-r w-12 text-center">Nr.</th>
                            <th rowspan="2" class="header-cell px-6 border-r w-80 text-left">Emri dhe Mbiemri</th>
                            <th colspan="5" class="header-cell py-4 border-r border-b bg-blue-50 text-blue-700 text-center">Periudha I</th>
                            <th colspan="5" class="header-cell py-4 border-r border-b bg-indigo-50 text-indigo-700 text-center">Periudha II</th>
                            <th rowspan="2" class="header-cell px-4 border-r w-24 text-center bg-slate-200/50">Vjetore</th>
                            <th rowspan="2" class="header-cell px-4 text-center">Vërejtje</th>
                        </tr>
                        <tr>
                            <th class="header-cell border-r py-2 w-20 text-center">Detyrë</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Aktiv.</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-20 text-center bg-blue-100/50">Nota I</th>
                            
                            <th class="header-cell border-r py-2 w-20 text-center">Detyrë</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Aktiv.</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-20 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-20 text-center bg-indigo-100/50">Nota II</th>
                        </tr>
                    </thead>
                    <tbody id="journalBody">
                        <?php $nr = 1; foreach ($students as $row): ?>
                        <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                            <td class="text-center text-xs font-bold text-slate-400 border-r bg-slate-50/50"><?= $nr++ ?></td>
                            <td class="px-6 border-r font-bold text-slate-800 text-sm italic uppercase"><?= htmlspecialchars($row['name']) ?></td>
                            
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="homework" value="<?= $row['p1_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="activity" value="<?= $row['p1_activity'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="project" value="<?= $row['p1_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="test" value="<?= $row['p1_test'] ?>"></td>
                            <td class="border-r p-0 bg-blue-50/30"><input type="text" class="grade-input p1-final text-blue-700" value="<?= $row['p1_final'] ?>" readonly></td>

                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="homework" value="<?= $row['p2_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="activity" value="<?= $row['p2_activity'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="project" value="<?= $row['p2_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="test" value="<?= $row['p2_test'] ?>"></td>
                            <td class="border-r p-0 bg-indigo-50/30"><input type="text" class="grade-input p2-final text-indigo-700" value="<?= $row['p2_final'] ?>" readonly></td>

                            <td class="border-r p-0 bg-slate-200/30 text-center font-black yearly-avg text-slate-900"></td>
                            
                            <td class="px-2 relative">
                                <input type="text" class="auto-save-comment w-full h-10 bg-transparent border-none text-[11px] italic focus:outline-none" data-student-id="<?= $row['student_id'] ?>" placeholder="..." value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
                                <div class="save-indicator absolute right-0 top-0 w-1 h-full bg-blue-600 opacity-0 transition-opacity"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Formula e vlerësimit për Kosovë (Pesha mund të rregullohet këtu)
function calculateRow(row) {
    const studentId = row.querySelector('.grade-input').dataset.studentId;
    ['p1', 'p2'].forEach(p => {
        const h = parseFloat(row.querySelector(`[data-period="${p}"][data-field="homework"]`).value) || 0;
        const a = parseFloat(row.querySelector(`[data-period="${p}"][data-field="activity"]`).value) || 0;
        const pr = parseFloat(row.querySelector(`[data-period="${p}"][data-field="project"]`).value) || 0;
        const t = parseFloat(row.querySelector(`[data-period="${p}"][data-field="test"]`).value) || 0;
        const finalInput = row.querySelector(`.${p}-final`);

        // Nëse janë plotësuar të gjitha kolonat, llogarit mesataren e rrumbullakuar
        if (h > 0 && a > 0 && pr > 0 && t > 0) {
            const avg = Math.round((h + a + pr + t) / 4);
            if (finalInput.value != avg) {
                finalInput.value = avg;
                saveData(studentId, `${p}_final`, avg);
            }
        } else { finalInput.value = ''; }
    });

    // Llogaritja Vjetore
    const f1 = parseFloat(row.querySelector('.p1-final').value) || 0;
    const f2 = parseFloat(row.querySelector('.p2-final').value) || 0;
    const yearlyCell = row.querySelector('.yearly-avg');
    yearlyCell.innerText = (f1 > 0 && f2 > 0) ? Math.round((f1 + f2) / 2) : '';
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
            setTimeout(() => { indicator.style.opacity = '0'; }, 600);
        }
    });
}

document.getElementById('journalBody').addEventListener('input', (e) => {
    if (e.target.classList.contains('grade-input')) {
        let val = parseInt(e.target.value);
        if (val < 1) e.target.value = ''; 
        if (val > 5) e.target.value = 5; // Limiti maksimal 5
        
        saveData(e.target.dataset.studentId, `${e.target.dataset.period}_${e.target.dataset.field}`, e.target.value);
        calculateRow(e.target.closest('tr'));
    }
});

document.getElementById('journalBody').addEventListener('blur', (e) => {
    if (e.target.classList.contains('auto-save-comment')) {
        saveData(e.target.dataset.studentId, 'comment', e.target.value);
    }
}, true);

// Llogaritja fillestare
document.querySelectorAll('#journalBody tr').forEach(row => calculateRow(row));

// Navigimi me shigjeta
document.addEventListener('keydown', (e) => {
    const active = document.activeElement;
    if (!active.classList.contains('grade-input')) return;
    const row = active.closest('tr');
    const cell = active.closest('td');
    const colIdx = cell.cellIndex;

    if (e.key === 'ArrowDown') row.nextElementSibling?.cells[colIdx].querySelector('input')?.focus();
    if (e.key === 'ArrowUp') row.previousElementSibling?.cells[colIdx].querySelector('input')?.focus();
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>