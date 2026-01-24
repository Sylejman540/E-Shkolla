<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_admin') {
    header('Location: /login.php'); exit;
}

$schoolId = (int)$_SESSION['user']['school_id'];

/* Fetch data for selects */
$classes  = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id=?");
$subjects = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id=?");
$teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id=?");

$classes->execute([$schoolId]);
$subjects->execute([$schoolId]);
$teachers->execute([$schoolId]);

$classes  = $classes->fetchAll(PDO::FETCH_ASSOC);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
$teachers = $teachers->fetchAll(PDO::FETCH_ASSOC);

$classId = (int)($_GET['class_id'] ?? 0);

$days = [
    'monday'=>'E Hënë','tuesday'=>'E Martë','wednesday'=>'E Mërkurë',
    'thursday'=>'E Enjte','friday'=>'E Premte'
];

$periods = [1=>'Ora 1',2=>'Ora 2',3=>'Ora 3',4=>'Ora 4',5=>'Ora 5',6=>'Ora 6',7=>'Ora 7'];

ob_start();
?>
<style>
    :root { --primary: #2563eb; --bg: #f8fafc; --border: #e2e8f0; }
    .schedule-container { font-family: 'Inter', sans-serif; background: var(--bg); padding: 20px; border-radius: 12px; }
    
    /* Toolbar/Header */
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    select, button { padding: 0.6rem 1rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; }
    button { background: var(--primary); color: white; border: none; cursor: pointer; font-weight: 600; }
    button:hover { opacity: 0.9; }

    /* Form Design */
    .entry-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; background: #fff; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid var(--border); }

    /* Table Design */
    .timetable { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); table-layout: fixed; }
    .timetable th { background: #f1f5f9; padding: 1rem; color: #475569; font-weight: 600; text-align: center; border-bottom: 2px solid var(--border); }
    .timetable td { height: 100px; padding: 0.5rem; border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); vertical-align: top; }
    .timetable td:first-child { background: #f1f5f9; font-weight: 600; width: 80px; text-align: center; vertical-align: middle; }
    
    /* Lesson Card */
    .lesson-card { background: #eff6ff; border-left: 4px solid var(--primary); padding: 8px; border-radius: 4px; height: 100%; box-sizing: border-box; }
    .lesson-card b { display: block; color: #1e40af; font-size: 0.85rem; margin-bottom: 4px; }
    .lesson-card span { font-size: 0.75rem; color: #64748b; }
</style>

<div class="schedule-container">
    <div class="toolbar">
        <h2 style="margin:0;">📅 Orari Javor</h2>
        <form method="GET" id="classSelector">
            <select name="class_id" onchange="this.form.submit()" style="min-width: 200px;">
                <option value="">Zgjedh klasën...</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>>
                        Klasa <?= htmlspecialchars($c['grade']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($classId): ?>
    <form method="POST" action="/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/form.php" class="entry-form">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        
        <select name="day" required>
            <option value="">Dita</option>
            <?php foreach ($days as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>

        <select name="period_number" required>
            <option value="">Ora</option>
            <?php foreach ($periods as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>

        <select name="subject_id" required>
            <option value="">Lënda</option>
            <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= $s['subject_name'] ?></option><?php endforeach; ?>
        </select>

        <select name="teacher_id" required>
            <option value="">Mësimdhënësi</option>
            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= $t['name'] ?></option><?php endforeach; ?>
        </select>

        <button type="submit">➕ Shto në Orar</button>
    </form>

    <table class="timetable">
        <thead>
            <tr>
                <th></th>
                <?php foreach ($days as $d): ?><th><?= $d ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($periods as $pKey=>$pLabel): ?>
            <tr>
                <td><?= $pKey ?></td>
                <?php foreach ($days as $dayKey=>$dayLabel): ?>
                    <td data-day="<?= $dayKey ?>" data-period="<?= $pKey ?>"></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    fetch(`get-schedule-grid.php?class_id=<?= $classId ?>&school_id=<?= $schoolId ?>`)
    .then(r=>r.json())
    .then(data=>{
        Object.values(data.grid).forEach(day=>{
            Object.values(day).forEach(e=>{
                const cell = document.querySelector(`[data-day="${e.day}"][data-period="${e.period_number}"]`);
                if(cell) {
                    cell.innerHTML = `
                        <div class="lesson-card">
                            <b>${e.subject_name}</b>
                            <span>👤 ${e.teacher_name}</span>
                        </div>`;
                }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>