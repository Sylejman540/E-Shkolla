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
if ($classId !== null) $params['class_id'] = $classId;
if ($subjectId !== null) $params['subject_id'] = $subjectId;
$query = $params ? '?' . http_build_query($params) : '';

function isActive($path) {
    return str_contains($_SERVER['REQUEST_URI'], $path);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Menaxhimi i Klasës</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/E-Shkolla/images/icon.png" type="image/png">
    <style>
        [x-cloak] { display: none !important; }
        .custom-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* Indikatori blu për linjat aktive */
        .active-indicator::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: #2563eb;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900"
      x-data="{ sidebarCollapsed: false, mobileOpen: false, toasts: [] }">

    <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden">
    </div>

    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
           :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/teacher-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-9 w-auto min-w-[36px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition.opacity class="ml-3 whitespace-nowrap">
                <h1 class="text-lg font-bold tracking-tight text-slate-800 leading-none">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600 mt-1">Menaxhimi</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-1.5">
                
                <li>
                    <a href="/E-Shkolla/show-classes<?= $query ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/show-classes') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Pasqyra</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/class-attendance<?= $query ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/class-attendance') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Prezenca</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/class-assignments<?= $query ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/class-assignments') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Detyrat</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/class-grades<?= $query ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/class-grades') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Notat</span>
                    </a>
                </li>

                <div class="mt-auto pt-6 border-t border-slate-50">
                    <li>
                        <a href="/E-Shkolla/teacher-dashboard" class="group flex items-center gap-x-3 rounded-xl bg-blue-50/50 p-3 text-sm font-semibold text-blue-600 hover:bg-blue-50 transition-all">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 9l-3 3m0 0l3 3m-3-3h7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Kthehu te ballina</span>
                        </a>
                    </li>
                </div>
            </ul>
        </nav>

        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex items-center justify-center h-12 border-t border-slate-50 text-slate-400 hover:text-blue-600 transition-colors">
            <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </aside>

    <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="min-h-screen custom-transition flex flex-col">
        
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between bg-white/80 backdrop-blur-md border-b border-slate-100 px-4 lg:px-8">
            <button @click="mobileOpen = true" class="p-2 lg:hidden text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="hidden lg:block">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Detajet e Klasës</h2>
            </div>
            <div class="flex items-center gap-3">
                 <div class="h-9 w-9 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-black shadow-lg shadow-blue-200">P</div>
            </div>
        </header>

        <main class="p-4 lg:p-10 flex-1">
            <div class="max-w-7xl mx-auto">
                <?= $content ?? '<div class="flex flex-col items-center justify-center h-[60vh] text-slate-400 italic">Zgjidhni një kategori nga menuja...</div>' ?>
            </div>
        </main>
    </div>

    <div class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible" 
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-y-4 opacity-0 scale-95"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-end="opacity-0 scale-90"
                 class="pointer-events-auto flex items-center p-4 bg-white rounded-2xl shadow-2xl border border-slate-100 ring-1 ring-black/5">
                <div :class="toast.type === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'" class="p-2 rounded-xl mr-4 shrink-0">
                    <svg x-show="toast.type === 'success'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="toast.type === 'error'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <p class="text-sm font-bold text-slate-700" x-text="toast.message"></p>
            </div>
        </template>
    </div>

</body>
</html>