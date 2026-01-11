<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo->beginTransaction();

        /* =====================
           INPUTS
        ===================== */
        $name        = $_POST['name'] ?? null;
        $gender      = $_POST['gender'] ?? null;
        $class_id    = $_POST['class'] ?? null;
        $date_birth  = $_POST['date_birth'] ?? null;
        $email       = $_POST['email'] ?? null;
        $status      = $_POST['status'] ?? 'active';
        $password    = $_POST['password'] ?? null;

        $schoolId = $_SESSION['user']['school_id'] ?? null;

        if (!$schoolId) {
            throw new Exception('School ID missing');
        }

        if (!$class_id) {
            throw new Exception('Class not selected');
        }

        /* =====================
           GET CLASS NAME (X/3)
        ===================== */
        $stmt = $pdo->prepare("
            SELECT grade
            FROM classes
            WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([$class_id, $schoolId]);

        $classRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classRow) {
            throw new Exception('Invalid class selected');
        }

        $class_name = $classRow['grade']; // e.g. X/3

        /* =====================
           1️⃣ CREATE USER
        ===================== */
        $stmt = $pdo->prepare("
            INSERT INTO users (school_id, name, email, password, role, status)
            VALUES (?, ?, ?, ?, 'student', ?)
        ");
        $stmt->execute([
            $schoolId,
            $name,
            $email,
            $password,
            $status
        ]);

        $newUserId = $pdo->lastInsertId();

        /* =====================
           2️⃣ CREATE STUDENT
           (CLASS NAME ONLY)
        ===================== */
        $stmt = $pdo->prepare("
            INSERT INTO students
            (school_id, user_id, name, gender, class_name, date_birth, email, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $schoolId,
            $newUserId,
            $name,
            $gender,
            $class_name,
            $date_birth,
            $email,
            $status
        ]);

        $student_id = $pdo->lastInsertId();

        /* =====================
           3️⃣ LINK STUDENT ↔ CLASS
           (ID ONLY)
        ===================== */
        $stmt = $pdo->prepare("
            INSERT INTO student_class (school_id, student_id, class_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $schoolId,
            $student_id,
            $class_id
        ]);

        $pdo->commit();

        header("Location: /E-Shkolla/students");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error: ' . $e->getMessage());
    }

    header("Location: /E-Shkolla/students");
    exit;
}

?>
<div id="addSchoolForm" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto pt-10">
     
    <div class="w-full max-w-3xl px-4">

      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Shto nxënës të ri
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Plotësoni të dhënat bazë për nxënësin.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">
            
        <form action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/students/form.php" method="post" class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">

            <div class="sm:col-span-3">
            <label for="name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Emri dhe mbiemri</label>
            <div class="mt-2">
                <input id="name" type="text" name="name" autocomplete="name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="password" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Fjalëkalim</label>
            <div class="mt-2">
                <input id="password" type="text" name="password" autocomplete="password" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-2">
            <label for="gender" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Gjinia</label>
            <div class="mt-2">
              <select id="gender" name="gender" autocomplete="sex" class="border block w-full rounded-md bg-white p-[7px] text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
                <option value="male">Mashkull</option>
                <option value="female">Femër</option>
                <option value="other">Tjetër</option>
              </select>
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Email</label>
            <div class="mt-2">
                <input id="email" type="email" name="email" autocomplete="email" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>

            <div class="sm:col-span-3">
            <label for="date_birth" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Ditëlindja</label>
            <div class="mt-2">
                <input id="date_birth" type="text" name="date_birth" autocomplete="date_birth" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
            </div>
            </div>
            

          <div class="sm:col-span-3">
              <label class="block text-sm font-medium text-gray-900 dark:text-white">Klasa</label>
              <select name="class" class="mt-2 border block w-full round/\ ed-md p-2">
                  <?php
                  $classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                  $classes->execute([$_SESSION['user']['school_id']]);
                  foreach ($classes as $c):
                  ?>
                      <option value="<?= $c['id'] ?>">
                          <?= htmlspecialchars($c['grade']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>

            <div class="sm:col-span-3">
            <label for="status" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Statusi</label>
            <div class="mt-2">
              <select id="status" name="status" autocomplete="status" class="border block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-gray-300 focus:outline-2 focus:outline-indigo-600 sm:text-sm dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus:outline-indigo-500">
                  <option value="active">Aktive</option>
                  <option value="inactive">Joaktive</option>
              </select>
            </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-x-4">
            <button type="button" id="cancel" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Cancel</button>

            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500">Save</button>
        </div>
        </form>
    </div>
  </div>
</div>
