<?php
// 1. Core Security & Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Hardening Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// 2. Authorization Guard
// Redirect immediately if not logged in or not a student
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: /E-Shkolla/login");
    exit();
}

// 3. Application State & Helper Functions
$userId = $_SESSION['user']['id'];
$currentUri = $_SERVER['REQUEST_URI'];

function isActive($path) {
    global $currentUri;
    return str_contains($currentUri, $path);
}

// 4. Data Fetching (Scoped for the Layout)
$studentName = 'Nxënës';
try {
    require_once __DIR__ . '/../../db.php'; 
    $stmt = $pdo->prepare("SELECT name FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $fetchedName = $stmt->fetchColumn();
    if ($fetchedName) {
        $studentName = $fetchedName;
    }
} catch (PDOException $e) {
    // Log error internally, don't echo $e to user
    error_log("Database Error in Layout: " . $e->getMessage());
}

// Prepare the content variable (this will be populated by your route files)
// $content is expected to be defined by the file including this layout.
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Nxënësi</title>
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

<aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
       :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

    <a href="/E-Shkolla/student-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
        <img src="/E-Shkolla/images/icon.png" class="h-9 w-auto min-w-[36px]" alt="Logo">
        <div x-show="!sidebarCollapsed" x-transition.opacity class="ml-3 whitespace-nowrap">
            <h1 class="text-lg font-bold tracking-tight text-slate-800 leading-none">E-Shkolla</h1>
            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600 mt-1">Nxënës</p>
        </div>
    </a>

    <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
        <ul role="list" class="flex flex-1 flex-col gap-y-1.5">
            
            <li>
                <a href="/E-Shkolla/student-dashboard"
                   class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                   <?= isActive('/student-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Paneli</span>
                </a>
            </li>

            <li>
                <a href="/E-Shkolla/student-grades"
                   class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                   <?= isActive('/student-grades') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Notat e Mia</span>
                </a>
            </li>

            <li>
                <a href="/E-Shkolla/student-assignments"
                   class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                   <?= isActive('/student-assignments') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Detyrat e Mia</span>
                </a>
            </li>

            <li>
                <a href="/E-Shkolla/student-schedule"
                   class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                   <?= isActive('/student-schedule') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008ZM6.75 12h.008v.008H6.75V12Zm0 0h.008v.008H6.75V12Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Orari Mësimor</span>
                </a>
            </li>

            <div x-show="!sidebarCollapsed" class="px-3 mt-4 mb-2">
                <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Personalizimi</h3>
            </div>

            <li>
                <a href="/E-Shkolla/student-settings"
                   class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                   <?= isActive('/student-settings') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774a1.125 1.125 0 0 1 .12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.894.15c.542.09.94.56.94 1.109v1.094c0 .55-.398 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738a1.125 1.125 0 0 1-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.45.12l-.737-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.526c.351.25.807.272 1.204.108.397-.165.71-.505.78-.929l.15-.894Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Cilësimet</span>
                </a>
            </li>

            <div class="mt-auto space-y-1">
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
        
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between bg-white/80 backdrop-blur-md border-b border-slate-100 px-4 lg:px-8">
            <button @click="mobileOpen = true" class="p-2 lg:hidden text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <?php
                $studentName = 'Nxënës';
                // Logjika për marrjen e emrit të studentit nga tabela students
                if (isset($_SESSION['user']['id']) && $_SESSION['user']['role'] === 'student') {
                    require_once __DIR__ . '/../../db.php'; // Sigurohu që path është i saktë
                    $stmt = $pdo->prepare("SELECT name FROM students WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user']['id']]);
                    $studentName = $stmt->fetchColumn() ?: 'Nxënës';
                }
            ?>

            <div class="hidden lg:block">
                <p class="text-slate-500">Përshëndetje, <span class="font-semibold text-slate-800"><?= htmlspecialchars($studentName) ?></span></p>
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
                    <span class="hidden md:block text-sm font-semibold text-slate-700"><?= htmlspecialchars($studentName) ?></span>
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                        <?= strtoupper(substr(htmlspecialchars($studentName), 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-8 flex-1">
            <div class="max-w-7xl mx-auto">
                <?= $content ?? '<div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm text-center">Mirësevini në profilin tuaj, ' . htmlspecialchars($studentName) . '.</div>' ?>
            </div>
        </main>
    </div>

    <div class="fixed bottom-4 left-4 right-4 md:left-auto md:right-8 md:bottom-8 z-[100] flex flex-col gap-3 md:w-80" x-data="{ toasts: [] }">
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
</body>
</html>