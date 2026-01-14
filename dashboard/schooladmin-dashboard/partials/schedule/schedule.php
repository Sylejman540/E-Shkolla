<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../index.php'; 

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = ?");
$stmt->execute([$schoolId]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<main class="lg:pl-72">
  <div class="xl:pl-18">
    <div class="px-4 py-10 sm:px-6 lg:px-8 lg:py-6">
        <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Orari i klasave</h1>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Menaxhoni orarin javor për secilën klasë në shkollë
                </p>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-white/15">
        <thead>
            <tr>
                <th class="py-3.5 pl-4 text-left text-sm font-semibold">Viti akademik</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold">Klasa</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold">Nr i nxënësve</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold">Statusi</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold">Created At</th>
                <th class="py-3.5 pr-4 text-right"></th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
        <?php if (!empty($classes)): ?>
            <?php foreach ($classes as $row): ?>

            <tr>
                <td class="py-4 pl-4 font-medium"><?= htmlspecialchars($row['academic_year']) ?></td>
                <td class="px-3 py-4"><?= htmlspecialchars($row['grade']) ?></td>
                <td class="px-3 py-4"><?= htmlspecialchars($row['max_students']) ?></td>
                <td class="px-3 py-4">
                    <span class="px-2 py-1 rounded-xl bg-green-200 text-green-700 text-xs">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>
                <td class="px-3 py-4"><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="py-4 pr-4 text-right">
                    <button type="button" class="text-indigo-600 hover:text-indigo-900" onclick="toggleSchedule(<?= (int)$row['id'] ?>)">Shiko orarin</button>
                </td>
                <td class="py-4 pr-4 text-right">
                    <button type="button" class="addScheduleBtn text-indigo-600 hover:text-indigo-900" data-class-id="<?= (int)$row['id'] ?>">Shto orar</button>
                </td>
            </tr>
            <?php  

                $classId = (int) $row['id'];

                $stmt = $pdo->prepare("
                    SELECT 
                        cs.day,
                        cs.start_time,
                        cs.end_time,
                        s.subject_name,
                        t.name AS teacher_name,
                        c.grade AS class_name
                    FROM class_schedule cs
                    JOIN subjects s ON s.id = cs.subject_id
                    JOIN teachers t ON t.id = cs.teacher_id
                    JOIN classes c ON c.id = cs.class_id
                    WHERE cs.class_id = ?
                    ORDER BY cs.start_time
                ");
                $stmt->execute([$classId]);

                $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $grid = [];

                foreach ($schedule as $lesson) {
                    $timeKey = $lesson['start_time'] . ' - ' . $lesson['end_time'];
                    $grid[$timeKey][$lesson['day']] = $lesson;
                }
            
            ?>
            <tr id="schedule-<?= (int)$row['id'] ?>" class="hidden bg-gray-50 dark:bg-gray-900">
                <td colspan="8" class="p-4">
                    <table class="min-w-full text-sm border border-gray-200 rounded-md overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr class="uppercase text-xs text-gray-600">
                                <th class="py-2 px-2 text-left">Ora</th>
                                <th class="py-2 px-2 text-center">E Hënë</th>
                                <th class="py-2 px-2 text-center">E Martë</th>
                                <th class="py-2 px-2 text-center">E Mërkurë</th>
                                <th class="py-2 px-2 text-center">E Enjte</th>
                                <th class="py-2 px-2 text-center">E Premte</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($grid)): ?>
                            <?php foreach ($grid as $time => $days): ?>
                                <tr class="border-t">
                                    <td class="py-2 px-2 font-medium text-gray-700">
                                        <?= htmlspecialchars($time) ?>
                                    </td>

                                    <?php foreach (['monday','tuesday','wednesday','thursday','friday'] as $day): ?>
                                        <td class="py-2 px-2 text-center">
                                            <?php if (isset($days[$day])): ?>
                                                <div class="text-sm font-semibold">
                                                    <?= htmlspecialchars($days[$day]['subject_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($days[$day]['teacher_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($days[$day]['class_name']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">–</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500">
                                    Nuk ka orar për këtë klasë
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="py-10 text-center text-gray-500">
                    Tabela nuk përmban të dhëna
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>

    <?php require_once 'form.php'; ?>

    </div>
    </div>
    </div>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('addSchoolForm');
  const cancel = document.getElementById('cancel');

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.addScheduleBtn');
    if (!btn) return;

    const classId = btn.dataset.classId;

    const classInput = form.querySelector('input[name="class_id"]');
    if (classInput) {
      classInput.value = classId;
    }

    form.classList.remove('hidden');
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  cancel?.addEventListener('click', () => {
    form.classList.add('hidden');
  });

  const params = new URLSearchParams(window.location.search);
  if (params.get('open_form') === '1') {
    form.classList.remove('hidden');
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
});


function toggleSchedule(classId) {
    const row = document.getElementById('schedule-' + classId);
    row.classList.toggle('hidden');
}
</script>

</body>
</html>
