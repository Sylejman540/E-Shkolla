<?php
// 1. Siguria dhe Menaxhimi i Sesionit
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authorization Guard
if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'parent') {
    header("Location: /E-Shkolla/login");
    exit();
}

require_once __DIR__ . '/../../db.php';
$userId = $_SESSION['user']['id'];
$currentUri = $_SERVER['REQUEST_URI'];

function isActive($path) {
    global $currentUri;
    return str_contains($currentUri, $path);
}

// Marrja e të dhënave të prindit
$parentName = 'Prind';
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $parentName = $stmt->fetchColumn() ?: 'Prind';
} catch (PDOException $e) {
    error_log("Database Error in Layout: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Prindi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/E-Shkolla/images/icon.png" type="image/png">
    <style>
        [x-cloak] { display: none !important; }
        .custom-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .active-indicator::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: #4f46e5;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900" 
      x-data="{ sidebarCollapsed: false, mobileOpen: false }">

    <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
           :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/parent-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-10 w-auto min-w-[40px] object-contain" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition class="ml-3 whitespace-nowrap">
                <h1 class="text-xl font-bold tracking-tight text-slate-800">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600">Prindër</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-2">
                
                <?php
                $menuItems = [
                    ['url' => '/parent-dashboard', 'label' => 'Paneli', 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
                    ['url' => '/parent-children', 'label' => 'Fëmijët e Mi', 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
                    ['url' => '/parent-attendance', 'label' => 'Prezenca', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                    ['url' => '/parent-grades', 'label' => 'Notat', 'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25'],
                    ['url' => '/parent-assignments', 'label' => 'Detyrat', 'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25']
                ];

                foreach ($menuItems as $item): ?>
                <li>
                    <a href="/E-Shkolla<?= $item['url'] ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive($item['url']) ? 'bg-indigo-50 text-indigo-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-indigo-600' ?>">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap"><?= $item['label'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>

                <li class="mt-auto pt-4 border-t border-slate-50">
                    <a href="/E-Shkolla/logout" class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-100 transition-all">
                        <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        <span x-show="!sidebarCollapsed || mobileOpen" class="whitespace-nowrap">Çkyçu</span>
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

            <div class="hidden lg:block">
                <p class="text-slate-500">Mirëseerdhët, Prind: <span class="font-semibold text-slate-800"><?= htmlspecialchars($parentName) ?></span></p>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 pl-4 border-l border-slate-100">
                    <span class="hidden md:block text-sm font-semibold text-slate-700"><?= htmlspecialchars($parentName) ?></span>
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                        <?= strtoupper(substr(htmlspecialchars($parentName), 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-8 flex-1">
            <div class="max-w-7xl mx-auto">
                <?= $content ?? '<div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">Përmbajtja nuk u gjet.</div>' ?>
            </div>
        </main>
    </div>
</body>
</html>