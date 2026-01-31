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

/* FETCH DATA */
$classesStmt  = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id=? ORDER BY grade ASC");

// Updated to match your 'users' table structure
$teachersStmt = $pdo->prepare("
    SELECT id, name 
    FROM users 
    WHERE school_id = ? AND role = 'teacher' 
    ORDER BY name ASC
");

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
            <a href="/E-Shkolla/schedule-csv" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v7m0 0l-3-3m3 3l3-3M12 3v9"/></svg>
                Import CSV
            </a>
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
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Shto në orar</h3>
        </div>
        <form method="POST" action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/form.php"
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
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="subject_id" id="subjectSelect" required
                class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" disabled selected>Lënda</option>
            </select>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold py-3 text-sm transition-all active:scale-95 shadow-lg sm:col-span-2 lg:col-span-1">
                Ruaj Orarin
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto scroll-smooth">
            <table class="w-full border-collapse table-fixed min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase w-20 text-center sticky left-0 bg-slate-50 z-20 border-r shadow-sm">Ora</th>
                        <?php foreach ($days as $d): ?>
                            <th class="p-4 text-[11px] md:text-xs font-bold text-slate-600 uppercase tracking-wider border-l border-slate-100"><?= $d ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($periods as $pKey=>$pLabel): ?>
                    <tr>
                        <td class="p-4 text-center font-bold text-slate-400 text-xs sticky left-0 bg-white z-10 border-r shadow-sm"><?= $pKey ?></td>
                        <?php foreach ($days as $dayKey=>$dayLabel): ?>
                            <td class="p-2 border-l border-slate-100 min-h-[100px] h-28 align-top" data-day="<?= $dayKey ?>" data-period="<?= $pKey ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-5 right-5 z-[110] flex flex-col gap-2"></div>

    <script>
    // 1. Load the Grid
    fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=<?= $classId ?>`)
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

    const teacherSelect = document.getElementById('teacherSelect');
    const subjectSelect = document.getElementById('subjectSelect');

    if (teacherSelect && subjectSelect) {
        teacherSelect.addEventListener('change', function() {
            const teacherId = this.value;
            if (!teacherId) return;
                            
            subjectSelect.innerHTML = '<option value="">Duke ngarkuar...</option>';
            subjectSelect.classList.remove('border-emerald-400', 'bg-emerald-50', 'border-2');

            fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-teacher-subjects.php?teacher_id=70&class_id=26`)
                .then(response => response.json())
                .then(subjects => {
                    subjectSelect.innerHTML = '<option value="" disabled selected>Lënda</option>';

                    if (!Array.isArray(subjects) || subjects.length === 0) {
                        subjectSelect.innerHTML = '<option value="" disabled>Asnjë lëndë e caktuar</option>';
                        return;
                    }

                    subjects.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.subject_name; 
                        subjectSelect.appendChild(opt);
                    });

                    if (subjects.length === 1) {
                        subjectSelect.value = subjects[0].id;
                        subjectSelect.classList.add('border-emerald-400', 'bg-emerald-50', 'border-2');
                    }
                })
                .catch(err => {
                    console.error('Fetch Error:', err);
                    subjectSelect.innerHTML = '<option value="" disabled>Gabim gjatë ngarkimit</option>';
                });
        });
    }

    function deleteEntry(id) {
        if (!confirm('A jeni të sigurt?')) return;

        fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/delete-entry.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Shtohet ?success=1 në URL dhe rifreskohet faqja
                const url = new URL(window.location.href);
                url.searchParams.set('success', '1');
                window.location.href = url.toString();
            } else {
                showToast(res.error, 'error');
            }
        });
    }

    // 1. Funksioni universal për Toast (si te prindërit)
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const isSuccess = type === 'success';
        
        toast.className = `${isSuccess ? 'bg-emerald-600' : 'bg-rose-600'} text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-medium transform transition-all duration-300 translate-y-10 opacity-0`;
        
        toast.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${isSuccess ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'}
            </svg>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // 2. Kontrolli i URL-së për mesazhet pas redirect (si te prindërit)
    function checkUrlMessages() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('success') === '1') {
            showToast('Orari u përditësua me sukses!', 'success');
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]success=1/, ''));
        } else if (params.get('error') === '1') {
            showToast('Ndodhi një gabim gjatë ruajtjes!', 'error');
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]error=1/, ''));
        }
    }

    // Run kur ngarkohet faqja
    document.addEventListener('DOMContentLoaded', checkUrlMessages);

    </script>

    <?php else: ?>
        <div class="flex flex-col items-center justify-center bg-white p-20 rounded-3xl border-2 border-dashed border-slate-200">
            <h2 class="text-lg font-bold text-slate-700">Asnjë klasë e zgjedhur</h2>
            <p class="text-xs text-slate-400 text-center max-w-xs">Zgjidhni një klasë për të parë orarin.</p>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';
?>