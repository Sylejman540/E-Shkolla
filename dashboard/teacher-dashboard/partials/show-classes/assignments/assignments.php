<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lidhja me bazën e të dhënave
require_once __DIR__ . '/../../../../../db.php';

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$userId   = (int) ($_SESSION['user']['id'] ?? 0);

if (!$schoolId || !$userId) {
    die('Sesion i pavlefshëm');
}

// Merr ID-në e mësuesit
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacherId = (int) $stmt->fetchColumn();

if (!$teacherId) {
    die('Mësuesi nuk u gjet');
}

// Merr detyrat dhe statistikat
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE school_id = ? AND teacher_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$schoolId, $teacherId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed
    FROM assignments
    WHERE school_id = ? AND teacher_id = ?
");
$stmt->execute([$schoolId, $teacherId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total     = (int) ($stats['total'] ?? 0);
$active    = (int) ($stats['active'] ?? 0);
$completed = (int) ($stats['completed'] ?? 0);

// Fillojmë kapjen e përmbajtjes për ta injektuar në index.php (Sidebar/Layout)
ob_start();
?>

<div class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight text-indigo-700">Detyrat e Shtëpisë</h1>
            <p class="mt-1 text-sm text-gray-500 italic">Menaxhoni ngarkesën mësimore për klasat tuaja.</p>
        </div>
        <button id="addAssignmentBtn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg hover:bg-indigo-500 transition-all hover:-translate-y-0.5 active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Krijo Detyrë
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="p-3 bg-gray-50 rounded-lg text-gray-400">#</div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Totali</p>
                <p class="text-2xl font-black text-gray-900"><?= $total ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-indigo-100 border-l-4 border-l-indigo-500 flex items-center gap-4">
            <div class="p-3 bg-indigo-50 rounded-lg text-indigo-500">●</div>
            <div>
                <p class="text-xs font-bold text-indigo-400 uppercase tracking-widest">Aktive</p>
                <p class="text-2xl font-black text-indigo-600"><?= $active ?></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-emerald-100 border-l-4 border-l-emerald-500 flex items-center gap-4">
            <div class="p-3 bg-emerald-50 rounded-lg text-emerald-500">✓</div>
            <div>
                <p class="text-xs font-bold text-emerald-400 uppercase tracking-widest">Përfunduara</p>
                <p class="text-2xl font-black text-emerald-600"><?= $completed ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-700">📋 LISTA E DETALJUAR</h2>
            <span class="text-xs text-gray-400 font-medium">Renditja: Më të fundit</span>
        </div>
        
        <div class="divide-y divide-gray-100">
            <?php if (empty($assignments)): ?>
                <div class="p-20 text-center">
                    <div class="text-4xl mb-4">✍️</div>
                    <p class="text-gray-400 italic">Nuk keni asnjë detyrë të regjistruar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $row): ?>
                    <div class="flex items-center justify-between p-6 hover:bg-gray-50/50 transition-colors group">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors uppercase tracking-tight">
                                    <?= htmlspecialchars($row['title']) ?>
                                </h3>
                                <?php if($row['completed_at']): ?>
                                    <span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-0.5 rounded-full font-bold">DONE</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500 line-clamp-2 max-w-3xl">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                            <div class="flex items-center gap-4 mt-3">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Afati: <?= date('d/m/Y', strtotime($row['due_date'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="deleteAssignment p-3 text-gray-300 hover:text-rose-600 hover:bg-rose-50 rounded-2xl transition-all opacity-0 group-hover:opacity-100 active:scale-90" data-id="<?= (int)$row['id'] ?>">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php require_once 'form.php'; ?>
</div>
<script>
// Menaxhimi i Formës
const btn = document.getElementById('addAssignmentBtn');
const form = document.getElementById('addAssignmentForm');
const cancel = document.getElementById('cancel');

btn?.addEventListener('click', () => {
  form.classList.remove('hidden');
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

cancel?.addEventListener('click', () => {
  form.classList.add('hidden');
});

// Fshirja me AJAX
document.addEventListener('click', function (e) {
    const deleteBtn = e.target.closest('.deleteAssignment');
    if (!deleteBtn) return;

    const id = deleteBtn.dataset.id;
    if (!id) return;

    if (!confirm('A jeni i sigurt që doni ta fshini këtë detyrë?')) return;

    fetch('/E-Shkolla/dashboard/teacher-dashboard/partials/show-classes/assignments/delete_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Animacion i thjeshtë gjatë largimit
            const row = deleteBtn.closest('.flex');
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                // Opsionale: rifresko faqen për të përditësuar numrat e statistikave
                // location.reload();
            }, 300);
        } else {
            alert(data.message || 'Fshirja dështoi');
        }
    })
    .catch(() => alert('Gabim në server'));
});
</script>
<?php
$content = ob_get_clean();
// Kjo siguron që kodi i mësipërm të shfaqet brenda strukturës tuaj kryesore
require_once __DIR__ . '/../index.php'; 
?>
</body>
</html>