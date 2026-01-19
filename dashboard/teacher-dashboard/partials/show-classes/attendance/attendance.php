<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['id'] ?? 0); 
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

// --- 1. AJAX LOGIC (SAVE & RESET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $studentId = (int) $_POST['student_id'];
    
    if ($_POST['action'] === 'save') {
        $status = $_POST['status']; 
        $present = ($status === 'present') ? 1 : 0;
        $missing = ($status === 'missing') ? 1 : 0;
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, present, missing) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $present, $missing]);
            echo json_encode(['status' => 'success', 'type' => $status]); exit;
        } catch (Exception $e) { echo json_encode(['status' => 'error']); exit; }
    }

    if ($_POST['action'] === 'reset') {
        try {
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ? AND class_id = ? AND subject_id = ? AND created_at >= NOW() - INTERVAL 6 HOUR");
            $stmt->execute([$studentId, $classId, $subjectId]);
            echo json_encode(['status' => 'success']); exit;
        } catch (Exception $e) { echo json_encode(['status' => 'error']); exit; }
    }
}

// --- 2. DATA FETCHING ---
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.email, s.status, a.present AS is_present, a.missing AS is_missing
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    LEFT JOIN attendance a ON a.student_id = s.student_id 
        AND a.class_id = ? AND a.subject_id = ? AND a.created_at >= NOW() - INTERVAL 6 HOUR
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$classId, $subjectId, $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Regjistrimi i Prezencës</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Shfaqen nxënësit e klasës suaj.</p>
        </div>
        <div id="global-spinner" class="hidden animate-spin h-5 w-5 text-indigo-600 border-2 border-current border-t-transparent rounded-full"></div>
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
            <span>Rreshta për faqe:</span>
            <select id="rowsPerPage" class="bg-slate-100 dark:bg-gray-800 border-none rounded-lg py-1 px-2 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase text-slate-500">Nxënësi</th>
                        <th class="w-[35%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-center">Veprimi</th>
                        <th class="w-[35%] px-6 py-4 text-xs font-bold uppercase text-slate-500 text-right pr-10">Statusi Aktual</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody" class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($students as $row): 
                        $hasStatus = ($row['is_present'] !== null);
                        $isP = $row['is_present'] == 1;
                    ?>
                    <tr class="student-row group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4">
                            <span class="student-name text-sm font-semibold text-slate-900 dark:text-white" data-original="<?= htmlspecialchars($row['name']) ?>">
                                <?= htmlspecialchars($row['name']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="markAttendance(<?= $row['student_id'] ?>, 'present')" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition active:scale-95">Prezent</button>
                                <button onclick="markAttendance(<?= $row['student_id'] ?>, 'missing')" class="px-4 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 text-xs font-bold rounded-xl transition active:scale-95">Mungon</button>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right pr-10">
                            <div class="save-indicator flex items-center justify-end gap-3 transition-all duration-300 <?= $hasStatus ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-2' ?>" data-student-id="<?= $row['student_id'] ?>">
                                <button onclick="resetAttendance(<?= $row['student_id'] ?>)" class="p-1.5 text-slate-400 hover:text-rose-500 transition-colors" title="Anulo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                <span class="indicator-text text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest <?= $hasStatus ? ($isP ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700') : '' ?>">
                                    <?= $hasStatus ? ($isP ? 'Prezent' : 'Mungon') : '' ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 bg-slate-50 dark:bg-white/5 border-t border-slate-200 dark:border-white/10 flex items-center justify-between">
            <p class="text-xs text-slate-500" id="paginationInfo">Duke shfaqur 0 deri në 0 nga 0 nxënës</p>
            <div class="flex gap-2">
                <button id="prevPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="nextPage" class="p-2 rounded-lg border border-slate-200 dark:border-white/10 hover:bg-white dark:hover:bg-gray-800 transition disabled:opacity-30 disabled:cursor-not-allowed">
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

const tableBody = document.getElementById('attendanceTableBody');
const allRows = Array.from(tableBody.querySelectorAll('.student-row'));

function updatePagination() {
    const searchVal = document.getElementById('liveSearch').value.toLowerCase().trim();
    
    // 1. Filter rows based on search
    filteredRows = allRows.filter(row => {
        const nameEl = row.querySelector('.student-name');
        const nameText = nameEl.getAttribute('data-original');
        const isMatch = nameText.toLowerCase().includes(searchVal);
        
        // Highlight logic
        if (searchVal && isMatch) {
            const regex = new RegExp(`(${searchVal})`, "gi");
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

    // 2. Hide all, show only current page slice
    allRows.forEach(row => row.style.display = 'none');
    filteredRows.slice(start, end).forEach(row => row.style.display = '');

    // 3. Update Controls
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
    document.getElementById('paginationInfo').innerText = `Duke shfaqur ${totalRows > 0 ? start + 1 : 0} deri në ${Math.min(end, totalRows)} nga ${totalRows} nxënës`;
}

// Event Listeners
document.getElementById('liveSearch').addEventListener('input', () => { currentPage = 1; updatePagination(); });
document.getElementById('rowsPerPage').addEventListener('change', (e) => { rowsPerPage = parseInt(e.target.value); currentPage = 1; updatePagination(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; updatePagination(); } });
document.getElementById('nextPage').addEventListener('click', () => { if (currentPage < Math.ceil(filteredRows.length / rowsPerPage)) { currentPage++; updatePagination(); } });

// Attendance Functions
function markAttendance(id, status) {
    const indicator = document.querySelector(`.save-indicator[data-student-id="${id}"]`);
    const indicatorText = indicator.querySelector('.indicator-text');
    document.getElementById('global-spinner').classList.remove('hidden');

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=save&student_id=${id}&status=${status}`
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('global-spinner').classList.add('hidden');
        if(data.status === 'success') {
            indicatorText.className = `indicator-text text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest ${status === 'present' ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700'}`;
            indicatorText.innerText = status === 'present' ? 'Prezent' : 'Mungon';
            indicator.classList.remove('opacity-0', 'translate-x-2');
            indicator.classList.add('opacity-100', 'translate-x-0');
        }
    });
}

function resetAttendance(id) {
    const indicator = document.querySelector(`.save-indicator[data-student-id="${id}"]`);
    document.getElementById('global-spinner').classList.remove('hidden');

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=reset&student_id=${id}`
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('global-spinner').classList.add('hidden');
        if(data.status === 'success') {
            indicator.classList.add('opacity-0', 'translate-x-2');
            indicator.classList.remove('opacity-100', 'translate-x-0');
        }
    });
}

// Initial Load
updatePagination();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>