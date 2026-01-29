<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'parent') {
    header("Location: /E-Shkolla/login");
    exit();
}

require_once __DIR__ . '/../../db.php';

$userId     = (int) $_SESSION['user']['id'];
$schoolId   = (int) ($_SESSION['user']['school_id'] ?? 0);
$currentUri = $_SERVER['REQUEST_URI'];

function isActive($path) {
    global $currentUri;
    return str_contains($currentUri, $path);
}

// ===============================
// Parent name
// ===============================
$parentName = 'Prind';

try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $parentName = $stmt->fetchColumn() ?: 'Prind';
} catch (PDOException $e) {
    error_log('Parent layout name error: ' . $e->getMessage());
}

// ===============================
// Parent notifications
// ===============================
$userNotifications = [];

try {
    $stmt = $pdo->prepare("
        SELECT title, content, created_at
        FROM announcements
        WHERE school_id = ?
          AND target_role IN ('all', 'parent')
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$schoolId]);
    $userNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Parent layout notifications error: ' . $e->getMessage());
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
        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900" 
      x-data="{ sidebarCollapsed: false, mobileOpen: false, helpOpen: false }">

    <div x-show="mobileOpen || helpOpen" x-cloak x-transition.opacity @click="mobileOpen = false; helpOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm"></div>

    <template x-teleport="body">
        <div x-show="helpOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-end="translate-x-full"
             x-cloak
             class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl border-l border-slate-100 flex flex-col">
            <div class="flex items-center justify-between p-6 border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 text-white rounded-lg">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800">Ndihmë për Prindërit</h2>
                </div>
                <button @click="helpOpen = false" class="p-2 rounded-full hover:bg-slate-100"><svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto p-8 text-sm text-slate-600 space-y-6">
                <section><h3 class="font-bold text-slate-800 mb-2">Monitorimi i Fëmijëve</h3><p>Këtu mund të shihni progresin akademik, notat dhe prezencën për të gjithë fëmijët tuaj të regjistruar në shkollë.</p></section>
                <section><h3 class="font-bold text-slate-800 mb-2">Detyrat e Shtëpisë</h3><p>Ju mund të kontrolloni detyrat e dhëna nga mësuesit për të ndihmuar fëmijët në përgatitjen e tyre.</p></section>
            </div>
        </div>
    </template>

    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
           :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/parent-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-9 w-auto min-w-[36px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition.opacity class="ml-3 whitespace-nowrap">
                <h1 class="text-lg font-bold tracking-tight text-slate-800 leading-none">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 mt-1">Prindër</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-1.5">
                
                <?php
                $menuItems = [
                    ['url' => '/parent-dashboard', 'label' => 'Pasqyra', 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
                    ['url' => '/parent-children', 'label' => 'Fëmijët e Mi', 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
                    ['url' => '/parent-attendance', 'label' => 'Prezenca', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                    ['url' => '/parent-grades', 'label' => 'Notat', 'icon' => 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75'],
                    ['url' => '/parent-assignments', 'label' => 'Detyrat', 'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25'],
                    ['url' => '/parent-settings', 'label' => 'Cilësimet', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z']
                ];

                foreach ($menuItems as $item): ?>
                <li>
                    <a href="/E-Shkolla<?= $item['url'] ?>"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive($item['url']) ? 'bg-indigo-50 text-indigo-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-indigo-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="<?= $item['icon'] ?>" /></svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap"><?= $item['label'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>

                <li class="mt-auto">
                    <button @click="helpOpen = true" class="w-full flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold text-slate-500 hover:bg-indigo-50 hover:text-indigo-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-show="!sidebarCollapsed">Ndihmë</span>
                    </button>
                </li>

                <li class="mt-2">
                    <a href="/E-Shkolla/logout" class="group flex items-center gap-x-3 rounded-xl bg-red-50/50 p-3 text-sm font-semibold text-red-600 hover:bg-red-50 transition-all">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Çkyçu</span>
                    </a>
                </li>
            </ul>
        </nav>

        <button @click="sidebarCollapsed = !sidebarCollapsed" class="hidden lg:flex items-center justify-center h-12 border-t border-slate-50 text-slate-400 hover:text-blue-600 transition-colors">
            <svg :class="sidebarCollapsed ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    </aside>

    <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="min-h-screen custom-transition flex flex-col lg:pl-72">
        
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between bg-white/80 backdrop-blur-md border-b border-slate-100 px-4 lg:px-8">
            <button @click="mobileOpen = true" class="p-2 lg:hidden text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="hidden lg:block">
                <p class="text-slate-500">Përshëndetje, <span class="font-semibold text-slate-800"><?= htmlspecialchars($parentName) ?></span></p>
            </div>

            <div class="flex items-center gap-2 lg:gap-4">
                <div class="relative"
                x-data="{
                    open: false,
                    latest: '<?= $userNotifications[0]['created_at'] ?? null ?>',
                    lastSeen: localStorage.getItem('parent_announcements_seen_at'),

                    hasNew() {
                        if (!this.latest) return false;
                        if (!this.lastSeen) return true;
                        return new Date(this.latest) > new Date(this.lastSeen);
                    },

                    markSeen() {
                        if (this.latest) {
                            localStorage.setItem('parent_announcements_seen_at', this.latest);
                            this.lastSeen = this.latest;
                        }
                    }
                }">
                <button
                    @click="open = !open; if (open) markSeen()"
                    class="p-2 text-slate-400 hover:text-blue-600 relative transition-colors">

                    <template x-if="hasNew()">
                        <span class="absolute top-2 right-2 flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                    </template>

                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11
                            a6.002 6.002 0 00-4-5.659V5
                            a2 2 0 10-4 0v.341
                            C7.67 6.165 6 8.388 6 11v3.159
                            c0 .538-.214 1.055-.595 1.436L4 17h5
                            m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>

                    <div x-show="open" @click.away="open = false" x-transition x-cloak
                         class="absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden ring-1 ring-black/5">
                        <div class="p-4 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">Njoftimet</h3>
                            <span class="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-bold"><?= count($userNotifications) ?> Reja</span>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <?php if (empty($userNotifications)): ?>
                                <div class="p-8 text-center">
                                    <p class="text-xs text-slate-400 italic font-medium">Nuk ka njoftime të reja.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($userNotifications as $u): ?>
                                    <div class="p-4 border-b border-slate-50 hover:bg-slate-50 transition-colors cursor-pointer">
                                        <div class="flex justify-between items-start mb-1">
                                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($u['title']) ?></h4>
                                            <span class="text-[9px] text-slate-400 font-bold">
                                                <?= date('d.m.Y / H:i', strtotime($u['created_at'])) ?>
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 leading-relaxed"><?= htmlspecialchars($u['content']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pl-2 lg:pl-4 border-l border-slate-100">
                    <span class="hidden md:block text-sm font-semibold text-slate-700"><?= htmlspecialchars($parentName) ?></span>
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                        <?= strtoupper(substr(htmlspecialchars($parentName), 0, 1)) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-4 lg:p-10 flex-1">
            <div class="max-w-7xl mx-auto">
                <?= $content ?? '<div class="flex flex-col items-center justify-center h-[60vh] text-slate-400"><p class="italic">Zgjidhni një opsion nga menuja...</p></div>' ?>
            </div>
        </main>
    </div>
</body>
</html>