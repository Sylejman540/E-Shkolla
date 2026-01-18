<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/../../../../db.php';

$schoolId = $_SESSION['user']['school_id'] ?? null;

// SQL i përmirësuar: Marrim të dhënat më të fundit nga subjects dhe mësuesit
$stmt = $pdo->prepare("
    SELECT s.*, t.name as teacher_name 
    FROM subjects s
    LEFT JOIN teachers t ON s.user_id = t.user_id
    WHERE s.school_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->execute([$schoolId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start(); 
?>

<div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
        <div class="mt-5 sm:flex-auto">
            <h1 class="text-xl sm:text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                Lëndët e Shkollës
            </h1>
            <p class="mt-2 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">
                Kjo listë gjenerohet automatikisht bazuar në mësuesit e shtuar. Ndryshimet te mësuesit reflektohen këtu menjëherë.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="/E-Shkolla/teachers?open_form=1" class="inline-block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
                Shto Mësues/Lëndë të Re
            </a>
        </div>
    </div>

    <div class="mt-6 mb-4 flex items-center gap-2">
        <div class="relative w-full sm:w-64">
            <input id="subjectSearch" type="text" placeholder="Kërko lëndën ose mësuesin..." 
                   class="w-full pl-10 pr-4 py-2 rounded-lg border text-sm dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none border-gray-300 dark:border-white/10"
                   oninput="filterSubjects()">
            <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-white/10">
                    <thead>
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-0">Emri i Lëndës</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Mësimdhënësi Përgjegjës</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Statusi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5" id="subjectsTableBody">
                        <?php if(!empty($subjects)): ?>
                            <?php foreach ($subjects as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="py-4 pl-4 pr-3 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white sm:pl-0">
                                    <span class="searchable-text"><?= htmlspecialchars($row['subject_name']) ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="searchable-text font-medium"><?= htmlspecialchars($row['name'] ?? 'I pacaktuar') ?></span>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Nuk u gjet asnjë lëndë.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
require_once __DIR__ . '/../../index.php'; 
?>

<script>
// Funksioni i kërkimit i sinkronizuar me modulet e tjera (me Highlight)
function filterSubjects() {
    const input = document.getElementById("subjectSearch");
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll("#subjectsTableBody tr");

    rows.forEach(row => {
        const searchableElements = row.querySelectorAll(".searchable-text");
        let matchFound = false;
        
        searchableElements.forEach(el => {
            // Ruajmë tekstin origjinal për të mos humbur të dhënat gjatë zëvendësimit të HTML
            const originalContent = el.getAttribute('data-original') || el.innerText;
            if(!el.getAttribute('data-original')) el.setAttribute('data-original', originalContent);

            if (originalContent.toLowerCase().includes(filter)) {
                matchFound = true;
                if (filter !== "") {
                    const regex = new RegExp(`(${filter})`, "gi");
                    el.innerHTML = originalContent.replace(regex, "<mark class='bg-yellow-200 dark:bg-yellow-500/30 dark:text-white rounded-sm'>$1</mark>");
                } else {
                    el.innerText = originalContent;
                }
            } else {
                el.innerText = originalContent;
            }
        });

        row.style.display = matchFound ? "" : "none";
    });
}
</script>