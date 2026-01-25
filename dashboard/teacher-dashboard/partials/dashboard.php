<?php
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: /login.php');
    exit;
}

$teacherId   = $_SESSION['user']['id'];
$teacherName = $_SESSION['user']['name'] ?? 'Mësimdhënës';

/* =======================
   KLASAT E MËSIMDHËNËSIT
======================= */
$stmt = $pdo->prepare("
SELECT DISTINCT c.id, c.grade
FROM classes c
INNER JOIN class_schedule s ON s.class_id = c.id
WHERE s.teacher_id = ?

");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================
   SINJALE (LOGJIKË PRODUKTI)
======================= */

// Klasa me vijueshmëri të ulët (7 ditë)
$stmt = $pdo->prepare("
    SELECT c.id, c.grade,
           ROUND((SUM(a.present)/COUNT(*))*100) rate
    FROM attendance a
    JOIN classes c ON c.id = a.class_id
    WHERE a.teacher_id = ?
      AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY c.id
    HAVING rate < 75
");
$stmt->execute([$teacherId]);
$lowAttendanceClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detyra pa u vlerësuar
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM assignments 
    WHERE teacher_id = ? AND status = 'submitted'
");
$stmt->execute([$teacherId]);
$pendingAssignments = (int)$stmt->fetchColumn();

// Nxënës me mungesa të përsëritura (7 ditë)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id)
    FROM attendance
    WHERE teacher_id = ?
      AND present = 0
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$teacherId]);
$studentsAtRisk = (int)$stmt->fetchColumn();

ob_start();
?>

<div class="p-6 lg:p-10 bg-slate-50 min-h-screen text-slate-900">

    <!-- HEADER -->
    <div class="mb-8 border-b border-slate-200 pb-6">
        <h1 class="text-3xl font-black">Paneli i Mësimdhënësit</h1>
        <p class="text-sm text-slate-500 mt-1">
            Gjendja e përgjithshme e klasave tuaja
        </p>
    </div>

    <!-- SINJALET -->
    <div class="mb-10 bg-white rounded-2xl border border-slate-200 p-6">
        <h2 class="text-xs uppercase font-bold text-slate-400 mb-4">
            Sinjale & Prioritete
        </h2>

        <ul class="space-y-3 text-sm">
            <?php if (!empty($lowAttendanceClasses)): ?>
                <li class="text-rose-600 font-semibold">
                    ⚠️ <?= count($lowAttendanceClasses) ?> klasë me vijueshmëri nën 75%
                </li>
            <?php endif; ?>

            <?php if ($pendingAssignments > 0): ?>
                <li class="text-amber-600 font-semibold">
                    📝 <?= $pendingAssignments ?> detyra pa u vlerësuar
                </li>
            <?php endif; ?>

            <?php if ($studentsAtRisk > 0): ?>
                <li class="text-rose-600 font-semibold">
                    📉 <?= $studentsAtRisk ?> nxënës me mungesa të përsëritura
                </li>
            <?php endif; ?>

            <?php if (
                empty($lowAttendanceClasses) &&
                $pendingAssignments === 0 &&
                $studentsAtRisk === 0
            ): ?>
                <li class="text-emerald-600 font-semibold">
                    ✓ Nuk ka probleme kritike për momentin
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- LISTA E KLASAVE -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-6 border-b bg-slate-50">
            <h2 class="text-xs uppercase font-bold text-slate-400">
                Klasat e mia
            </h2>
        </div>

        <?php if (empty($classes)): ?>
            <div class="p-10 text-center text-slate-400">
                Nuk jeni të caktuar në asnjë klasë.
            </div>
        <?php else: ?>
            <?php foreach ($classes as $class): ?>
                <?php
                $status = 'Gjendje normale';
                $statusColor = 'text-emerald-600';

                foreach ($lowAttendanceClasses as $low) {
                    if ($low['id'] == $class['id']) {
                        $status = 'Vijueshmëri në rënie';
                        $statusColor = 'text-rose-600';
                        break;
                    }
                }
                ?>
                <div class="p-6 flex items-center justify-between hover:bg-slate-50 transition">
                    <div>
                        <p class="font-black text-lg">
                            Klasa <?= htmlspecialchars($class['grade']) ?>
                        </p>
                        <p class="text-sm <?= $statusColor ?>">
                            <?= $status ?>
                        </p>
                    </div>

                    <a href="/E-Shkolla/dashboard/teacher-dashboard/class.php?id=<?= $class['id'] ?>"
                       class="px-4 py-2 text-xs font-black uppercase rounded-lg border border-slate-300 hover:bg-slate-900 hover:text-white transition">
                        Hap klasën
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- FOOTER INFO -->
    <div class="mt-10 text-sm text-slate-500">
        Ky panel shërben për orientim dhe monitorim.
        Veprimet konkrete kryhen brenda secilës klasë.
    </div>

</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/index.php';
?>
