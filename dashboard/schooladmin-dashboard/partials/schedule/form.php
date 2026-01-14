<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__  . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $schoolId = $_SESSION['user']['school_id'] ?? null;
$teacherId = (int)$_POST['teacher_id'];

$stmt = $pdo->prepare("
    SELECT s.id
    FROM subjects s
    JOIN teachers t ON t.user_id = s.user_id
    WHERE t.id = ? AND s.school_id = ?
    LIMIT 1
");
$stmt->execute([$teacherId, $schoolId]);

$subjectId = $stmt->fetchColumn();

if (!$subjectId) {
    die('No subject found for this teacher');
}

    $teacherId = $_POST['teacher_id'];    
    $classId = $_POST['class_id'];
    $user_id = $_SESSION['user']['id'] ?? null;
$stmt = $pdo->prepare("
    INSERT INTO class_schedule
    (school_id, user_id, class_id, day, start_time, end_time, subject_id, teacher_id, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $schoolId,
    $user_id,
    $classId,
    $day,
    $start_time,
    $end_time,
    $subjectId,
    $teacherId,
    $status
]);

    header("Location: /E-Shkolla/schedule");
    exit;
}
?>

<?php
$teacherSubjects = $pdo->prepare("
    SELECT s.id AS subject_id, s.subject_name, t.id AS teacher_id
    FROM subjects s
    JOIN teachers t ON t.user_id = s.user_id
    WHERE s.school_id = ?
");
$teacherSubjects->execute([$schoolId]);
$teacherSubjects = $teacherSubjects->fetchAll(PDO::FETCH_ASSOC);

?>
<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
    <div class="w-full max-w-3xl px-4">

      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Shto klasë të re
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Plotësoni të dhënat bazë për klasën.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">
          
          <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/form.php?class_id=<?= $row['id'] ?>" method="post" class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
          <input type="hidden" name="user_id" value="<?= $user_id ?>">
          
          <div class="sm:col-span-4">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Dita</label>
              <select name="day" class="mt-2 border block w-full rounded-md p-2">
                  <option value="monday">E hënë</option>
                  <option value="tuesday">E martë</option>
                  <option value="wednesday">E mërkurë</option>
                  <option value="thursday">E enjte</option>
                  <option value="friday">E premte</option>
              </select>
          </div>

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Ora e fillimit</label>
              <input type="time" name="start_time" class="mt-2 border w-full rounded-md p-2">
          </div>

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Ora e përfundimit</label>
              <input type="time" name="end_time" class="mt-2 border w-full rounded-md p-2">
          </div>

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Klasa</label>
              <select name="class_id" class="mt-2 border block w-full rounded-md p-2">
                  <?php
                  $teachers = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                  $teachers->execute([$_SESSION['user']['school_id']]);
                  foreach ($teachers as $t):
                  ?>
                      <option value="<?= $t['id'] ?>">
                          <?= htmlspecialchars($t['grade']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Lënda</label>
<input type="hidden" name="subject_id" id="subject_id">

<select id="subjectSelect"
        class="mt-2 border block w-full rounded-md p-2 bg-gray-100"
        disabled>
<?php foreach ($teacherSubjects as $s): ?>
    <option value="<?= $s['subject_id'] ?>"
            data-teacher-id="<?= $s['teacher_id'] ?>">
        <?= htmlspecialchars($s['subject_name']) ?>
    </option>
<?php endforeach; ?>
</select>

          </div>

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Mësuesi</label>
              <select name="teacher_id" id="teacherSelect"
        class="mt-2 border block w-full rounded-md p-2">
<?php
$teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id = ?");
$teachers->execute([$schoolId]);
foreach ($teachers as $t):
?>
    <option value="<?= $t['id'] ?>">
        <?= htmlspecialchars($t['name']) ?>
    </option>
<?php endforeach; ?>
</select>

          </div>

          <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Statusi</label>
              <select name="status" class="mt-2 border block w-full rounded-md p-2">
                  <option value="active">Aktive</option>
                  <option value="inactive">Joaktive</option>
              </select>
          </div>

        <div class="mt-32 flex justify-start ml-36 gap-x-4">
            <button type="button" id="cancel" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Cancel</button>

            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500">Save</button>
        </div>
        </form>
    </div>
  </div>
</div>
