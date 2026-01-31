<?php
/**
 * E-Shkolla — Paneli i Administratorit të Shkollës
 * Versioni: Command Center v2.0 (Analysis Integrated)
 * Filozofia: Diagnozë > Supozim | Aksion i Bazuar në të Dhëna
 */

require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =======================
   Siguria
======================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    http_response_code(403);
    exit('I paautorizuar');
}

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) die('ID e shkollës mungon');

/* =======================
   Shtresa e të Dhënave
======================= */

// Emri i shkollës
$stmt = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$schoolName = $stmt->fetchColumn() ?: 'Shkolla';

// Trendi i vijueshmërisë (7 ditë)
$stmt = $pdo->prepare("
    SELECT DATE(a.created_at) AS day,
           ROUND((SUM(a.present) / COUNT(*)) * 100) AS rate
    FROM attendance a
    JOIN students s ON s.student_id = a.student_id
    WHERE s.school_id = ?
      AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY day
    ORDER BY day ASC
");
$stmt->execute([$schoolId]);
$trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentAttendance = end($trendData)['rate'] ?? 0;
$avgAttendance = count($trendData)
    ? array_sum(array_column($trendData, 'rate')) / count($trendData)
    : 0;
$attendanceDiff = round($currentAttendance - $avgAttendance);

// Klasat nën prag sot
$stmt = $pdo->prepare("
    SELECT c.id, c.grade,
           ROUND((SUM(a.present) / COUNT(*)) * 100) AS rate
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    WHERE c.school_id = ?
      AND DATE(a.created_at) = CURDATE()
    GROUP BY c.id
    HAVING rate < 75
    ORDER BY rate ASC
");
$stmt->execute([$schoolId]);
$problemClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indeksi i rrezikut
$riskScore = 0;
if ($attendanceDiff < 0) $riskScore += abs($attendanceDiff) * 3;
$riskScore += count($problemClasses) * 12;
$riskScore = min(100, $riskScore);

// Veprimi i fundit administrativ
$stmt = $pdo->prepare("
    SELECT action_title, status, created_at
    FROM admin_logs
    WHERE school_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$schoolId]);
$lastAction = $stmt->fetch(PDO::FETCH_ASSOC);

// Notat (30 ditët e fundit)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN g.grade >= 4.5 THEN '5'
            WHEN g.grade >= 3.5 THEN '4'
            WHEN g.grade >= 2.5 THEN '3'
            WHEN g.grade >= 1.5 THEN '2'
            ELSE '1'
        END AS label,
        COUNT(*) AS total
    FROM grades g
    JOIN students s ON s.student_id = g.student_id
    WHERE s.school_id = ?
      AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY label
");
$stmt->execute([$schoolId]);
$gradeDist = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$updatedAt = date('H:i');

ob_start();
?>  

<div id="diagModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="p-6 border-b flex justify-between items-center bg-slate-50/50">
            <div>
                <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Analiza e Sistemit</h3>
                <p id="diagClassName" class="text-lg font-semibold text-slate-800">Klasa --</p>
            </div>
            <button onclick="closeDiag()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        
        <div id="diagLoading" class="p-12 text-center">
            <div class="animate-spin h-6 w-6 border-2 border-slate-800 border-t-transparent rounded-full mx-auto mb-3"></div>
            <p class="text-[11px] font-medium text-slate-400 uppercase">Duke analizuar shkaqet...</p>
        </div>

        <div id="diagContent" class="hidden p-6 space-y-6">
            <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                <p id="diagSummary" class="text-indigo-900 text-sm font-medium leading-snug"></p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] font-bold uppercase text-slate-400 mb-2 tracking-tighter">Nxënësit Kritikë</p>
                    <ul id="diagStudents" class="space-y-1 text-xs text-slate-600 font-medium"></ul>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase text-slate-400 mb-2 tracking-tighter">Lëndët/Trendi</p>
                    <ul id="diagSubjects" class="space-y-1 text-xs text-slate-600 font-medium"></ul>
                </div>
            </div>
            <div class="pt-4 flex gap-3">
                <button onclick="closeDiag()" class="flex-1 text-xs font-bold text-slate-400 uppercase">Mbyll</button>
                <button id="proceedToActionButton" class="flex-[2] bg-slate-900 text-white text-xs font-bold py-3 rounded-lg uppercase">Ndërmerr Veprim</button>
            </div>
        </div>
    </div>
