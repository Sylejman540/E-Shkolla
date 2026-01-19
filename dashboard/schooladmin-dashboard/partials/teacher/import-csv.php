<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) return;

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    try {
        $file = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($file);

        $expected = ['name','email','phone','gender','subject_name','class_grade','status'];
        if ($header !== $expected) {
            throw new Exception('CSV formati nuk është i saktë.');
        }

        $added = $skipped = $errors = 0;
        $pdo->beginTransaction();

        while (($row = fgetcsv($file)) !== false) {
            [$name,$email,$phone,$gender,$subject,$grade,$status] = array_map('trim', $row);

            if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++; continue;
            }

            $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $skipped++; continue;
            }

            $cls = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
            $cls->execute([$grade, $schoolId]);
            $classId = $cls->fetchColumn();
            if (!$classId) {
                $errors++; continue;
            }

            $plain = bin2hex(random_bytes(4));
            $hash  = password_hash($plain, PASSWORD_DEFAULT);

            $pdo->prepare("
                INSERT INTO users (school_id,name,email,password,role,status)
                VALUES (?,?,?,?, 'teacher',?)
            ")->execute([$schoolId,$name,$email,$hash,$status]);

            $userId = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO teachers (school_id,user_id,name,email,phone,gender,subject_name,status)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([$schoolId,$userId,$name,$email,$phone,$gender,$subject,$status]);

            $teacherId = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO subjects (school_id,user_id,name,subject_name,status)
                VALUES (?,?,?,?,?)
            ")->execute([$schoolId,$userId,$name,$subject,$status]);

            $subjectId = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO teacher_class (school_id,teacher_id,class_id,subject_id)
                VALUES (?,?,?,?)
            ")->execute([$schoolId,$teacherId,$classId,$subjectId]);

            $added++;
        }

        $pdo->commit();
        fclose($file);

        $report = compact('added','skipped','errors');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $report = ['error' => $e->getMessage()];
    }
}
?>

<div id="importCsvModal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/30 overflow-y-auto py-10">

    <div class="bg-white dark:bg-gray-900 w-full max-w-xl rounded-2xl p-6 shadow-xl border dark:border-white/10">

        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Importo Mësues me CSV</h3>
            <button onclick="closeCsv()" class="text-2xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <?php if ($report): ?>
            <?php if (isset($report['error'])): ?>
                <div class="bg-red-50 text-red-700 p-3 rounded mb-4"><?= $report['error'] ?></div>
            <?php else: ?>
                <div class="bg-emerald-50 text-emerald-700 p-3 rounded mb-4">
                    ✔ <?= $report['added'] ?> shtuar |
                    ⚠ <?= $report['skipped'] ?> anashkaluar |
                    ❌ <?= $report['errors'] ?> gabime
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-5">
            <div class="relative group">
                <label for="csv-upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-200 rounded-[24px] bg-slate-50 hover:bg-white hover:border-emerald-400 transition-all cursor-pointer group">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <div class="p-3 bg-white rounded-2xl shadow-sm mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <p class="text-xs font-bold text-slate-700 uppercase tracking-wider">Zgjidh skedarin CSV</p>
                        <p id="file-name" class="text-[10px] text-slate-400 mt-1 italic">Klikoni ose tërhiqeni këtu</p>
                    </div>
                    
                    <input id="csv-upload" type="file" name="csv" accept=".csv" required 
                        class="hidden" 
                        onchange="updateFileName(this)">
                </label>
            </div>

            <button class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-[18px] font-black text-sm uppercase tracking-widest shadow-lg shadow-emerald-200 transition-all active:scale-[0.98]">
                Importo CSV
            </button>
            <button type="button" onclick="toggleCsvHelp()" class="text-sm font-semibold text-indigo-600 hover:underline mb-3">❓ Ndihmë – Si të importoj CSV?</button>

            <div id="csvHelpBox" class="hidden bg-slate-50 dark:bg-gray-800 p-4 rounded-xl text-sm text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10 space-y-2">

            <p class="font-semibold text-slate-900 dark:text-white">
                📥 Hapat për importim të mësuesve me CSV
            </p>

            <ol class="list-decimal list-inside space-y-1">
                <li>Shkarko CSV Template.</li>
                <li>Plotëso mësuesit në Excel ose Google Sheets.</li>
                <li>Ruaje dokumentin si <strong>.csv</strong>.</li>
                <li>Kliko “Choose file” dhe zgjidh CSV.</li>
                <li>Kliko “Importo CSV”.</li>
            </ol>

            <p class="mt-2">
                📌 <strong>Kolonat duhet të jenë saktësisht:</strong>
            </p>

            <code class="block bg-white dark:bg-gray-900 p-2 rounded text-xs">
                name,email,phone,gender,subject_name,class_grade,status
            </code>

            <p class="mt-2">
                ⚠️ <strong>Kujdes:</strong>
            </p>
            <ul class="list-disc list-inside space-y-1">
                <li>Email duhet të jetë unik</li>
                <li>Klasa duhet të ekzistojë në sistem</li>
                <li>Statusi: <code>active</code> ose <code>inactive</code></li>
            </ul>

            <p class="mt-2">
                📄 <strong>Shembull rreshti:</strong>
            </p>

            <code class="block bg-white dark:bg-gray-900 p-2 rounded text-xs">
                Arben Krasniqi,arben@school.com,044123456,male,Matematikë,6,active
            </code>
        </div>

        </form>
        <p class="mt-3 text-xs text-slate-500">
            Formati: name, email, phone, gender, subject_name, class_grade, status
        </p>
    </div>
</div>
<script>
// Funksion i vogël për të treguar emrin e skedarit sapo zgjidhet
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('file-name');
    if (input.files && input.files.length > 0) {
        fileNameDisplay.innerText = "U zgjodh: " + input.files[0].name;
        fileNameDisplay.classList.remove('text-slate-400');
        fileNameDisplay.classList.add('text-emerald-600', 'font-bold');
    }
}

function toggleCsvHelp() {
    document.getElementById('csvHelpBox').classList.toggle('hidden');
}

</script>