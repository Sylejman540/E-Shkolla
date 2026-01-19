<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$teacherId = (int) ($_SESSION['user']['id'] ?? 0); 
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

// --- 1. AJAX SAVE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_attendance'])) {
    $studentId = (int) $_POST['student_id'];
    $status    = $_POST['status']; 

    $present = ($status === 'present') ? 1 : 0;
    $missing = ($status === 'missing') ? 1 : 0;

    try {
        // LOCK CHECK: Only allow one entry per hour to prevent accidental spam
        $lockStmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND class_id = ? AND subject_id = ? AND teacher_id = ? 
            AND created_at >= NOW() - INTERVAL 1 HOUR LIMIT 1
        ");
        $lockStmt->execute([$studentId, $classId, $subjectId, $teacherId]);

        if ($lockStmt->fetch()) {
            echo json_encode(['status' => 'locked', 'message' => 'Regjistruar së fundmi.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, present, missing)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $present, $missing]);

        echo json_encode(['status' => 'success', 'msg_type' => $status]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error']);
        exit;
    }
}

// --- 2. DATA FETCHING (Modified to include current status) ---
// We fetch the student list AND join with the attendance table for the last 6 hours
$stmt = $pdo->prepare("
    SELECT 
        s.student_id, s.name, s.email, s.status,
        a.present AS is_present,
        a.missing AS is_missing,
        a.created_at AS last_marked
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    LEFT JOIN attendance a ON a.student_id = s.student_id 
        AND a.class_id = ? 
        AND a.subject_id = ?
        AND a.created_at >= NOW() - INTERVAL 6 HOUR
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
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Statusi ruhet për 6 orë pas regjistrimit.</p>
        </div>
        <div class="flex items-center gap-3">
            <div id="global-spinner" class="hidden animate-spin h-5 w-5 text-indigo-600 border-2 border-current border-t-transparent rounded-full"></div>
            <div class="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-white/5 px-4 py-2 rounded-xl border border-slate-200 dark:border-white/10">
                <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>

    <div class="mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full max-w-xs">
            <input id="liveSearch" type="text" placeholder="Kërko nxënësin..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-white dark:bg-gray-900 text-sm shadow-sm ring-1 ring-slate-200 dark:ring-white/10 focus:ring-2 focus:ring-indigo-500 outline-none transition dark:text-white">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
        <div class="flex gap-2">
             <div class="px-4 py-2 bg-blue-50 dark:bg-blue-500/10 rounded-xl border border-blue-100 dark:border-blue-500/20">
                <p class="text-[10px] uppercase tracking-wider font-bold text-blue-600 dark:text-blue-400">Nxënës</p>
                <p class="text-lg font-bold text-blue-700 dark:text-blue-300"><?= count($students) ?></p>
             </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[800px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[35%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nxënësi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Llogaria</th>
                        <th class="w-[35%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Veprimi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right pr-10">Statusi Aktual</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody" class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($students as $row): 
                        // Check if student was already marked in this 6hr window
                        $hasStatus = ($row['is_present'] !== null || $row['is_missing'] !== null);
                        $currentStatusText = '';
                        $statusClass = 'opacity-0 translate-x-2';
                        $badgeClass = '';

                        if ($hasStatus) {
                            $statusClass = 'opacity-100 translate-x-0';
                            $currentStatusText = $row['is_present'] ? 'Prezent' : 'Mungon';
                            $badgeClass = $row['is_present'] ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700';
                        }
                    ?>
                    <tr class="group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap overflow-hidden">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-600 dark:text-slate-400 font-bold text-xs ring-2 ring-white dark:ring-gray-800 shadow-sm">
                                    <?= strtoupper(substr($row['name'], 0, 2)) ?>
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
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?= $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="markAttendance(<?= $row['student_id'] ?>, 'present')" 
                                    class="attendance-btn flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                                    Prezent
                                </button>
                                <button onclick="markAttendance(<?= $row['student_id'] ?>, 'missing')" 
                                    class="attendance-btn flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-xs font-bold rounded-xl transition-all active:scale-95">
                                    Mungon
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right pr-10">
                            <div class="save-indicator transition-all duration-500 <?= $statusClass ?>" data-student-id="<?= $row['student_id'] ?>">
                                <span class="indicator-text text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest <?= $badgeClass ?>">
                                    <?= $currentStatusText ?: 'U Ruajt' ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    let bgColor = type === 'success' ? 'bg-emerald-600' : (type === 'warning' ? 'bg-amber-500' : 'bg-rose-600');

    toast.className = `${bgColor} text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-medium transform transition-all duration-300 translate-y-10 opacity-0`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function markAttendance(studentId, status) {
    const spinner = document.getElementById('global-spinner');
    const indicator = document.querySelector(`.save-indicator[data-student-id="${studentId}"]`);
    const indicatorText = indicator.querySelector('.indicator-text');
    
    spinner.classList.remove('hidden');

    const formData = new FormData();
    formData.append('ajax_attendance', '1');
    formData.append('student_id', studentId);
    formData.append('status', status);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        spinner.classList.add('hidden');
        if(data.status === 'success') {
            // Permanent visibility for this session
            indicatorText.className = `indicator-text text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest ${status === 'present' ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700'}`;
            indicatorText.innerText = status === 'present' ? 'Prezent' : 'Mungon';
            
            indicator.classList.remove('opacity-0', 'translate-x-2');
            indicator.classList.add('opacity-100', 'translate-x-0');
            
            showToast(status === 'present' ? 'Nxënësi u shënua prezent' : 'Nxënësi u shënua mungon');
        } else if(data.status === 'locked') {
            showToast(data.message, 'warning');
        }
    })
    .catch(() => {
        spinner.classList.add('hidden');
        showToast('Gabim në rrjet!', 'error');
    });
}

// Live Search logic... (kept the same as previous)
document.getElementById('liveSearch').addEventListener('input', function() {
    let filter = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll('#attendanceTableBody tr');
    rows.forEach(row => {
        let nameElement = row.querySelector('.student-name');
        let nameText = nameElement.getAttribute('data-original');
        if (nameText.toLowerCase().includes(filter)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>