</div>

<div id="actionModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl border border-slate-200">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 id="modalTitle" class="text-sm font-semibold uppercase tracking-widest">Veprim Administrativ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="p-6">
            <p id="modalDescription" class="text-sm text-slate-500 mb-4"></p>
            <textarea id="actionMessage" rows="3"
                class="w-full p-3 border rounded-lg text-sm focus:ring-2 focus:ring-slate-900 outline-none"
                placeholder="Shënim i brendshëm (opsional)…"></textarea>
        </div>
        <div class="p-6 bg-slate-50 flex gap-3">
            <button onclick="closeModal()" class="flex-1 text-xs font-bold uppercase text-slate-400">Anulo</button>
            <button id="confirmBtn" class="flex-1 bg-slate-900 text-white text-xs font-bold uppercase rounded-lg py-2">
                Konfirmo
            </button>
        </div>
    </div>
</div>

<div class="p-6 lg:p-8 bg-slate-50 min-h-screen text-slate-800 antialiased font-sans">

    <div class="mb-10 border-b border-slate-200 pb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900"><?= htmlspecialchars($schoolName) ?></h1>
        <div class="flex items-center gap-2 mt-1">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            <p class="text-[11px] font-medium text-slate-400 uppercase tracking-widest">
                Sistemi Aktiv · <?= $updatedAt ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Gjendja e Shkollës Sot</p>
            <p class="text-xl font-semibold text-slate-900">
                <?= $riskScore > 50 ? 'Kërkohet monitorim i shtuar.' : 'Operacionet janë brenda normës.' ?>
            </p>
            <p class="mt-1 text-sm text-slate-500 font-medium">
                <?= $riskScore > 50
                    ? 'Janë identifikuar anomali në vijueshmërinë e klasave specifike.'
                    : 'Vijueshmëria dhe performanca janë stabile në rang shkolle.' ?>
            </p>
        </div>
        <div class="bg-indigo-600 p-6 rounded-2xl text-white shadow-lg shadow-indigo-100">
            <p class="text-[10px] font-bold uppercase opacity-70 mb-1">Indeksi i Rrezikut</p>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-semibold leading-none"><?= $riskScore ?></p>
                <p class="text-xs font-medium opacity-70 pb-1">/100</p>
            </div>
            <div class="mt-3 w-full bg-indigo-400/30 h-1 rounded-full overflow-hidden">
                <div class="bg-white h-full" style="width: <?= $riskScore ?>%"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <div class="lg:col-span-8 space-y-6">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b bg-slate-50/50 flex justify-between items-center">
                    <p class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">Klasat nën prag (75%)</p>
                </div>

                <?php if (empty($problemClasses)): ?>
                    <div class="p-12 text-center text-slate-400 text-xs font-medium italic">
                        Nuk ka klasa me vijueshmëri kritike sot.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                    <?php foreach ($problemClasses as $c): ?>
                        <div class="p-5 flex justify-between items-center hover:bg-slate-50 transition-colors">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Klasa <?= htmlspecialchars($c['grade']) ?></p>
                                <p class="text-[11px] font-bold text-rose-500 uppercase mt-0.5">
                                    <?= $c['rate'] ?>% vijueshmëri sot
                                </p>
                            </div>
                            <button
                                onclick="startDiagnosis(<?= (int)$c['id'] ?>, '<?= $c['grade'] ?>')"
                                class="bg-slate-900 text-white text-[10px] font-bold uppercase px-4 py-2 rounded-lg hover:bg-slate-800 transition-colors">
                                Diagnostiko
                            </button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200">
                    <p class="text-[10px] font-bold uppercase text-slate-400 mb-4 tracking-widest">Vijueshmëria (7 ditë)</p>
                    <div class="h-32"><canvas id="trendChart"></canvas></div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-200">
                    <p class="text-[10px] font-bold uppercase text-slate-400 mb-2 tracking-widest">Logu i Fundit</p>
                    <?php if ($lastAction): ?>
                        <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($lastAction['action_title']) ?></p>
                        <p class="text-xs text-slate-500 mt-1">Statusi: <span class="font-bold text-indigo-600 uppercase"><?= $lastAction['status'] ?></span></p>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 italic mt-4">Nuk ka veprime të fundit.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-[10px] font-bold uppercase text-slate-400 mb-6 tracking-widest">Notat (30 ditë)</p>
                <div class="h-40"><canvas id="gradeChart"></canvas></div>
            </div>
            
            <div class="bg-slate-900 p-6 rounded-2xl text-white shadow-xl">
                <p class="text-[10px] font-bold uppercase text-slate-400 mb-3 tracking-widest">Fokusi i Ditës</p>
                <?php if (!empty($problemClasses)): ?>
                    <ul class="space-y-3">
                        <?php foreach (array_slice($problemClasses, 0, 2) as $c): ?>
                            <li class="flex items-center gap-3">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                <p class="text-xs font-medium">Klasa <?= htmlspecialchars($c['grade']) ?> kërkon ndjekje.</p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-xs text-slate-500">Monitorim rutinë.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* CHARTS */
