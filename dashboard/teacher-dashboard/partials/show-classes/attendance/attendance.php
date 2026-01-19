<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Sigurohuni që këto shtigje janë të sakta në serverin tuaj
require_once __DIR__ . '/../../../../../db.php';

/* ===== LOGJIKA POST (RUAJTJA E PREZENCËS) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
    $teacherId = (int) ($_SESSION['user']['id'] ?? 0); // Ose teacher_id varësisht nga sesioni juaj

    $studentId = (int) ($_POST['student_id'] ?? 0);
    $classId   = (int) ($_POST['class_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    if (!$schoolId || !$teacherId || !$studentId || !$classId || !$subjectId) {
        die('Të dhëna të pavlefshme për prezencën');
    }

    $present = isset($_POST['present_action']) ? 1 : 0;
    $missing = isset($_POST['missing_action']) ? 1 : 0;

    /* ===== KONTROLLI I LOCK-UT (1 ORË) ===== */
    $lockStmt = $pdo->prepare("
        SELECT id FROM attendance 
        WHERE student_id = ? AND class_id = ? AND subject_id = ? AND teacher_id = ? 
        AND created_at >= NOW() - INTERVAL 1 HOUR LIMIT 1
    ");
    $lockStmt->execute([$studentId, $classId, $subjectId, $teacherId]);

    if ($lockStmt->fetch()) {
        header("Location: /E-Shkolla/class-attendance?class_id=$classId&subject_id=$subjectId&error=locked");
        exit;
    }

    /* ===== INSERTIMI I PREZENCËS ===== */
    $stmt = $pdo->prepare("
        INSERT INTO attendance (school_id, student_id, class_id, subject_id, teacher_id, present, missing)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$schoolId, $studentId, $classId, $subjectId, $teacherId, $present, $missing]);

    header("Location: /E-Shkolla/class-attendance?class_id=$classId&subject_id=$subjectId&success=1");
    exit;
}

/* ===== LOGJIKA GET (SHFAQJA E LISTËS) ===== */
$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0); // E nevojshme për formën POST

if ($classId <= 0) {
    die('ID e klasës e pavlefshme');
}

$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.email, s.status
    FROM student_class sc
    INNER JOIN students s ON s.student_id = sc.student_id
    WHERE sc.class_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Përfshirja e template-it të faqes (Sidebar etj)
ob_start();
?>

            <div class="sm:flex-auto mt-5">
                <h1 class="text-2xl font-bold text-gray-900">Regjistrimi i Prezencës</h1>
                <p class="mt-2 text-sm text-gray-600">Lista e nxënësve për klasën dhe lëndën e përzgjedhur.</p>
            </div>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'locked'): ?>
            <div id="alert-locked" class="mb-4 mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-center gap-3 text-amber-800 shadow-sm transition-opacity duration-500">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="font-medium">Vërejtje: Prezenca për këtë nxënës është regjistruar para më pak se një ore.</span>
            </div>

            <script>
                setTimeout(() => {
                    const alertBox = document.getElementById('alert-locked');
                    if (alertBox) {
                        alertBox.style.opacity = '0';
                        setTimeout(() => alertBox.remove(), 500); // remove after fade out
                    }
                }, 5000); // 5 seconds
            </script>
        <?php endif; ?>


        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nxënësi</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Statusi</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Veprimi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if(!empty($students)): ?>
                            <?php foreach($students as $row): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($row['name']) ?></span>
                                            <span class="text-xs text-gray-500"><?= htmlspecialchars($row['email']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold inline-flex <?= $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                                            <?= strtoupper($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <form method="POST" class="inline-flex gap-2">
                                            <input type="hidden" name="student_id" value="<?= (int)$row['student_id'] ?>">
                                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

                                            <button type="submit" name="present_action" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm active:scale-95">
                                                Prezent
                                            </button>

                                            <button type="submit" name="missing_action" class="inline-flex items-center px-4 py-2 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold rounded-xl transition-all active:scale-95">
                                                Mungon
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-gray-400 italic">
                                    Nuk u gjet asnjë nxënës në këtë klasë.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>