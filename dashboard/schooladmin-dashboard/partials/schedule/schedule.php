<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    header('Location: /login.php'); exit;
}

$schoolId = (int)$_SESSION['user']['school_id'];
$classId = (int)($_GET['class_id'] ?? 0);

/* Fetch data for selects */
$classes  = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id=?");
$subjects = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id=?");
$teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id=?");

$classes->execute([$schoolId]);
$subjects->execute([$schoolId]);
$teachers->execute([$schoolId]);

$classes  = $classes->fetchAll(PDO::FETCH_ASSOC);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
$teachers = $teachers->fetchAll(PDO::FETCH_ASSOC);

$days = [
    'monday'=>'E Hënë','tuesday'=>'E Martë','wednesday'=>'E Mërkurë',
    'thursday'=>'E Enjte','friday'=>'E Premte'
];
$periods = [1=>'Ora 1', 2=>'Ora 2', 3=>'Ora 3', 4=>'Ora 4', 5=>'Ora 5', 6=>'Ora 6', 7=>'Ora 7'];

ob_start();
?>

<div class="p-4 antialiased text-slate-800">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Menaxhimi i Orarit</h1>
            <p class="text-slate-500 text-sm">Zgjidhni klasën për të organizuar procesin mësimor.</p>
        </div>
        
        <form method="GET" class="flex items-center gap-2">
            <select name="class_id" onchange="this.form.submit()" 
                class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block w-full md:w-64 p-3 font-semibold transition-all">
                <option value="">Zgjedh klasën...</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>>
                        Klasa <?= htmlspecialchars($c['grade']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($classId): ?>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4">Shto Orë të re</h3>
        <form method="POST" action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/form.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600 ml-1">Dita</label>
                <select name="day" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach ($days as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600 ml-1">Ora</label>
                <select name="period_number" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach ($periods as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600 ml-1">Lënda</label>
                <select name="subject_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= $s['subject_name'] ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-600 ml-1">Mësimdhënësi</label>
                <select name="teacher_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= $t['name'] ?></option><?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-indigo-200 transition-all active:scale-95">
                Ruaj Orarin
            </button>
        </form>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="w-20 p-4 text-xs font-black text-slate-400 uppercase text-center">Ora</th>
                        <?php foreach ($days as $d): ?>
                            <th class="p-4 text-sm font-bold text-slate-700 border-l border-slate-100"><?= $d ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $pKey=>$pLabel): ?>
                    <tr class="border-b border-slate-100 group hover:bg-slate-50/50 transition-colors">
                        <td class="p-4 text-center text-sm font-black text-slate-300 group-hover:text-indigo-500 transition-colors border-r border-slate-100 bg-slate-50/30">
                            <?= $pKey ?>
                        </td>
                        <?php foreach ($days as $dayKey=>$dayLabel): ?>
                            <td class="p-2 h-32 border-l border-slate-100 align-top relative" data-day="<?= $dayKey ?>" data-period="<?= $pKey ?>">
                                </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/get-schedule-grid.php?class_id=<?= $classId ?>`)
    .then(r=>r.json())
    .then(data=>{
        if(!data.grid) return;
        Object.values(data.grid).forEach(day=>{
            Object.values(day).forEach(e=>{
                const cell = document.querySelector(`[data-day="${e.day}"][data-period="${e.period_number}"]`);
                if(cell) {
                    cell.innerHTML = `
                        <div class="group/card relative bg-indigo-50 border border-indigo-100 p-3 rounded-2xl h-full flex flex-col justify-between hover:shadow-md hover:bg-indigo-100/50 transition-all animate-in fade-in zoom-in duration-300">
                            <div>
                                <div class="text-[11px] font-black text-indigo-400 uppercase tracking-tighter mb-1">Lënda</div>
                                <div class="text-sm font-bold text-indigo-900 leading-tight">${e.subject_name}</div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-indigo-200/50">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Mësimdhënësi</div>
                                <div class="text-[11px] font-bold text-indigo-700 truncate">${e.teacher_name}</div>
                            </div>
                            <button onclick="deleteEntry(${e.id})" class="absolute top-2 right-2 opacity-0 group-hover/card:opacity-100 p-1.5 bg-white text-red-500 rounded-lg shadow-sm border border-red-100 hover:bg-red-50 transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>`;
                }
            });
        });
    });

function deleteEntry(id) {
    if (!confirm('A jeni të sigurt?')) return;

    fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/delete-entry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(r => r.text()) // 👈 parse as text first
    .then(text => {
        try {
            const res = JSON.parse(text);

            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Fshirja dështoi');
            }
        } catch (e) {
            console.error('Invalid JSON:', text);
            alert('Server ktheu përgjigje të pavlefshme');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Gabim gjatë fshirjes');
    });
}


    </script>
    <?php else: ?>
    <div class="flex flex-col items-center justify-center py-24 bg-white rounded-3xl border-2 border-dashed border-slate-200">
        <div class="bg-slate-100 p-4 rounded-full mb-4">
            <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h2 class="text-xl font-bold text-slate-800">Nuk u zgjodh asnjë klasë</h2>
        <p class="text-slate-500 max-w-xs text-center mt-2">Zgjidhni një klasë nga lista më sipër për të parë orarin.</p>
    </div>
    <?php endif; ?>
</div>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>