<?php
$current = $_SERVER['REQUEST_URI'];

/**
 * Checks if the current URL matches the path.
 * We check if the URI ends with the path or contains it followed by a slash 
 */
function isActive($path) {
    $uri = $_SERVER['REQUEST_URI'];
    return (str_ends_with($uri, $path) || str_contains($uri, $path . '/'));
}

/**
 * Helper to check if any path in an array is active (used for dropdowns)
 */
function isAnyActive(array $paths) {
    foreach ($paths as $path) {
        if (isActive($path)) return true;
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
        
        /* The blue indicator for active links */
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
        
        /* Custom scrollbar for the nav */
        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900"
      x-data="{ 
        sidebarCollapsed: false, 
        mobileOpen: false, 
        helpOpen: false 
      }">

    <div x-show="mobileOpen || helpOpen" x-cloak x-transition.opacity 
         @click="mobileOpen = false; helpOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm">
    </div>

<template x-teleport="body">
    <div x-show="helpOpen" 
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         x-cloak
         class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl border-l border-slate-100 flex flex-col">
        
        <div class="flex items-center justify-between p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-600 text-white rounded-lg shadow-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Udhëzuesi i Operimeve</h2>
                    <p class="text-xs text-slate-500 font-medium">Ndiqni rregullat e sistemit</p>
                </div>
            </div>
            <button @click="helpOpen = false" class="text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-white transition-all">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="space-y-8">
                
                <section>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-blue-600 mb-4">Hierarkia e Regjistrimit</h3>
                    <div class="p-4 bg-blue-50/50 rounded-xl border border-blue-100 relative overflow-hidden">
                        <h4 class="font-bold text-blue-900 text-sm mb-3">Rradha strikte e veprimeve:</h4>
                        <ol class="text-xs text-blue-800 space-y-3 list-decimal ml-4 font-medium leading-relaxed">
                            <li><strong>Klasat:</strong> Krijoni klasat fillimisht.</li>
                            <li><strong>Mësuesit:</strong> Regjistroni mësuesit si përdorues.</li>
                            <li><strong>Kujdestaria:</strong> Te seksioni "Klasat", shtoni mësuesin kujdestar.</li>
                            <li><strong>Nxënësit:</strong> Regjistroni nxënësit në klasat përkatëse.</li>
                            <li><strong>Prindërit:</strong> Krijoni llogaritë e prindërve pas nxënësve.</li>
                            <li><strong>Orari:</strong> Gjeneroni orarin vetëm pasi të gjitha më lart janë kryer.</li>
                        </ol>
                    </div>
                </section>

                <section>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">Përdorimi i CSV</h3>
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <h4 class="font-bold text-slate-800 text-sm">Ku ndodhet CSV?</h4>
                            <p class="text-xs text-slate-500 mt-1">
                                Butoni i importit <strong>CSV</strong> është i pranishëm në pjesën e sipërme të çdo faqeje (Mësuesit, Nxënësit, etj).
                            </p>
                        </div>
                        
                        <div class="p-4 bg-amber-50 rounded-xl border border-amber-100 border-dashed">
                            <h4 class="font-bold text-amber-900 text-sm flex items-center gap-2">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                Jeni konfuz me formatin?
                            </h4>
                            <p class="text-xs text-amber-800 mt-1">
                                Nëse nuk jeni të sigurt si të plotësoni të dhënat, shkarkoni <strong>"Template-in CSV"</strong> që ekziston brenda dritares së importit në çdo faqe. Plotësoni atë dhe bëni upload përsëri.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="p-6 border-t border-slate-100 bg-white">
            <a href="mailto:support@e-shkolla.com" class="w-full flex items-center justify-center gap-2 py-3.5 px-4 bg-slate-900 text-white rounded-xl font-semibold hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                Na kontaktoni për asistencë
            </a>
        </div>
    </div>
</template>

    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
           :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/school-admin-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-9 w-auto min-w-[36px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition.opacity class="ml-3 whitespace-nowrap">
                <h1 class="text-lg font-bold tracking-tight text-slate-800 leading-none">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600 mt-1">School Admin</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-1.5">
                
                <li>
                    <a href="/E-Shkolla/school-admin-dashboard"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/school-admin-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>

                <li x-data="{ open: localStorage.getItem('menu-users') === 'true' || <?= isAnyActive(['/teachers', '/students', '/parents']) ? 'true' : 'false' ?> }">
                    <button @click="open = !open; localStorage.setItem('menu-users', open)" 
                            class="w-full group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="flex-1 text-left">Përdoruesit</span>
                        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul x-show="open && !sidebarCollapsed" x-cloak x-transition class="mt-1 ml-4 border-l-2 border-slate-100 space-y-1">
                        <li><a href="/E-Shkolla/teachers" class="block p-2 pl-6 text-xs font-medium <?= isActive('/teachers') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Mësuesit</a></li>
                        <li><a href="/E-Shkolla/students" class="block p-2 pl-6 text-xs font-medium <?= isActive('/students') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Nxënësit</a></li>
                        <li><a href="/E-Shkolla/parents" class="block p-2 pl-6 text-xs font-medium <?= isActive('/parents') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Prindërit</a></li>
                    </ul>
                </li>

                <li x-data="{ open: localStorage.getItem('menu-academy') === 'true' || <?= isAnyActive(['/classes', '/subjects', '/schedule']) ? 'true' : 'false' ?> }">
                    <button @click="open = !open; localStorage.setItem('menu-academy', open)" 
                            class="w-full group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.246.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="flex-1 text-left">Akademia</span>
                        <svg x-show="!sidebarCollapsed" :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <ul x-show="open && !sidebarCollapsed" x-cloak x-transition class="mt-1 ml-4 border-l-2 border-slate-100 space-y-1">
                        <li><a href="/E-Shkolla/classes" class="block p-2 pl-6 text-xs font-medium <?= isActive('/classes') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Klasat</a></li>
                        <li><a href="/E-Shkolla/subjects" class="block p-2 pl-6 text-xs font-medium <?= isActive('/subjects') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Lëndët</a></li>
                        <li><a href="/E-Shkolla/schedule" class="block p-2 pl-6 text-xs font-medium <?= isActive('/schedule') ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Orari</a></li>
                    </ul>
                </li>

                <li>
                    <a href="/E-Shkolla/school-settings"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/school-settings') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Cilësimet</span>
                    </a>
                </li>

                <div class="mt-auto space-y-1">
                    <li>
                        <button @click="helpOpen = true" 
                                class="w-full group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-blue-50 hover:text-blue-600 transition-all">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Ndihmë</span>
                        </button>
                    </li>

                    <li>
                        <a href="/E-Shkolla/logout" class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition-all">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity class="whitespace-nowrap">Çkyçu</span>
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
        
        <header class="lg:hidden sticky top-0 z-30 bg-white border-b border-slate-100 h-16 shrink-0">
            <div class="flex items-center justify-between px-4 h-full">
                <a href="/E-Shkolla/school-admin-dashboard" class="flex items-center gap-2">
                    <img src="/E-Shkolla/images/icon.png" class="h-8 w-8" alt="Logo">
                    <span class="text-sm font-bold text-slate-800">E-Shkolla</span>
                </a>
                <button @click="mobileOpen = true" class="p-2 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </header>

        <main class="p-6 lg:p-10 pt-24 lg:pt-10 flex-1">
            <?= $content ?? '<div class="flex flex-col items-center justify-center h-full text-slate-400"><p class="italic">Së shpejti...</p></div>' ?>
        </main>
    </div>

</body>
</html>