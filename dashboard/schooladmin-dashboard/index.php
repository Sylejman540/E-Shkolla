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
<body class="h-full font-sans antialiased" x-data="{ 
    sidebarCollapsed: false, 
    mobileOpen: false,
    openMenus: { users: false, academy: false } 
}">

    <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

    <aside 
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
        :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/school-admin-dashboard" class="flex h-20 shrink-0 items-center px-4 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
              <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">School Admin</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-4">
                
                <li>
                    <a href="/E-Shkolla/school-admin-dashboard"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/school-admin-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/>
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Dashboard</span>
                    </a>
                </li>
                <li 
                  x-data="{
                    localOpen: localStorage.getItem('menu-users') === 'true',
                    toggle() {
                      this.localOpen = !this.localOpen
                      localStorage.setItem('menu-users', this.localOpen)
                    }
                  }">
                     <button
                      @click="sidebarCollapsed 
                        ? (sidebarCollapsed = false, localOpen = true, localStorage.setItem('menu-users', true)) 
                        : toggle()" class="w-full relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                        <svg class="h-6 w-6 stroke-[1.5] fill-none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                      </svg>
                        <span x-show="!sidebarCollapsed" class="flex-1 text-left">Përdoruesit</span>
                        <svg x-show="!sidebarCollapsed" :class="localOpen ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                      <ul
                      x-show="localOpen && !sidebarCollapsed"
                      x-cloak
                      x-transition
                      @click.stop
                      class="mt-1 ml-10 space-y-1">
                        <li><a href="/E-Shkolla/teachers" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Mësuesit</a></li>
                        <li><a href="/E-Shkolla/students" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Nxënësit</a></li>
                        <li><a href="/E-Shkolla/parents" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Prindërit</a></li>
                      </ul>
                </li>
                <li 
                  x-data="{
                    localOpen: localStorage.getItem('menu-academy') === 'true',
                    toggle() {
                      this.localOpen = !this.localOpen
                      localStorage.setItem('menu-academy', this.localOpen)
                    }
                  }">
                  <button
                  @click="sidebarCollapsed 
                    ? (sidebarCollapsed = false, localOpen = true, localStorage.setItem('menu-academy', true)) 
                    : toggle()" class="w-full relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                    <svg class="h-6 w-6 stroke-[1.5] fill-none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.174L12 4.5L19.74 10.174M4.26 10.174L12 15.848L19.74 10.174M4.26 10.174V15.5M19.74 10.174V15.5M12 15.848V21" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="flex-1 text-left">Akademia</span>
                    <svg x-show="!sidebarCollapsed" :class="localOpen ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </button>
                    <ul
                    x-show="localOpen && !sidebarCollapsed"
                    x-cloak
                    x-transition
                    @click.stop
                    class="mt-1 ml-10 space-y-1">
                        <li><a href="/E-Shkolla/classes" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Klasat</a></li>
                        <li><a href="/E-Shkolla/subjects" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Lëndët</a></li>
                        <li><a href="/E-Shkolla/schedule" class="block p-2 text-xs font-medium text-slate-500 hover:text-blue-600">Orari</a></li>
                    </ul>
                </li>

                <li>
                    <a href="/E-Shkolla/attendance" class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V15.848M4.26 10.174L12 4.5l7.74 5.674M4.26 10.174v5.326A1.5 1.5 0 005.76 17h12.48a1.5 1.5 0 001.5-1.5V10.174M4.26 10.174L12 15.848l7.74-5.674" />
                        </svg>
                        <span x-show="!sidebarCollapsed">Pjesëmarrja</span>
                    </a>
                </li>

                <div class="my-4 border-t border-slate-50"></div>

                <li>
                    <a href="/E-Shkolla/school-settings" class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-blue-600 transition-all">
                        <svg class="h-6 w-6 stroke-[1.5] fill-none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.004.828c.424.35.534.954.26 1.43l-1.297 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.332.183-.582.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378.138.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        </svg>
                        <span x-show="!sidebarCollapsed">Cilësimet</span>
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

    <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="h-full custom-transition">
        <div class="lg:hidden sticky top-0 flex items-center justify-between bg-white px-4 py-3 border-b border-slate-100 z-40">
            <a href="/E-Shkolla/school-admin-dashboard" class="flex h-20 shrink-0 items-center px-4 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
              <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">School Admin</p>
            </div>
            </a>
            <button @click="mobileOpen = true" class="p-2 text-slate-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>

        <main class="p-8">
            <h1 class="text-2xl font-bold text-slate-800">Mirësevini</h1>
        </main>
    </div>

</body>
</html>