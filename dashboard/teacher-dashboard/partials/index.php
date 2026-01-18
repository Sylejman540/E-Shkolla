<?php
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
    <title>E-Shkolla | School Admin</title>
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

    <!-- Mobile overlay -->
    <div
        x-show="mobileOpen"
        x-cloak
        @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden">
    </div>

    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
        :class="[
            sidebarCollapsed ? 'w-20' : 'w-72',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">

        <a href="/E-Shkolla/school-admin-dashboard" class="flex h-20 shrink-0 items-center px-4 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
              <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Teacher</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-4">
                
                <li>
                    <a href="/E-Shkolla/teacher-dashboard"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>
                                <li>
                    <a href="/E-Shkolla/teacher-classes"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-classes') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Klasat e mia</span>
                    </a>
                </li>
                <li>
                    <a href="/E-Shkolla/teacher-schedule"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-schedule') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Orari</span>
                    </a>
                </li>

                <li class="mt-auto">
                    <a href="/E-Shkolla/logout" class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all">
                        <svg class="h-6 w-6 shrink-0 custom-transition group-hover:scale-110" 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke-width="1.8" 
                            stroke="currentColor">
                            <path stroke-linecap="round" 
                                stroke-linejoin="round" 
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>

                        <span x-show="!sidebarCollapsed" 
                            x-transition.opacity
                            class="whitespace-nowrap">
                            Çkyçu nga Sistemi
                        </span>
                    </a>
                </li>
            </ul>
        </nav>

        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex items-center justify-center h-12 border-t border-slate-100 text-slate-400 hover:text-slate-600">
            <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="h-5 w-5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 19l-7-7 7-7m8 14l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </aside>

<!-- WRAPPER -->
<div
    x-data="{ sidebarCollapsed: false, mobileOpen: false }"
    class="min-h-screen bg-slate-50"
>

    <!-- MOBILE TOP BAR -->
    <header class="lg:hidden fixed top-0 inset-x-0 z-50 bg-white border-b border-slate-100">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="/E-Shkolla/school-admin-dashboard" class="flex items-center gap-3">
                <img src="/E-Shkolla/images/icon.png" class="h-8 w-8" alt="Logo">
                <div>
                    <h1 class="text-sm font-bold text-slate-800">E-Shkolla</h1>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-400">
                        School Admin
                    </p>
                </div>
            </a>

            <button @click="mobileOpen = true" class="p-2 text-slate-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- DESKTOP CONTENT WRAPPER -->
    <div
        :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'"
        class="pt-16 lg:pt-0 transition-all duration-300"
    >
        <main class="p-6 lg:p-8">
            <?= $content ?? '' ?>
        </main>
    </div>

    <!-- MOBILE SIDEBAR OVERLAY -->
    <div
        x-show="mobileOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        @click="mobileOpen = false"
    ></div>

    <!-- MOBILE SIDEBAR -->
    <aside
        x-show="mobileOpen"
        x-transition
        @click.outside="mobileOpen = false"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl lg:hidden"
    >
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

    <!-- Mobile overlay -->
    <div
        x-show="mobileOpen"
        x-cloak
        @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden">
    </div>

    <!-- SIDEBAR -->
    <!-- SIDEBAR -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
        :class="[
            sidebarCollapsed ? 'w-20' : 'w-72',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">

        <a href="/E-Shkolla/school-admin-dashboard" class="flex h-20 shrink-0 items-center px-4 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
              <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Teacher</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-4">
                
                <li>
                    <a href="/E-Shkolla/teacher-dashboard"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>
                                <li>
                    <a href="/E-Shkolla/teacher-classes"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-classes') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Klasat e mia</span>
                    </a>
                </li>
                <li>
                    <a href="/E-Shkolla/teacher-schedule"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-schedule') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Orari</span>
                    </a>
                </li>

                <li class="mt-auto">
                    <a href="/E-Shkolla/logout" class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all">
                        <svg class="h-6 w-6 shrink-0 custom-transition group-hover:scale-110" 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke-width="1.8" 
                            stroke="currentColor">
                            <path stroke-linecap="round" 
                                stroke-linejoin="round" 
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>

                        <span x-show="!sidebarCollapsed" 
                            x-transition.opacity
                            class="whitespace-nowrap">
                            Çkyçu nga Sistemi
                        </span>
                    </a>
                </li>
            </ul>
        </nav>

        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex items-center justify-center h-12 border-t border-slate-100 text-slate-400 hover:text-slate-600">
            <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="h-5 w-5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 19l-7-7 7-7m8 14l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </aside>

</div>


</body>
</html>