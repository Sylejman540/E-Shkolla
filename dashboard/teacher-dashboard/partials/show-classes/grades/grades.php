<?php
declare(strict_types=1);

/* =====================================================
    SESSION & SECURITY
===================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../../../db.php';
require_once __DIR__ . '/../../../../../helpers/GradeMailer.php';

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'teacher') {
    http_response_code(403);
    exit('Unauthorized');
}

/* =====================================================
    CONTEXT
===================================================== */
$schoolId  = (int) ($user['school_id'] ?? 0);
$userId    = (int) ($user['id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

if (!$schoolId || !$userId || !$classId || !$subjectId) {
    http_response_code(400);
    exit('Missing parameters');
}

/* =====================================================
    GET TEACHER ID
===================================================== */
$stmt = $pdo->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ? AND school_id = ?
    LIMIT 1
");
$stmt->execute([$userId, $schoolId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) {
    http_response_code(403);
    exit('Invalid teacher');
}

/* =====================================================
    VERIFY TEACHER OWNS CLASS
===================================================== */
$stmt = $pdo->prepare("
    SELECT 1 
    FROM teacher_class
    WHERE teacher_id = ? AND class_id = ?
    LIMIT 1
");
$stmt->execute([$teacherId, $classId]);

if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('No access to this class');
}

/* =====================================================
    AJAX SAVE (INLINE GRADING)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $column    = $_POST['column'] ?? '';
    $value     = $_POST['value'] ?? '';

    $allowedColumns = [
        'p1_homework','p1_activity','p1_oral','p1_project','p1_test','p1_final',
        'p2_homework','p2_activity','p2_oral','p2_project','p2_test','p2_final',
        'grade','comment'
    ];

    if (!$studentId || !in_array($column, $allowedColumns, true)) {
        http_response_code(422);
        exit;
    }

    // Validate student belongs to class + school + active
    $stmt = $pdo->prepare("
        SELECT 1
        FROM student_class sc
        JOIN students s 
            ON s.student_id = sc.student_id
           AND s.status = 'active'
        WHERE sc.student_id = ?
          AND sc.class_id = ?
          AND s.school_id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId, $classId, $schoolId]);

    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit;
    }

    // Grade range validation
    if (is_numeric($value) && ($value < 1 || $value > 5)) {
        http_response_code(422);
        exit;
    }

    /* =====================================================
        UPSERT GRADE CELL
    ===================================================== */
    $stmt = $pdo->prepare("
        INSERT INTO grades
            (school_id, teacher_id, student_id, class_id, subject_id, {$column})
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            {$column} = VALUES({$column}),
            teacher_id = VALUES(teacher_id),
            updated_at = NOW()
    ");

    $stmt->execute([
        $schoolId,
        $teacherId,
        $studentId,
        $classId,
        $subjectId,
        $value
    ]);

    /* =====================================================
        AUTO FINAL + YEARLY LOGIC
    ===================================================== */
    $alreadySent = false;

    if (in_array($column, ['p1_final','p2_final'], true)) {

        $stmt = $pdo->prepare("
            SELECT p1_final, p2_final
            FROM grades
            WHERE student_id = ? AND class_id = ? AND subject_id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $classId, $subjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $p1 = (int) ($row['p1_final'] ?? 0);
        $p2 = (int) ($row['p2_final'] ?? 0);

        $final = ($p1 && $p2)
            ? round(($p1 + $p2) / 2)
            : ($p1 ?: $p2 ?: null);

        $upd = $pdo->prepare("
            UPDATE grades
            SET grade = ?
            WHERE student_id = ? AND class_id = ? AND subject_id = ?
        ");
        $upd->execute([$final, $studentId, $classId, $subjectId]);

        if ($final !== null) {
            sendGradeNotification(
                $pdo,
                $studentId,
                $classId,
                $subjectId,
                'grade',
                $final
            );
            $alreadySent = true;
        }
    }

    // Generic notification (no duplicates)
    if (!$alreadySent && $value !== '' && is_numeric($value)) {
        sendGradeNotification(
            $pdo,
            $studentId,
            $classId,
            $subjectId,
            $column,
            $value
        );
    }

    echo 'success';
    exit;
}

/* =====================================================
    FETCH ACTIVE STUDENTS + GRADES
===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        s.student_id, 
        s.name,
        g.p1_homework, g.p1_activity, g.p1_oral, g.p1_project, g.p1_test, g.p1_final,
        g.p2_homework, g.p2_activity, g.p2_oral, g.p2_project, g.p2_test, g.p2_final,
        g.grade, g.comment
    FROM student_class sc
    JOIN students s 
        ON s.student_id = sc.student_id
       AND s.status = 'active'
    LEFT JOIN grades g
        ON g.student_id = s.student_id
       AND g.class_id = ?
       AND g.subject_id = ?
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$classId, $subjectId, $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
    RENDER
===================================================== */
ob_start();
?>

<style>
    .grade-input { 
        width: 100%; height: 48px; text-align: center; border: none; 
        background: transparent; font-size: 15px; font-weight: 700;
        display: block; cursor: text; color: #1e293b; transition: all 0.1s;
    }
    .grade-input:focus { background-color: #fff; outline: 2px solid #6366f1; z-index: 10; box-shadow: inset 0 0 10px rgba(99, 102, 241, 0.1); }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .table-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1); }
    .header-cell { background-color: #f8fafc; text-transform: uppercase; font-size: 10px; font-weight: 800; color: #64748b; border-bottom: 2px solid #e2e8f0; letter-spacing: 0.05em; }
    .save-indicator { position: absolute; right: 0; top: 0; bottom: 0; width: 3px; background: #22c55e; opacity: 0; transition: opacity 0.3s; }
</style>

<div class="p-8 bg-slate-50 min-h-screen">
    <div class="max-w-[1550px] mx-auto">
        <div class="mb-6 flex justify-between items-end no-print">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Regjistri i Arritjeve</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider">Sistemi 1-5</span>
                </div>
            </div>
            <button onclick="window.print()" class="bg-white border border-slate-200 px-4 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-all flex items-center gap-2">
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
                            <th colspan="6" class="header-cell py-3 border-r border-b bg-blue-50/50 text-blue-600 text-center">Periudha I</th>
                            <th colspan="6" class="header-cell py-3 border-r border-b bg-indigo-50/50 text-indigo-600 text-center">Periudha II</th>
                            <th rowspan="2" class="header-cell px-4 border-r w-24 text-center bg-slate-100 text-slate-900">Vjetore</th>
                            <th rowspan="2" class="header-cell px-4 text-left">Shënime</th>
                        </tr>
                        <tr>
                            <th class="header-cell border-r py-2 w-14 text-center">Det.</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Akt.</th>
                            <th class="header-cell border-r py-2 w-14 text-center bg-blue-50">Me Gojë</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-16 text-center bg-blue-100 text-blue-800">Nota</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Det.</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Akt.</th>
                            <th class="header-cell border-r py-2 w-14 text-center bg-indigo-50">Me Gojë</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Proj.</th>
                            <th class="header-cell border-r py-2 w-14 text-center">Test</th>
                            <th class="header-cell border-r py-2 w-16 text-center bg-indigo-100 text-indigo-800">Nota</th>
                        </tr>
                    </thead>
                    <tbody id="journalBody">
                    <?php if (empty($students)): ?>
                        <tr><td colspan="16" class="px-6 py-12 text-center text-slate-400 italic bg-white">Nuk u gjet asnjë nxënës.</td></tr>
                    <?php else: ?>
                        <?php $nr = 1; foreach ($students as $row): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors bg-white relative">
                            <td class="text-center text-[10px] font-bold text-slate-300 border-r bg-slate-50 w-10"><?= $nr++ ?></td>
                            <td class="px-4 border-r font-bold text-slate-700 text-[13px] uppercase truncate"><?= htmlspecialchars($row['name']) ?></td>
                            
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="homework" value="<?= $row['p1_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="activity" value="<?= $row['p1_activity'] ?>"></td>
                            <td class="border-r p-0 bg-blue-50/20"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="oral" value="<?= $row['p1_oral'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="project" value="<?= $row['p1_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p1" data-field="test" value="<?= $row['p1_test'] ?>"></td>
                            <td class="border-r p-0 bg-blue-50/50"><input type="text" class="grade-input p1-final text-blue-700" value="<?= $row['p1_final'] ?>" readonly tabindex="-1"></td>

                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="homework" value="<?= $row['p2_homework'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="activity" value="<?= $row['p2_activity'] ?>"></td>
                            <td class="border-r p-0 bg-indigo-50/20"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="oral" value="<?= $row['p2_oral'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="project" value="<?= $row['p2_project'] ?>"></td>
                            <td class="border-r p-0"><input type="number" class="grade-input" data-student-id="<?= $row['student_id'] ?>" data-period="p2" data-field="test" value="<?= $row['p2_test'] ?>"></td>
                            <td class="border-r p-0 bg-indigo-50/50"><input type="text" class="grade-input p2-final text-indigo-700" value="<?= $row['p2_final'] ?>" readonly tabindex="-1"></td>

                            <td class="border-r p-0 bg-slate-100/50 text-center font-black yearly-avg text-slate-900 text-sm">
                                <?= $row['grade'] ?: '' ?>
                            </td>

                            <td class="px-3 relative">
                                <input type="text" class="auto-save-comment w-full h-8 bg-transparent border-none text-xs italic text-slate-500 focus:outline-none" 
                                    data-student-id="<?= $row['student_id'] ?>" placeholder="..." value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
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
    <div class="hidden print:flex justify-between pt-16 text-[11px] font-bold uppercase text-slate-400">
        <div class="border-t border-slate-300 pt-2 w-40 text-center">Nënshkrimi</div>
    </div>
</div>


<script>
function saveData(row, studentId, column, value) {
    const indicator = row.querySelector('.save-indicator');
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('column', column);
    formData.append('value', value);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(res => res.text())
    .then(res => {
        if (res.trim() === 'success' && indicator) {
            indicator.style.opacity = '1';
            setTimeout(() => indicator.style.opacity = '0', 700);
        }
    });
}

function calculateRow(row) {
    const studentId = row.querySelector('.grade-input').dataset.studentId;

    ['p1', 'p2'].forEach(p => {
        const h  = parseInt(row.querySelector(`[data-period="${p}"][data-field="homework"]`)?.value) || 0;
        const a  = parseInt(row.querySelector(`[data-period="${p}"][data-field="activity"]`)?.value) || 0;
        const o  = parseInt(row.querySelector(`[data-period="${p}"][data-field="oral"]`)?.value) || 0;
        const pr = parseInt(row.querySelector(`[data-period="${p}"][data-field="project"]`)?.value) || 0;
        const t  = parseInt(row.querySelector(`[data-period="${p}"][data-field="test"]`)?.value) || 0;

        const finalInput = row.querySelector(`.${p}-final`);
        const grades = [h, a, o, pr, t].filter(v => v > 0);

        if (grades.length) {
            const avg = Math.round(grades.reduce((s, v) => s + v, 0) / grades.length);
            if (finalInput.value != avg) {
                finalInput.value = avg;
                saveData(row, studentId, `${p}_final`, avg);
            }
        } else {
            if (finalInput.value !== '') {
                finalInput.value = '';
                saveData(row, studentId, `${p}_final`, '');
            }
        }
    });

    // Llogaritja dhe Ruajtja e Vjetores (Grade)
    const f1 = parseInt(row.querySelector('.p1-final').value) || 0;
    const f2 = parseInt(row.querySelector('.p2-final').value) || 0;
    const yearlyCell = row.querySelector('.yearly-avg');
    
    let yearlyAvg = 0;
    if (f1 && f2) {
        yearlyAvg = Math.round((f1 + f2) / 2);
    } else {
        yearlyAvg = f1 || f2 || 0;
    }

    const currentDisplay = parseInt(yearlyCell.innerText) || 0;
    if (yearlyAvg !== currentDisplay) {
        yearlyCell.innerText = yearlyAvg || '';
        // Ruajme automatikisht ne kolonen 'grade'
        saveData(row, studentId, 'grade', yearlyAvg || '');
    }
}

document.getElementById('journalBody').addEventListener('input', e => {
    if (!e.target.classList.contains('grade-input')) return;
    let val = parseInt(e.target.value);
    if (val < 1) e.target.value = '';
    if (val > 5) e.target.value = 5;

    const row = e.target.closest('tr');
    saveData(row, e.target.dataset.studentId, `${e.target.dataset.period}_${e.target.dataset.field}`, e.target.value);
    calculateRow(row);
});

document.getElementById('journalBody').addEventListener('blur', e => {
    if (!e.target.classList.contains('auto-save-comment')) return;
    saveData(e.target.closest('tr'), e.target.dataset.studentId, 'comment', e.target.value);
}, true);

// Initial calc
document.querySelectorAll('#journalBody tr').forEach(row => calculateRow(row));

// Arrow keys navigation
document.addEventListener('keydown', e => {
    const active = document.activeElement;
    if (!active.classList.contains('grade-input')) return;
    const row = active.closest('tr');
    const col = active.closest('td').cellIndex;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        row.nextElementSibling?.cells[col]?.querySelector('input')?.focus();
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        row.previousElementSibling?.cells[col]?.querySelector('input')?.focus();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>