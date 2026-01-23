<?php
require_once __DIR__ . '/../../../../db.php';

$classId = $_GET['class_id'] ?? null;
if (!$classId) die("ID e klasës mungon.");

$stmt = $pdo->prepare("
    SELECT cs.*, s.subject_name, t.name as teacher_name 
    FROM class_schedule cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN teachers t ON cs.teacher_id = t.id
    WHERE cs.class_id = ? 
    ORDER BY FIELD(cs.day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'), cs.start_time ASC
");
$stmt->execute([$classId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days_map = ['monday' => 'HËN', 'tuesday' => 'MAR', 'wednesday' => 'MËR', 'thursday' => 'ENJ', 'friday' => 'PRE'];
$grid = [];
foreach ($entries as $entry) {
    $grid[$entry['day']][] = $entry;
}
$max_slots = 7;
?>

<style>
    .schedule-grid-container {
        display: grid;
        grid-template-columns: 80px repeat(<?= $max_slots ?>, minmax(140px, 1fr));
        gap: 8px;
        padding: 16px;
        user-select: none !important;
        -webkit-user-select: none !important;
    }

    .grid-header {
        text-align: center;
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding-bottom: 8px;
    }

    .day-label {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        border: 1px solid #f1f5f9;
    }

    .drop-zone {
        height: 100px;
        background: rgba(248, 250, 252, 0.5);
        border: 2px dashed #e2e8f0;
        border-radius: 14px;
        position: relative;
        transition: all 0.2s ease;
    }

    .drop-zone.drag-over {
        background: #eef2ff;
        border-color: #6366f1;
    }

    .draggable-card {
        position: absolute;
        inset: 4px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        cursor: grab;
        z-index: 50;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .draggable-card:active { cursor: grabbing; }

    /* Parandalon markimin e tekstit brenda kartës */
    .draggable-card * { pointer-events: none; }
    .delete-btn { pointer-events: auto !important; }

    .sortable-ghost { opacity: 0; } /* Fsheh kartën origjinale gjatë drag-it */
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<div class="schedule-grid-container bg-white dark:bg-slate-900 rounded-b-2xl overflow-x-auto">
    <div></div>
    <?php for($i=1; $i<=$max_slots; $i++): ?>
        <div class="grid-header">Ora <?= $i ?></div>
    <?php endfor; ?>

    <?php foreach ($days_map as $dayKey => $dayLabel): ?>
        <div class="day-label"><?= $dayLabel ?></div>
        <?php for($i=0; $i < $max_slots; $i++): ?>
            <div class="drop-zone" data-day="<?= $dayKey ?>" data-slot="<?= $i ?>">
                <?php if (isset($grid[$dayKey][$i])): 
                    $item = $grid[$dayKey][$i]; ?>
                    <div class="draggable-card group" data-id="<?= $item['id'] ?>">
                        <div class="text-[11px] font-bold text-indigo-600 leading-tight">
                            <?= htmlspecialchars($item['subject_name']) ?>
                        </div>
                        <div class="text-[10px] text-slate-500 mt-1">
                            <?= htmlspecialchars($item['teacher_name']) ?>
                        </div>
                        
                        <button onclick="deleteScheduleEntry(<?= $item['id'] ?>, <?= $classId ?>)" 
                                class="delete-btn absolute -top-2 -right-2 opacity-0 group-hover:opacity-100 bg-rose-500 text-white p-1 rounded-full shadow-lg transition-all">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    <?php endforeach; ?>
</div>

<script>
(function() {
    const zones = document.querySelectorAll('.drop-zone');
    
    zones.forEach(zone => {
        new Sortable(zone, {
            group: 'schedule_app',
            animation: 200,
            delay: 100, // Vonesë e shtuar për të evituar markimin blu
            delayOnTouchOnly: false,
            swapThreshold: 1,
            draggable: '.draggable-card',
            ghostClass: 'sortable-ghost',
            
            onStart: function() {
                document.body.classList.add('dragging-active');
            },

            onEnd: async function (evt) {
                document.body.classList.remove('dragging-active');
                
                if (evt.to === evt.from) return;

                const cardId = evt.item.getAttribute('data-id');
                const newDay = evt.to.getAttribute('data-day');

                try {
                    const res = await fetch('/E-Shkolla/dashboard/schooladmin-dashboard/partials/schedule/update-position.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: cardId, day: newDay })
                    });
                    const data = await res.json();
                    if(data.success) showToast("U ruajt me sukses");
                } catch (e) {
                    showToast("Gabim gjatë komunikimit", "error");
                }
            }
        });
    });
})();
</script>