<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;
if (!$schoolId) return;

$report = null;

// Funksioni që kryen regjistrimin në Databazë
function executeImport($rows, $pdo, $schoolId) {
    $added = $skipped = $errors = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            // Nëse të dhënat vijnë nga preview-csv, ato janë brenda çelësit 'data'
            $data = isset($row['data']) ? $row['data'] : $row;
            if (count($data) < 7) continue;

            [$name, $email, $phone, $gender, $subject, $grade, $status] = array_map('trim', $data);

            // Validimi i emailit unik
            $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) { $skipped++; continue; }

            // Gjetja e ID-së së klasës
            $cls = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND school_id = ?");
            $cls->execute([$grade, $schoolId]);
            $classId = $cls->fetchColumn();
            if (!$classId) { $errors++; continue; }

            // Gjenerimi i fjalëkalimit automatik
            $plain = bin2hex(random_bytes(4));
            $hash = password_hash($plain, PASSWORD_DEFAULT);

            // 1. Krijo User
            $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?,?,?,?,'teacher',?)")
                ->execute([$schoolId, $name, $email, $hash, $status]);
            $userId = $pdo->lastInsertId();

            // 2. Krijo Teacher
            $pdo->prepare("INSERT INTO teachers (school_id, user_id, name, email, phone, gender, subject_name, status) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$schoolId, $userId, $name, $email, $phone, $gender, $subject, $status]);
            $teacherId = $pdo->lastInsertId();

            // 3. Krijo Subject
            $pdo->prepare("INSERT INTO subjects (school_id, user_id, name, subject_name, status) VALUES (?,?,?,?,?)")
                ->execute([$schoolId, $userId, $name, $subject, $status]);
            $subjectId = $pdo->lastInsertId();

            // 4. Lidhja Teacher-Class
            $pdo->prepare("INSERT INTO teacher_class (school_id, teacher_id, class_id, subject_id) VALUES (?,?,?,?)")
                ->execute([$schoolId, $teacherId, $classId, $subjectId]);

            $added++;
        }
        $pdo->commit();
        return compact('added', 'skipped', 'errors');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => $e->getMessage()];
    }
}

// RASTI 1: Përdoruesi shtyp "Vazhdo Importimin" (Konfirmim nga Sesioni)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_final']) && $_POST['confirm_final'] == "1") {
    if (isset($_SESSION['csv_rows']) && !empty($_SESSION['csv_rows'])) {
        $report = executeImport($_SESSION['csv_rows'], $pdo, $schoolId);
        unset($_SESSION['csv_rows']); // Fshijmë të dhënat e përkohshme
    }
} 
// RASTI 2: Përdoruesi bën Upload një skedar të ri direkt
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && $_FILES['csv']['size'] > 0) {
    $file = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($file); // Kalojmë header-in
    $rows = [];
    while (($r = fgetcsv($file)) !== false) { $rows[] = $r; }
    fclose($file);
    $report = executeImport($rows, $pdo, $schoolId);
}
?>

<div id="importCsvModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm overflow-y-auto py-10 px-4 <?= !$report ? 'hidden' : '' ?>">
    <div class="bg-white dark:bg-gray-900 w-full max-w-xl rounded-[28px] p-8 shadow-2xl border dark:border-white/10">
        
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-900 dark:text-white">Importo Mësues</h3>
            <button onclick="closeCsv()" class="h-10 w-10 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400">&times;</button>
        </div>

        <?php if ($report): ?>
            <div class="mb-6 p-4 rounded-2xl <?= isset($report['error']) ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' ?> text-sm font-bold border border-current/10">
                <?php if (isset($report['error'])): ?>
                    ❌ <?= htmlspecialchars($report['error']) ?>
                <?php else: ?>
                    🎉 Rezultati: <?= $report['added'] ?> shtuar, <?= $report['skipped'] ?> dublikatë, <?= $report['errors'] ?> gabime.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form id="mainCsvForm" method="post" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="confirm_final" id="confirm_final" value="0">

            <div class="relative group">
                <label for="csv-upload" class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-slate-200 rounded-[24px] bg-slate-50 hover:bg-white hover:border-emerald-400 transition-all cursor-pointer overflow-hidden">
                    <div class="flex flex-col items-center justify-center p-6">
                        <div class="p-3 bg-white rounded-2xl shadow-sm mb-3 group-hover:scale-110 transition-transform border border-slate-100">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </div>
                        <p id="file-name-text" class="text-sm font-bold text-slate-700">Zgjidh skedarin CSV</p>
                    </div>
                    <input id="csv-upload" type="file" name="csv" accept=".csv" required class="hidden" onchange="handleFileSelect(this)">
                </label>
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-[18px] font-black text-sm shadow-lg transition-all active:scale-[0.98]">
                Importo CSV
            </button>

            <div id="csvPreview" class="hidden animate-in fade-in duration-300">
                <h4 class="text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest">Parapamje e të dhënave</h4>
                <div class="max-h-48 overflow-y-auto border border-slate-100 rounded-xl bg-slate-50">
                    <table class="w-full text-[10px] text-left">
                        <thead class="bg-slate-100 sticky top-0">
                            <tr><th class="p-2">Emri</th><th class="p-2">Email</th><th class="p-2">Klasa</th><th class="p-2">Statusi</th></tr>
                        </thead>
                        <tbody id="csvPreviewBody"></tbody>
                    </table>
                </div>
                <button type="button" onclick="confirmAndSubmit()" id="confirmImportBtn" class="mt-4 w-full bg-indigo-600 text-white py-2 rounded-xl font-bold text-xs uppercase">Vazhdo Importimin</button>
            </div>
        </form>
    </div>
</div>

<script>
function handleFileSelect(input) {
    if (input.files.length > 0) {
        document.getElementById('file-name-text').innerText = "U zgjodh: " + input.files[0].name;
        triggerPreview(input.files[0]);
    }
}

async function triggerPreview(file) {
    const formData = new FormData();
    formData.append('csv', file);

    const res = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/teacher/preview-csv.php', {
        method: 'POST',
        body: formData
    });

    const data = await res.json();
    const body = document.getElementById('csvPreviewBody');
    body.innerHTML = '';
    
    let validRows = 0;
    data.rows.forEach(r => {
        const ok = r.errors.length === 0;
        if(ok) validRows++;
        body.innerHTML += `
            <tr class="${ok ? 'bg-white' : 'bg-rose-50 text-rose-600'}">
                <td class="p-2 border-b border-slate-50">${r.data[0] || '-'}</td>
                <td class="p-2 border-b border-slate-50">${r.data[1] || '-'}</td>
                <td class="p-2 border-b border-slate-50">${r.data[5] || '-'}</td>
                <td class="p-2 border-b border-slate-50 font-bold">${ok ? 'OK' : 'GABIM'}</td>
            </tr>`;
    });

    document.getElementById('csvPreview').classList.remove('hidden');
    document.getElementById('confirmImportBtn').classList.toggle('hidden', validRows === 0);
}

function confirmAndSubmit() {
    // KJO ËSHTË ZGJIDHJA:
    document.getElementById('confirm_final').value = "1"; // I tregojmë PHP-së të përdorë sesionin
    document.getElementById('csv-upload').required = false; // Heqim detyrimin për skedar
    document.getElementById('mainCsvForm').submit();
}

function closeCsv() { document.getElementById('importCsvModal').classList.add('hidden'); }
</script>