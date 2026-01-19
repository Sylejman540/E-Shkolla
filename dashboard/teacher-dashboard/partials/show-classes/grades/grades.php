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

    try {
        $tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $tStmt->execute([$userId]);
        $realTeacherId = $tStmt->fetchColumn();

        if (isset($_POST['grade'])) {
            $grade = (int)$_POST['grade'];
            // Përdorim VALUES(column) për të marrë vlerën e re në rast dublikimi
            $stmt = $pdo->prepare("
                INSERT INTO grades (school_id, teacher_id, student_id, class_id, subject_id, grade)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    grade = VALUES(grade), 
                    teacher_id = VALUES(teacher_id), 
                    updated_at = NOW()
            ");
            $stmt->execute([$schoolId, $realTeacherId, $studentId, $classId, $subjectId, $grade]);
        } 
        
        if (isset($_POST['comment'])) {
            $comment = trim($_POST['comment']);
            $stmt = $pdo->prepare("
                INSERT INTO grades (school_id, teacher_id, student_id, class_id, subject_id, comment)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    comment = VALUES(comment), 
                    teacher_id = VALUES(teacher_id), 
                    updated_at = NOW()
            ");
            $stmt->execute([$schoolId, $realTeacherId, $studentId, $classId, $subjectId, $comment]);
        }

        echo "success";
    } catch (Exception $e) {
        http_response_code(500);
        echo "error";
    }
    exit;
}

// --- 2. DATA FETCHING (Shtuar class_id në JOIN për saktësi) ---
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.email, g.grade, g.comment 
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

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Regjistrimi i Notave</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Vlerësoni nxënësit. Të dhënat ruhen automatikisht.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-medium text-slate-400 bg-slate-100 dark:bg-white/5 px-3 py-2 rounded-xl">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            Live Save Aktiv
        </div>
    </div>

    <div class="mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-gray-900 p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="relative w-full max-w-xs">
            <input id="liveSearch" type="text" placeholder="Kërko nxënësin..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition dark:text-white">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
        
        <div class="flex items-center gap-2 text-sm text-slate-500">
            <span>Rreshta:</span>
            <select id="rowsPerPage" class="bg-slate-100 dark:bg-gray-800 border-none rounded-lg py-1 px-2 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase text-slate-500">Nxënësi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-center">Nota (1-5)</th>
                        <th class="w-[40%] px-6 py-4 text-xs font-bold uppercase text-slate-500">Koment/Vlerësimi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-right pr-10">Statusi</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody" class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($students as $row): ?>
                    <tr class="student-row group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap overflow-hidden">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xs">
                                    <?= strtoupper(substr($row['name'] ?? 'N', 0, 2)) ?>
                                </div>
                                <div class="flex flex-col truncate">
                                    <span class="student-name text-sm font-semibold text-slate-900 dark:text-white truncate" data-original="<?= htmlspecialchars($row['name']) ?>">
                                        <?= htmlspecialchars($row['name']) ?>
                                    </span>
                                    <span class="text-xs text-slate-400 truncate"><?= htmlspecialchars($row['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="number" class="auto-save-grade w-16 text-center bg-slate-100 dark:bg-gray-800 border-none rounded-xl py-2 font-bold text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500 outline-none transition-all" data-student-id="<?= $row['student_id'] ?>" min="1" max="5" value="<?= $row['grade'] ?>">
                        </td>
                        <td class="px-6 py-4">
                            <input type="text" class="auto-save-comment w-full bg-transparent border-b border-transparent hover:border-slate-200 dark:hover:border-white/10 focus:border-indigo-500 outline-none text-sm py-2 transition-all text-slate-600 dark:text-slate-300 italic" data-student-id="<?= $row['student_id'] ?>" placeholder="Shto një vlerësim..." value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
                        </td>
                        <td class="px-6 py-4 text-right pr-10">
                            <div class="save-indicator opacity-0 transition-all duration-300 transform translate-x-2" data-student-id="<?= $row['student_id'] ?>">
                                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-500/20 px-2.5 py-1 rounded-full uppercase tracking-widest">Rujtur</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-slate-50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-between">
            <p class="text-xs text-slate-500" id="paginationInfo"></p>
            <div class="flex gap-2">
                <button id="prevPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30">
                    <svg class="w-4 h-4 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="nextPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30">
                    <svg class="w-4 h-4 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let rowsPerPage = 10;
let filteredRows = [];

const tableBody = document.getElementById('studentTableBody');
const allRows = Array.from(tableBody.querySelectorAll('.student-row'));

function updatePagination() {
    const searchVal = document.getElementById('liveSearch').value.toLowerCase().trim();
    
    filteredRows = allRows.filter(row => {
        const nameEl = row.querySelector('.student-name');
        const nameText = nameEl.getAttribute('data-original');
        const isMatch = nameText.toLowerCase().includes(searchVal);
        
        if (searchVal && isMatch) {
            const regex = new RegExp(`(${searchVal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, "gi");
            nameEl.innerHTML = nameText.replace(regex, `<mark class="bg-indigo-100 text-indigo-700 rounded px-0.5">$1</mark>`);
        } else {
            nameEl.innerHTML = nameText;
        }
        return isMatch;
    });

    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    if (currentPage > totalPages) currentPage = totalPages || 1;

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    allRows.forEach(row => row.style.display = 'none');
    filteredRows.slice(start, end).forEach(row => row.style.display = '');

    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
    document.getElementById('paginationInfo').innerText = `Shfaqur ${totalRows > 0 ? start + 1 : 0} - ${Math.min(end, totalRows)} nga ${totalRows}`;
}

function saveData(studentId, field, value) {
    const indicator = document.querySelector(`.save-indicator[data-student-id="${studentId}"]`);
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append(field, value);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === "success") {
            indicator.classList.remove('opacity-0', 'translate-x-2');
            indicator.classList.add('opacity-100', 'translate-x-0');
            setTimeout(() => { 
                indicator.classList.add('opacity-0', 'translate-x-2');
                indicator.classList.remove('opacity-100', 'translate-x-0');
            }, 1500);
        }
    });
}

document.getElementById('liveSearch').addEventListener('input', () => { currentPage = 1; updatePagination(); });
document.getElementById('rowsPerPage').addEventListener('change', (e) => { rowsPerPage = parseInt(e.target.value); currentPage = 1; updatePagination(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; updatePagination(); } });
document.getElementById('nextPage').addEventListener('click', () => { if (currentPage < Math.ceil(filteredRows.length / rowsPerPage)) { currentPage++; updatePagination(); } });

tableBody.addEventListener('change', function(e) {
    if (e.target.classList.contains('auto-save-grade')) {
        let val = parseInt(e.target.value);
        if (val < 1) e.target.value = 1;
        if (val > 5) e.target.value = 5;
        saveData(e.target.dataset.studentId, 'grade', e.target.value);
    }
});

tableBody.addEventListener('blur', function(e) {
    if (e.target.classList.contains('auto-save-comment')) {
        saveData(e.target.dataset.studentId, 'comment', e.target.value);
    }
}, true);

updatePagination();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>