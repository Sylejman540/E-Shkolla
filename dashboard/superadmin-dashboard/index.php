<?php
$current = $_SERVER['REQUEST_URI'];

function isActive($path) {
    return str_contains($_SERVER['REQUEST_URI'], $path);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/E-Shkolla/images/icon.png" type="image/png">
    <style>
        [x-cloak] { display: none !important; }
        .text-brand-blue { color: #2563eb; }
        .bg-brand-blue { background-color: #2563eb; }
        .bg-brand-light { background-color: #eff6ff; }
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
<body class="h-full font-sans antialiased" x-data="{ sidebarCollapsed: false, mobileOpen: false, activeMenu: 'schools' }">

    <div x-show="mobileOpen" 
         x-cloak
         @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden transition-opacity"></div>
<div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="h-full custom-transition">

    <aside 
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
        :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/super-admin-dashboard" class="flex h-20 shrink-0 items-center px-4 overflow-hidden">
            <img src="/E-Shkolla/images/icon.png" class="h-12 w-auto min-w-[48px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
                <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Sistemi i Menaxhimit</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-4 custom-scrollbar">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    <ul role="list" class="space-y-1">
                        <li>
                            <a href="/E-Shkolla/super-admin-dashboard"
                            class="relative group flex gap-x-3 rounded-2xl p-3 text-sm font-semibold leading-6 transition-all
                            <?= isActive('/super-admin-dashboard')
                                    ? 'bg-blue-50 text-blue-600 active-indicator'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'
                            ?>">

                                <?php if (isActive('/super-admin-dashboard')): ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                     d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/>
                                    </svg>
                                <?php endif; ?>

                                <span x-show="!sidebarCollapsed">Dashboard</span>
                            </a>
                        </li>
                        
                        <li x-data="{ open: true }">
                            <a href="/E-Shkolla/super-admin-schools"
                            class="relative group flex gap-x-3 rounded-2xl p-3 text-sm font-semibold leading-6 transition-all
                            <?= isActive('/super-admin-schools')
                                    ? 'bg-blue-50 text-blue-600 active-indicator'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'
                            ?>">

                                <?php if (isActive('/super-admin-schools')): ?>
                                    <svg x-show="activeMenu === 'schools'" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.58 2.387a.75.75 0 01.84 0l8.25 5.25a.75.75 0 010 1.253l-8.25 5.25a.75.75 0 01-.84 0l-8.25-5.25a.75.75 0 010-1.253l8.25-5.25zM3.375 11.25l7.5 4.773a2.25 2.25 0 002.25 0l7.5-4.773v5.07c0 .243-.102.477-.28.646l-8.003 7.62a.75.75 0 01-1.034 0l-8.003-7.62a.976.976 0 01-.28-.647V11.25z" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path d="M12 3l9 6-9 6-9-6 9-6z"/>
                                    </svg>
                                <?php endif; ?>

                                <span x-show="!sidebarCollapsed">Shkollat</span>
                            </a>
                        </li>

                        <li>
                            <a href="/E-Shkolla/super-admin-users"
                            class="relative group flex gap-x-3 rounded-2xl p-3 text-sm font-semibold leading-6 transition-all
                            <?= isActive('/super-admin-users')
                                    ? 'bg-blue-50 text-blue-600 active-indicator'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'
                            ?>">

                                <?php if (isActive('/super-admin-users')): ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10z"/>
                                        <path d="M2 22a10 10 0 0120 0z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10z"/>
                                        <path d="M2 22a10 10 0 0120 0z"/>
                                    </svg>
                                <?php endif; ?>

                                <span x-show="!sidebarCollapsed">Përdoruesit</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li x-show="!sidebarCollapsed">
                    <div class="px-3 text-[10px] font-bold uppercase tracking-[2px] text-slate-300">Sistemi</div>
                    <ul role="list" class="mt-2 space-y-1">
                        <li>
                            <a href="/E-Shkolla/settings"
                            class="relative group flex gap-x-3 rounded-2xl p-3 text-sm font-semibold leading-6 transition-all
                            <?= isActive('/settings')
                                    ? 'bg-blue-50 text-blue-600 active-indicator'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600'
                            ?>">
                                <?php if (isActive('/settings')): ?>
                                    <svg x-show="activeMenu === 'settings'" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 00-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 00-2.282.819l-.922 1.597a1.875 1.875 0 00.432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 000 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 00-.432 2.385l.922 1.597a1.875 1.875 0 002.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 002.28-.819l.923-1.597a1.875 1.875 0 00-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 000-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 00-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 00-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 00-1.85-1.567h-1.843zM12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" clip-rule="evenodd" />
                                    </svg>
                                <?php else: ?>
                                    <svg x-show="activeMenu !== 'settings'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.004.828c.424.35.534.954.26 1.43l-1.297 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.332.183-.582.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378.138.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    </svg>
                                <?php endif; ?>

                                <span x-show="!sidebarCollapsed">Settings</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="mt-auto px-2 pb-4">
                    <a href="/E-Shkolla/logout" 
                    class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all duration-200"
                    :class="sidebarCollapsed ? 'justify-center px-0' : ''">
                        
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

    <div class="lg:hidden fixed top-0 left-0 right-0 flex items-center justify-between bg-white px-4 py-3 border-b border-slate-100 z-50">

        <a href="/E-Shkolla/super-admin-dashboard" class="flex h-12 shrink-0 items-center overflow-hidden">
            <img src="/E-Shkolla/images/icon.png" class="h-6 w-auto min-w-[48px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
                <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Sistemi i Menaxhimit</p>
            </div>
        </a>
        <button @click="mobileOpen = true" class="p-2 text-slate-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
    </div>

    <div :class="sidebarCollapsed ? '' : ''" class="h-full custom-transition">
        <main class="h-full p-8 pt-20 lg:pt-8">
            <?= $content ?? '' ?>
            </main>
    </div>

</body>
</html>