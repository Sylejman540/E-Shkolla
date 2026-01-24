<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../../db.php';

if ($_SESSION['user']['role'] !== 'school_admin') { http_response_code(403); exit; }

$stmt = $pdo->prepare("
INSERT INTO class_schedule
(school_id,user_id,class_id,day,period_number,subject_id,teacher_id,status)
VALUES (?,?,?,?,?,?,?,?)
");

try {
    $stmt->execute([
        $_SESSION['user']['school_id'],
        $_SESSION['user']['id'],
        $_POST['class_id'],
        $_POST['day'],
        $_POST['period_number'],
        $_POST['subject_id'],
        $_POST['teacher_id'],
        'active'
    ]);
} catch (PDOException $e) {}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;


?>

<div id="addScheduleForm" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto">
    
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        
        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 bg-slate-50/50">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Shto orar të ri</h2>
                <p class="mt-1 text-xs font-medium text-slate-500 uppercase tracking-wider">Cakto lëndët dhe mësuesit për klasat</p>
            </div>
            <button type="button" onclick="document.getElementById('addScheduleForm').classList.add('hidden')" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="" method="post" class="p-8">
            <input type="hidden" name="add_schedule" value="1">
            <input type="hidden" name="user_id" value="<?= $_SESSION['user']['id'] ?? '' ?>">
            
            <div class="grid grid-cols-1 gap-y-5 gap-x-4 sm:grid-cols-6">
                
                <div class="sm:col-span-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Dita e javës</label>
                    <select name="day" class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                        <option value="monday">E hënë</option>
                        <option value="tuesday">E martë</option>
                        <option value="wednesday">E mërkurë</option>
                        <option value="thursday">E enjte</option>
                        <option value="friday">E premte</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Ora e fillimit</label>
                    <input type="time" name="start_time" required class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Ora e përfundimit</label>
                    <input type="time" name="end_time" required class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Zgjidh Klasën</label>
                    <select name="class_id" class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                        <?php
                        $stmt_classes = $pdo->prepare("SELECT id, grade FROM classes WHERE school_id = ?");
                        $stmt_classes->execute([$schoolId]);
                        foreach ($stmt_classes as $class):
                        ?>
                            <option value="<?= $class['id'] ?>">Klasa <?= htmlspecialchars($class['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Lënda</label>
                    <select name="subject_id" id="f_subject" class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                        <?php
                        $stmt_subjects = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id = ?");
                        $stmt_subjects->execute([$schoolId]);
                        foreach ($stmt_subjects as $subject):
                        ?>
                            <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-4">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Mësuesi</label>
                    <select name="teacher_id" id="f_teacher" onchange="autoSelectSubject()" class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                        <option value="">Zgjidh Mësuesin</option>
                        <?php
                        $stmt_teachers = $pdo->prepare("SELECT id, name FROM teachers WHERE school_id = ?");
                        $stmt_teachers->execute([$schoolId]);
                        foreach ($stmt_teachers as $teacher):
                        ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-1.5">Statusi</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 bg-white text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all p-3 border">
                        <option value="active">Aktive</option>
                        <option value="inactive">Joaktive</option>
                    </select>
                </div>
            </div>

            <div class="mt-10 flex items-center justify-end gap-x-4 border-t border-slate-100 pt-8">
                <button type="button" id="closeModal" class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                    Anulo
                </button>
                <button type="submit" class="rounded-xl bg-blue-600 px-8 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500 hover:-translate-y-0.5 transition-all active:scale-95">
                    Ruaj Orarin
                </button>
            </div>
        </form>
    </div>
</div>