<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../../../db.php';

$schoolId  = (int) ($_SESSION['user']['school_id'] ?? 0);
$classId   = (int) ($_GET['class_id'] ?? 0);
$subjectId = (int) ($_GET['subject_id'] ?? 0);

// 1. Logjika e Ruajtjes (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int) $_POST['student_id'];
    $grade     = isset($_POST['grade']) ? (int)$_POST['grade'] : null;
    $comment   = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    $teacherId = (int) $_SESSION['user']['id'];

    try {
        // Marrim ID-në e mësuesit nga tabela teachers
        $tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $tStmt->execute([$teacherId]);
        $realTeacherId = $tStmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO grades (school_id, teacher_id, student_id, class_id, subject_id, grade, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                grade = COALESCE(?, grade), 
                comment = COALESCE(?, comment),
                updated_at = NOW()
        ");
        $stmt->execute([
            $schoolId, $realTeacherId, $studentId, $classId, $subjectId, 
            $grade, $comment, $grade, $comment
        ]);
        echo "success";
    } catch (Exception $e) {
        http_response_code(500);
        echo "error";
    }
    exit;
}

// 2. Marrja e të dhënave
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.email, g.grade, g.comment 
    FROM student_class sc 
    JOIN students s ON s.student_id = sc.student_id 
    LEFT JOIN grades g ON g.student_id = s.student_id AND g.subject_id = ?
    WHERE sc.class_id = ? 
    ORDER BY s.name ASC");
$stmt->execute([$subjectId, $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="relative min-h-screen p-4">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight text-indigo-600">Regjistri Digjital</h1>
            <p class="text-sm text-slate-500">Të dhënat ruhen automatikisht pas çdo ndryshimi.</p>
        </div>

        <div class="relative w-full md:w-80">
            <input type="text" id="liveSearch" placeholder="Kërko nxënësin..." 
                   class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-gray-900 border border-slate-200 dark:border-white/10 rounded-2xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white shadow-sm">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="w-[30%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nxënësi</th>
                        <th class="w-[15%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-center">Nota (1-5)</th>
                        <th class="w-[45%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Koment/Vlerësimi</th>
                        <th class="w-[10%] px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right pr-6">Statusi</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody" class="divide-y divide-slate-200 dark:divide-white/5">
                    <?php foreach ($students as $row): ?>
                    <tr class="group hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xs ring-1 ring-indigo-100 dark:ring-indigo-500/20">
                                    <?= strtoupper(substr($row['name'], 0, 2)) ?>
                                </div>
                                <div class="flex flex-col truncate">
                                    <span class="student-name text-sm font-semibold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($row['name']) ?></span>
                                    <span class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($row['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <input type="number" 
                                   class="auto-save-grade w-16 text-center bg-slate-100 dark:bg-gray-800 border-none rounded-lg py-1.5 font-bold text-indigo-600 dark:text-indigo-400 focus:ring-2 focus:ring-indigo-500 outline-none transition-all"
                                   data-student-id="<?= $row['student_id'] ?>" 
                                   min="1" max="5" 
                                   value="<?= $row['grade'] ?>">
                        </td>
                        <td class="px-6 py-4">
                            <div class="relative">
                                <input type="text" 
                                       class="auto-save-comment w-full bg-transparent border-b border-transparent hover:border-slate-200 dark:hover:border-white/10 focus:border-indigo-500 outline-none text-sm py-1.5 transition-all text-slate-600 dark:text-slate-300 italic"
                                       data-student-id="<?= $row['student_id'] ?>" 
                                       placeholder="Shkruaj vlerësimin këtu..." 
                                       value="<?= htmlspecialchars($row['comment'] ?? '') ?>">
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right pr-6">
                            <div class="save-indicator opacity-0 transition-opacity duration-300" data-student-id="<?= $row['student_id'] ?>">
                                <span class="text-[10px] font-bold text-emerald-500 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded-md">RUJTUR</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Funksioni kryesor për Ruajtjen (Auto-Save)
function saveData(studentId, field, value) {
    const indicator = document.querySelector(`.save-indicator[data-student-id="${studentId}"]`);
    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append(field, value);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === "success") {
            indicator.style.opacity = "1";
            setTimeout(() => { indicator.style.opacity = "0"; }, 1500);
        }
    });
}

// Eventet për Notën (Ruaj kur ndryshon)
document.querySelectorAll('.auto-save-grade').forEach(input => {
    input.addEventListener('change', function() {
        saveData(this.dataset.studentId, 'grade', this.value);
    });
});

// Eventet për Komentin (Ruaj kur mbaron së shkruari - blur)
document.querySelectorAll('.auto-save-comment').forEach(input => {
    input.addEventListener('blur', function() {
        saveData(this.dataset.studentId, 'comment', this.value);
    });
    // Ose ruaj kur shtyp Enter
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.blur();
        }
    });
});

// Live Search me Highlight
document.getElementById('liveSearch').addEventListener('input', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#studentTableBody tr');

    rows.forEach(row => {
        let nameElement = row.querySelector('.student-name');
        let nameText = nameElement.textContent;
        
        if (nameText.toLowerCase().includes(filter)) {
            row.style.display = "";
            if(filter) {
                let regex = new RegExp(`(${filter})`, "gi");
                nameElement.innerHTML = nameText.replace(regex, `<mark class="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/30 dark:text-white rounded px-0.5">$1</mark>`);
            } else {
                nameElement.innerHTML = nameText;
            }
        } else {
            row.style.display = "none";
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../index.php';
?>