Chart.defaults.font.size = 10;
Chart.defaults.color = '#94a3b8';

new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
        labels: ['5','4','3','2','1'],
        datasets: [{
            data: [<?= $gradeDist['5']??0 ?>,<?= $gradeDist['4']??0 ?>,<?= $gradeDist['3']??0 ?>,<?= $gradeDist['2']??0 ?>,<?= $gradeDist['1']??0 ?>],
            backgroundColor: '#1e293b',
            borderRadius: 4,
            barThickness: 12
        }]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display:false }, x: { grid: { display: false }, border: { display: false } } } }
});

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData,'day')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($trendData,'rate')) ?>,
            borderColor: '#6366f1',
            borderWidth: 2,
            tension: 0.4,
            pointRadius: 0
        }]
    },
    options: { maintainAspectRatio: false, plugins: { legend: { display:false } }, scales: { x:{display:false}, y:{display:false} } }
});

/* DIAGNOSIS & MODAL LOGIC */
const diagModal = document.getElementById('diagModal');
const actionModal = document.getElementById('actionModal');
let currentAction = { type: null, title: null, context: {} };

function startDiagnosis(classId, gradeName) {
    document.getElementById('diagClassName').innerText = `Klasa ${gradeName}`;
    diagModal.classList.remove('hidden');
    document.getElementById('diagContent').classList.add('hidden');
    document.getElementById('diagLoading').classList.remove('hidden');

    fetch(`/E-Shkolla/dashboard/schooladmin-dashboard/partials/dashboard/attendance-analysis.php?class_id=${classId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('diagLoading').classList.add('hidden');
            document.getElementById('diagContent').classList.remove('hidden');
            document.getElementById('diagSummary').innerText = data.summary;
            
            document.getElementById('diagStudents').innerHTML = data.students.length 
                ? data.students.map(s => `<li>• ${s.name} <span class="text-rose-500">(${s.absences})</span></li>`).join('')
                : '<li class="italic text-slate-400">Asnjë rast kronik.</li>';

            document.getElementById('diagSubjects').innerHTML = data.subjects.length 
                ? data.subjects.map(s => `<li>• ${s.name} <span class="text-indigo-500">${s.rate}%</span></li>`).join('')
                : '<li class="italic text-slate-400">Pa anomali lënde.</li>';

            document.getElementById('proceedToActionButton').onclick = () => {
                closeDiag();
                openActionModal('notify_teacher', `Njofto mësimdhënësin — ${gradeName}`, `Sipas analizës: ${data.summary}`, { class_id: classId });
            };
        });
}

function closeDiag() { diagModal.classList.add('hidden'); }

function openActionModal(type, title, description, context = {}) {
    currentAction = { type, title, context };
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalDescription').innerText = description;
    document.getElementById('actionMessage').value = '';
    actionModal.classList.remove('hidden');
}

function closeModal() { actionModal.classList.add('hidden'); }

document.getElementById('confirmBtn').onclick = () => {
    fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/dashboard/handle_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type: currentAction.type,
            title: currentAction.title,
            note: document.getElementById('actionMessage').value || '',
            context: currentAction.context
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') { location.reload(); }
        else { alert(data.error || 'Gabim gjatë ruajtjes'); }
    });
};
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../index.php';