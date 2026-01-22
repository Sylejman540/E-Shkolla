<?php
$subjectId = null;
$classId   = null;

if (isset($_GET['class_id']) && $_GET['class_id'] !== '') {
    $classId = (int)$_GET['class_id'];
}

if (isset($_GET['subject_id']) && $_GET['subject_id'] !== '') {
    $subjectId = (int)$_GET['subject_id'];
}

$params = [];

if ($classId !== null) {
    $params['class_id'] = $classId;
}

if ($subjectId !== null) {
    $params['subject_id'] = $subjectId;
}

$query = $params ? '?' . http_build_query($params) : '';

$current = $_SERVER['REQUEST_URI'];
function isActive($path) {
    return str_contains($_SERVER['REQUEST_URI'], $path);
}

function isAnyActive(array $paths) {
    foreach ($paths as $path) {
        if (str_contains($_SERVER['REQUEST_URI'], $path)) {
            return true;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Mësues</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/E-Shkolla/images/icon.png" type="image/png">
    <style>
        [x-cloak] { display: none !important; }
        .custom-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .active-indicator::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: #2563eb;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body class="h-full font-sans antialiased"
      x-data="{ sidebarCollapsed: false, mobileOpen: false }">

    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.opacity
        @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden">
    </div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
        :class="[
            sidebarCollapsed ? 'w-20' : 'w-72',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">

        <a href="/E-Shkolla/teacher-dashboard" class="flex h-20 shrink-0 items-center px-5 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
              <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
              <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600">Mësues</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-2">
                <li>
                    <a href="/E-Shkolla/head-class<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/head-class') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>

                <li>
                  <a href="/E-Shkolla/class-head-attendance<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/class-head-attendance') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Prezenca</span>
                    </a>
                </li>
                

                <li>
                    <a href="/E-Shkolla/class-head-assignments<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/class-head-assignments') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Detyrat</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/class-head-grades<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/class-head-grades') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Notat</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/teacher-head-parent<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/teacher-head-parent') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a.75.75 0 00.75-.75V11a3 3 0 00-3-3h-1.5m-9 10.72a.75.75 0 01-.75-.75V11a3 3 0 013-3h1.5m-3 10.72V19.5a2.25 2.25 0 002.25 2.25h6a2.25 2.25 0 002.25-2.25v-1.356m-10.5 0h10.5m-9-3.433c-1.112.086-2.221.242-3.32.473L3 16.485m3.68-1.196L3.68 19.5m16.64-1.196L17.32 19.5m0-3.433c1.112.086 2.221.242 3.32.473L21 16.485M12 3.75a.75.75 0 110 1.5.75.75 0 010-1.5zm0 0v-1.5m0 9a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Prindërit</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/teacher-head-notices<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/teacher-head-notices') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Njoftimet</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/teacher-head-settings<?= $query ?>&type=header"
                    class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                    <?= isActive('/teacher-head-settings') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774a1.125 1.125 0 0 1 .12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.894.15c.542.09.94.56.94 1.109v1.094c0 .55-.398 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738a1.125 1.125 0 0 1-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.45.12l-.737-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.526c.351.25.807.272 1.204.108.397-.165.71-.505.78-.929l.15-.894Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Cilësimet</span>
                    </a>
                </li>

                <li class="mt-auto">
                    <a href="/E-Shkolla/teacher-dashboard" class="group flex items-center gap-x-3 rounded-xl bg-blue-50/50 p-3 text-sm font-semibold text-blue-600 hover:bg-blue-100 transition-all">
                        <svg class="h-6 w-6 shrink-0 custom-transition group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 9l-3 3m0 0l3 3m-3-3h7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Kthehu te ballina</span>
                    </a>
                </li>
            </ul>
        </nav>

        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex items-center justify-center h-12 border-t border-slate-100 text-slate-400 hover:text-slate-600">
            <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="h-5 w-5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 19l-7-7 7-7m8 14l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </aside>

 <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="min-h-screen custom-transition flex flex-col">
        
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between bg-white/80 backdrop-blur-md border-b border-slate-100 px-4 lg:px-8">
            <button @click="mobileOpen = true" class="p-2 lg:hidden text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <?php
                $teacherName = 'Profesor';

                if ($_SESSION['user']['role'] === 'teacher') {
                    $stmt = $pdo->prepare("SELECT name FROM teachers WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user']['id']]);
                    $teacherName = $stmt->fetchColumn() ?: 'Profesor';
                }
            ?>
            <div class="hidden lg:block">
                <p class="text-slate-500">Mirëseerdhe, Prof. <?= htmlspecialchars($teacherName) ?></p>

            </div>

            <div class="flex items-center gap-2 lg:gap-4">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-2 text-slate-400 hover:text-blue-600 relative transition-colors">
                        <span class="absolute top-2 right-2 flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition x-cloak
                         class="absolute right-0 mt-3 w-72 md:w-80 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4">
                        <h3 class="font-bold text-slate-800 mb-4">Njoftimet</h3>
                        <div class="text-xs text-slate-500 text-center py-4 italic">Nuk ka njoftime të reja</div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pl-2 lg:pl-4 border-l border-slate-100">
                    <span class="hidden md:block text-sm font-semibold text-slate-700"><?= htmlspecialchars($teacherName) ?></span>
                    <div class="h-9 w-9 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                        <?= strtoupper(substr(htmlspecialchars($teacherName) ?? 'M', 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-8 flex-1">
            <div class="max-w-7xl mx-auto">
                <?= $content ?? '<div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm text-center">Dashboard content goes here.</div>' ?>
            </div>
        </main>
    </div>

    <div class="fixed bottom-4 left-4 right-4 md:left-auto md:right-8 md:bottom-8 z-[100] flex flex-col gap-3 md:w-80">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible" 
                 x-transition:enter="transition transform duration-300"
                 x-transition:enter-start="translate-y-12 opacity-0"
                 x-transition:leave="transition transform duration-200"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="flex items-center p-4 bg-white rounded-2xl shadow-xl border border-slate-100 ring-1 ring-black/5">
                <div :class="toast.type === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'" class="p-2 rounded-xl mr-3">
                    <svg x-show="toast.type === 'success'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="toast.type === 'error'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <p class="text-sm font-bold text-slate-700" x-text="toast.message"></p>
            </div>
        </template>
    </div>
</div>
</body>
</html> 