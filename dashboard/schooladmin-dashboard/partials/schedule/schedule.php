<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

/* AUTH */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    header('Location: /login.php');
    exit;
}

$schoolId = (int)$_SESSION['user']['school_id'];
$classId  = (int)($_GET['class_id'] ?? 0);

/* FETCH DATA - KORRIGJIMI KËTU */
// Duhet të bëjmë JOIN me tabelën teachers për të marrë ID-në e saktë të mësuesit
$teachersStmt = $pdo->prepare("
    SELECT t.id AS teacher_actual_id, u.name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE u.school_id = ? AND u.role = 'teacher'
    ORDER BY u.name ASC
");

$classesStmt  = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id=? ORDER BY grade ASC");

$classesStmt->execute([$schoolId]);
$teachersStmt->execute([$schoolId]);

$classes  = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

$days = ['monday' => 'E Hënë', 'tuesday' => 'E Martë', 'wednesday' => 'E Mërkurë', 'thursday' => 'E Enjte', 'friday' => 'E Premte'];
$periods = [1=>'Ora 1', 2=>'Ora 2', 3=>'Ora 3', 4=>'Ora 4', 5=>'Ora 5', 6=>'Ora 6', 7=>'Ora 7'];

ob_start();
?>

<div class="p-2 md:p-8 bg-slate-50 min-h-screen font-sans antialiased text-slate-900">

    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 bg-white p-4 md:p-6 rounded-2xl shadow-sm border border-slate-200 gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2">
                📅 <span class="truncate">Menaxhimi i Orarit</span>
            </h1>
            <p class="text-xs md:text-sm text-slate-500 font-medium">Organizoni planin mësimor javor</p>
        </div>

        <form method="GET" class="w-full lg:w-auto">
            <select name="class_id" onchange="this.form.submit()" 
                class="w-full lg:w-64 p-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-semibold text-slate-700 text-sm appearance-none cursor-pointer">
                <option value="">Zgjedh klasën…</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
                        Klasa <?= htmlspecialchars($c['grade']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($classId): ?>

    <div class="bg-white p-4 md:p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <form id="scheduleForm" method="POST" action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/form.php"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            
            <input type="hidden" name="school_id" value="<?= $schoolId ?>">
            <input type="hidden" name="class_id" value="<?= $classId ?>">

            <select name="day" required class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" disabled selected>Dita</option>
                <?php foreach ($days as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>

            <select name="period_number" required class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" disabled selected>Ora</option>
                <?php foreach ($periods as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>

            <select name="teacher_id" id="teacherSelect" required
                class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-indigo-600">
                <option value="" disabled selected>Zgjidh Mësimdhënësin</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['teacher_actual_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="subject_id" id="subjectSelect" required
                class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" disabled selected>Lënda</option>
            </select>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold py-3 text-sm transition-all shadow-lg">
                Ruaj Orarin
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse table-fixed min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase w-20 text-center sticky left-0 bg-slate-50 z-20 border-r shadow-sm">Ora</th>
                        <?php foreach ($days as $d): ?>
                            <th class="p-4 text-[11px] md:text-xs font-bold text-slate-600 uppercase border-l border-slate-100"><?= $d ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="scheduleGridBody" class="divide-y divide-slate-100">
                <?php foreach ($periods as $pKey=>$pLabel): ?>
                    <tr>
                        <td class="p-4 text-center font-bold text-slate-400 text-xs sticky left-0 bg-white z-10 border-r shadow-sm"><?= $pKey ?></td>
                        <?php foreach ($days as $dayKey=>$dayLabel): ?>
                            <td class="p-2 border-l border-slate-100 h-28 align-top" data-day="<?= $dayKey ?>" data-period="<?= $pKey ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

    <script>
    const urlParams = new URLSearchParams(window.location.search);
    const classIdFromUrl = urlParams.get('class_id');

    // Load Grid
    if (classIdFromUrl) {
        fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=${classIdFromUrl}`)
            .then(r => r.json())
            .then(data => {
                if (!data.grid) return;
                Object.values(data.grid).forEach(day => {
                    Object.values(day).forEach(e => {
                        const cell = document.querySelector(`[data-day="${e.day}"][data-period="${e.period_number}"]`);
                        if (cell) {
                            cell.innerHTML = `
                                <div class="group relative bg-indigo-50 border border-indigo-100 p-2 rounded-xl h-full flex flex-col justify-center transition-all hover:bg-indigo-100 shadow-sm">
                                    <strong class="text-indigo-900 text-[10px] md:text-xs block leading-tight mb-1 truncate">${e.subject_name}</strong>
                                    <span class="text-[9px] font-medium text-indigo-500 truncate opacity-80 leading-none">👤 ${e.teacher_name}</span>
                                    <button onclick="deleteEntry(${e.id})" class="absolute -top-1 -right-1 bg-white border border-red-100 text-red-500 rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>`;
                        }
                    });
                });
            });
    }

    // Auto-Selection Logic
    const teacherSelect = document.getElementById('teacherSelect');
    const subjectSelect = document.getElementById('subjectSelect');

    if (teacherSelect && subjectSelect) {
        teacherSelect.addEventListener('change', function() {
            const teacherId = this.value; // TANI DO TË JETË 70
            
            console.log("Teacher ID dërguar:", teacherId, "Class ID:", classIdFromUrl);

            subjectSelect.innerHTML = '<option value="">Duke ngarkuar...</option>';
            subjectSelect.classList.remove('border-emerald-500', 'bg-emerald-50', 'ring-2', 'ring-emerald-200');

            if (!teacherId || !classIdFromUrl) return;

            fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-teacher-subjects.php?teacher_id=${teacherId}&class_id=${classIdFromUrl}`)
                .then(r => r.json())
                .then(subjects => {
                    subjectSelect.innerHTML = '';
                    if (subjects.length === 0) {
                        subjectSelect.innerHTML = '<option value="" disabled selected>Asnjë lëndë e caktuar</option>';
                    } else {
                        if (subjects.length > 1) {
                            const placeholder = document.createElement('option');
                            placeholder.textContent = "Zgjidhni lëndën...";
                            placeholder.value = "";
                            placeholder.disabled = true;
                            placeholder.selected = true;
                            subjectSelect.appendChild(placeholder);
                        }
                        subjects.forEach(s => {
                            const opt = document.createElement('option');
                            opt.value = s.id;
                            opt.textContent = s.subject_name;
                            subjectSelect.appendChild(opt);
                        });
                        if (subjects.length === 1) {
                            subjectSelect.value = subjects[0].id;
                            subjectSelect.classList.add('border-emerald-500', 'bg-emerald-50', 'ring-2', 'ring-emerald-200');
                        }
                    }
                });
        });
    }

    function deleteEntry(id) {
        if (!confirm('A jeni të sigurt?')) return;
        fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/delete-entry.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(() => window.location.reload());
    }
    </script>

    <?php else: ?>
        <div class="flex flex-col items-center justify-center bg-white p-20 rounded-3xl border-2 border-dashed border-slate-200">
            <h2 class="text-lg font-bold text-slate-700">Asnjë klasë e zgjedhur</h2>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>