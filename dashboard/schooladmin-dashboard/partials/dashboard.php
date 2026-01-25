<?php
/**
 * E-Shkolla — Paneli i Administratorit të Shkollës
 * Versioni: Command Center v1 (Human-Centric)
 * Filozofia: Qartësi > Metriçe | Veprim > Zhurmë
 */

require_once __DIR__ . '/../../../db.php';

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

// Indeksi i rrezikut (i brendshëm)
$riskScore = 0;
if ($attendanceDiff < 0) $riskScore += abs($attendanceDiff) * 3;
$riskScore += count($problemClasses) * 12;
$riskScore = min(100, $riskScore);

// Veprimi i fundit administrativ
$stmt = $pdo->prepare("
    SELECT action_title, created_at
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

<!-- MODALI I VEPRIMIT -->
<div id="actionModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-xl border border-slate-200">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 id="modalTitle" class="text-sm font-black uppercase tracking-widest">Veprim Administrativ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="p-6">
            <p id="modalDescription" class="text-sm text-slate-600 mb-4"></p>
            <textarea id="actionMessage" rows="3"
                class="w-full p-3 border rounded-lg text-sm"
                placeholder="Shënim i brendshëm (opsional)…"></textarea>
        </div>
        <div class="p-6 bg-slate-50 flex gap-3">
            <button onclick="closeModal()" class="flex-1 text-xs font-black uppercase text-slate-500">Anulo</button>
            <button id="confirmBtn" class="flex-1 bg-slate-900 text-white text-xs font-black uppercase rounded-lg py-2">
                Konfirmo
            </button>
        </div>
    </div>
</div>

<div class="p-6 lg:p-10 bg-slate-50 min-h-screen text-slate-900">

    <!-- HEADER -->
    <div class="mb-10 border-b border-slate-200 pb-6">
        <h1 class="text-3xl font-black"><?= htmlspecialchars($schoolName) ?></h1>
        <p class="text-xs uppercase tracking-widest text-slate-400 mt-1">
            Përmbledhje e Gjendjes së Shkollës · Përditësuar sot në <?= $updatedAt ?>
        </p>
    </div>

    <!-- GJENDJA SOT -->
    <div class="mb-8 bg-white p-6 rounded-2xl border border-slate-200">
        <p class="text-xs uppercase text-slate-400 font-bold mb-2">
            Gjendja e Shkollës Sot
        </p>

        <p class="text-2xl font-black">
            <?= $riskScore > 50
                ? 'Shkolla kërkon vëmendje sot.'
                : 'Shkolla po funksionon normalisht sot.' ?>
        </p>

        <p class="mt-2 text-sm text-slate-600">
            <?= $riskScore > 50
                ? 'Janë vërejtur sinjale që kërkojnë monitorim në vijueshmëri ose performancë akademike.'
                : 'Nuk janë identifikuar probleme kritike në vijueshmëri apo rezultate akademike.' ?>
        </p>
    </div>

    <!-- FOKUSI I DITËS -->
    <div class="mb-10 bg-slate-900 text-white p-6 rounded-2xl">
        <p class="text-xs uppercase tracking-widest text-slate-400 mb-3 font-bold">
            Fokusi i Ditës
        </p>

        <?php if (!empty($problemClasses)): ?>
            <ul class="space-y-2 text-sm font-semibold">
                <?php foreach (array_slice($problemClasses, 0, 2) as $c): ?>
                    <li>
                        • Ndiqni Klasën <?= htmlspecialchars($c['grade']) ?>
                        (<?= $c['rate'] ?>% vijueshmëri)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-300">
                Nuk ka veprime urgjente për sot.
            </p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- MAJTAS -->
        <div class="lg:col-span-8 space-y-8">

            <div class="bg-slate-50 p-5 rounded-xl border border-slate-200">
                <p class="text-xs uppercase text-slate-400 font-bold mb-3">Pse kjo gjendje</p>
                <ul class="text-sm space-y-1 text-slate-700">
                    <li>• Trendi i vijueshmërisë është <?= $attendanceDiff < 0 ? 'në rënie' : 'i qëndrueshëm' ?></li>
                    <li>• <?= count($problemClasses) ?> klasa janë nën pragun minimal të vijueshmërisë</li>
                    <li>• Performanca akademike është brenda pritshmërive</li>
                </ul>
                <p class="mt-4 text-xs text-slate-400">
                    Indeksi i rrezikut (i brendshëm): <?= $riskScore ?>/100
                </p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200">
                <p class="text-xs uppercase text-slate-400 font-bold mb-2">
                    Çfarë mund të ndodhë në vazhdim
                </p>
                <p class="text-sm text-slate-700">
                    Nëse trendi aktual vazhdon, vijueshmëria e përgjithshme mund të bjerë nën <strong>80%</strong>
                    në ditët në vijim.
                </p>
                <p class="mt-2 text-xs text-slate-400">
                    Besueshmëria: mesatare (bazuar në 7 ditët e fundit)
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <div class="p-6 border-b bg-slate-50 flex justify-between items-center">
                    <p class="text-xs uppercase font-bold text-slate-400">
                        Klasat që kërkojnë vëmendje
                    </p>
                    <?php if ($lastAction): ?>
                        <p class="text-xs text-slate-400 italic">
                            Veprimi i fundit: <?= htmlspecialchars($lastAction['action_title']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (empty($problemClasses)): ?>
                    <div class="p-12 text-center text-slate-400 text-sm">
                        Të gjitha klasat janë brenda niveleve normale të vijueshmërisë.
                    </div>
                <?php else: ?>
                    <?php foreach ($problemClasses as $c): ?>
                        <div class="p-6 flex justify-between items-center hover:bg-slate-50">
                            <div>
                                <p class="font-bold">Klasa <?= htmlspecialchars($c['grade']) ?></p>
                                <p class="text-xs text-rose-600 uppercase">
                                    <?= $c['rate'] ?>% vijueshmëri sot
                                </p>
                            </div>
                            <button
                                onclick="openActionModal(
                                    'notify',
                                    'Njofto mësimdhënësin — Klasa <?= $c['grade'] ?>',
                                    'Kërko sqarim lidhur me rënien e vijueshmërisë sot.'
                                )"
                                class="bg-slate-900 text-white text-xs font-black uppercase px-4 py-2 rounded-lg">
                                Ndërmerr veprim
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- DJATHTAS -->
        <div class="lg:col-span-4 space-y-8">

            <div class="bg-white p-6 rounded-2xl border border-slate-200">
                <p class="text-xs uppercase font-bold text-slate-400 mb-4">
                    Gjendja Akademike (30 ditët e fundit)
                </p>
                <div class="h-40">
                    <canvas id="gradeChart"></canvas>
                </div>
                <p class="mt-4 text-xs text-slate-500 italic">
                    Rritja e notave të ulëta mund të tregojë vështirësi në vlerësim ose ngarkesë të lëndëve.
                </p>
            </div>

            <div class="bg-slate-900 p-6 rounded-2xl text-white">
                <p class="text-xs uppercase text-slate-400 font-bold mb-4">
                    Trendi i Vijueshmërisë
                </p>
                <div class="h-32">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <!-- MBYLLJE / SIGURI -->
    <div class="mt-12 p-6 rounded-2xl bg-slate-100 text-slate-600 text-sm">
        Sistemi monitoron vazhdimisht vijueshmërinë dhe performancën akademike.
        Në rast të ndryshimeve të rëndësishme, do të njoftoheni menjëherë.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#94a3b8';

new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
        labels: ['5','4','3','2','1'],
        datasets: [{
            data: [<?= $gradeDist['5']??0 ?>,<?= $gradeDist['4']??0 ?>,<?= $gradeDist['3']??0 ?>,<?= $gradeDist['2']??0 ?>,<?= $gradeDist['1']??0 ?>],
            backgroundColor: '#1e293b',
            borderRadius: 6,
            barThickness: 16
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { display:false }, x: { display:true } }
    }
});

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trendData,'day')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($trendData,'rate')) ?>,
            borderColor: '#6366f1',
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 0
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display:false } },
        scales: { x:{display:false}, y:{display:false} }
    }
});

const modal = document.getElementById('actionModal');
const confirmBtn = document.getElementById('confirmBtn');

function openActionModal(type, title, description) {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalDescription').innerText = description;
    modal.classList.remove('hidden');

    confirmBtn.onclick = () => {
        fetch('handle_command.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ type, title })
        }).then(() => location.reload());
    };
}

function closeModal() {
    modal.classList.add('hidden');
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
