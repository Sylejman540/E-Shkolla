<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db.php'; 

/**
 * Checks if the current URL matches the path.
 */
function isActive($path) {
    $uri = $_SERVER['REQUEST_URI'];
    return (str_ends_with($uri, $path) || str_contains($uri, $path . '/'));
}

// Logjika për emrin e mësuesit dhe njoftimet
$teacherName = 'Profesor';
$userNotifications = [];
$schoolId = $_SESSION['user']['school_id'] ?? null;
$isClassHeader = false; 

if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'teacher') {
    $userId = $_SESSION['user']['id'];

    // 1. Merr të dhënat nga tabela 'teachers' duke përdorur user_id e sesionit
    $stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $teacherData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacherData) {
        $teacherName = $teacherData['name'] ?: 'Profesor';
        $teacherId = $teacherData['id']; // Ky është ID-ja nga tabela 'teachers'

        // 2. Kontrollo nëse ky mësues është kujdestar klase duke përdorur teacherId
        $headerStmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_header = ? AND school_id = ?");
        $headerStmt->execute([$teacherId, $schoolId]);
        
        if ($headerStmt->fetchColumn() > 0) {
            $isClassHeader = true;
        }
    }

    // 3. Merr njoftimet reale nga databaza
    $annStmt = $pdo->prepare("
        SELECT title, content, created_at 
        FROM announcements 
        WHERE school_id = ? 
        AND (target_role = 'all' OR target_role = 'teacher')
        AND (expires_at IS NULL OR expires_at >= CURDATE())
        ORDER BY created_at DESC LIMIT 5
    ");
    $annStmt->execute([$schoolId]);
    $userNotifications = $annStmt->fetchAll(PDO::FETCH_ASSOC);
}

$latestAnnouncementAt = $userNotifications[0]['created_at'] ?? null;
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
            left: -16px;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: #2563eb;
            border-radius: 0 4px 4px 0;
        }

        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>

<body class="h-full font-sans antialiased text-slate-900"
      x-data="{ sidebarCollapsed: false, mobileOpen: false, helpOpen: false, toasts: [] }">

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
         x-transition:leave-end="translate-x-full"
         x-cloak
         class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl border-l border-slate-100 flex flex-col">

        <div class="flex items-center justify-between p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-600 text-white rounded-lg">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M8.228 9c.549-1.165 2.03-2 3.772-2
                              2.21 0 4 1.343 4 3
                              0 1.4-1.278 2.575-3.006 2.907
                              -.542.104-.994.54-.994 1.093m0 3h.01
                              M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Ndihmë për Mësuesit</h2>
                    <p class="text-xs text-slate-500">Rregullat kryesore të sistemit</p>
                </div>
            </div>

            <button @click="helpOpen = false" class="p-2 rounded-full hover:bg-white">
                <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-8 text-sm text-slate-600 space-y-8">
            <section>
                <h3 class="font-bold text-slate-800 mb-2">Dashboard</h3>
                <p>Dashboard-i shfaq një përmbledhje të shpejtë të aktiviteteve tuaja si mësues.</p>
            </section>

            <section>
                <h3 class="font-bold text-slate-800 mb-2">Klasat e mia</h3>
                <ul class="list-disc ml-5 space-y-1">
                    <li>Shfaqen vetëm klasat ku jeni të caktuar si mësues</li>
                    <li>Brenda klasës menaxhoni prezencën, notat dhe detyrat</li>
                </ul>
            </section>

            <?php if ($isClassHeader): ?>
            <section>
                <h3 class="font-bold text-slate-800 mb-2">Klasa Kujdestare</h3>
                <p>Si mësues kujdestar, ju mund të shihni detajet e prindërve për nxënësit e klasës suaj.</p>
            </section>
            <?php endif; ?>
        </div>
    </div>
</template>

    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white shadow-[4px_0_24px_rgba(0,0,0,0.02)] border-r border-slate-100 custom-transition"
           :class="[sidebarCollapsed ? 'w-20' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <a href="/E-Shkolla/teacher-dashboard" class="flex h-20 shrink-0 items-center px-6 overflow-hidden border-b border-slate-50">
            <img src="/E-Shkolla/images/icon.png" class="h-9 w-auto min-w-[36px]" alt="Logo">
            <div x-show="!sidebarCollapsed" x-transition.opacity class="ml-3 whitespace-nowrap">
                <h1 class="text-lg font-bold tracking-tight text-slate-800 leading-none">E-Shkolla</h1>
                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-600 mt-1">Mësues</p>
            </div>
        </a>

        <nav class="flex flex-1 flex-col overflow-y-auto px-4 py-6">
            <ul role="list" class="flex flex-1 flex-col gap-y-1.5">
                
                <li>
                    <a href="/E-Shkolla/teacher-dashboard"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-dashboard') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Paneli</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/teacher-classes"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-classes') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Klasat e mia</span>
                    </a>
                </li>

                <li>
                    <a href="/E-Shkolla/teacher-schedule"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-schedule') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Orari</span>
                    </a>
                </li>

                <?php if ($isClassHeader): ?>
                <div x-show="!sidebarCollapsed" class="px-3 mt-4 mb-2">
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Menaxhimi i klasës kujdestare</h3>
                </div>

                <li>
                    <a href="/E-Shkolla/teacher-parents"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-parents') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Prindërit</span>
                    </a>
                </li>
                <?php endif; ?>

                <div x-show="!sidebarCollapsed" class="px-3 mt-4 mb-2">
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Njoftimet</h3>
                </div>

                <li>
                    <a href="/E-Shkolla/teacher-notices"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-notices') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Njoftimet</span>
                    </a>
                </li>

                <div x-show="!sidebarCollapsed" class="px-3 mt-4 mb-2">
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Personalizime</h3>
                </div>

                <li>
                    <a href="/E-Shkolla/teacher-settings"
                       class="relative group flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold transition-all
                       <?= isActive('/teacher-settings') ? 'bg-blue-50 text-blue-600 active-indicator' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600' ?>">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="whitespace-nowrap">Cilësimet</span>
                    </a>
                </li>

                <li class="mt-auto">
                    <button @click="helpOpen = true"
                            class="w-full flex items-center gap-x-3 rounded-xl p-3 text-sm font-semibold
                                   text-slate-500 hover:bg-blue-50 hover:text-blue-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907 -.542.104-.994.54-.994 1.093m0 3h.01 M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-show="!sidebarCollapsed">Ndihmë</span>
                    </button>
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

    <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="min-h-screen custom-transition flex flex-col lg:pl-72">
        
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between bg-white/80 backdrop-blur-md border-b border-slate-100 px-4 lg:px-8">
            <button @click="mobileOpen = true" class="p-2 lg:hidden text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="hidden lg:block">
                <p class="text-sm font-medium text-slate-500 italic">Mirëseerdhe, Prof. <span class="text-slate-800 font-bold not-italic"><?= htmlspecialchars($teacherName) ?></span></p>
            </div>

            <div class="flex items-center gap-3">
               <div class="relative"
                    x-data="{
                        open: false,
                        latest: '<?= $latestAnnouncementAt ?>',
                        lastSeen: localStorage.getItem('teacher_announcements_seen_at'),

                        hasNew() {
                            if (!this.latest) return false;
                            if (!this.lastSeen) return true;
                            return new Date(this.latest).getTime() > new Date(this.lastSeen).getTime();
                        },

                        markSeen() {
                            if (this.latest) {
                                localStorage.setItem('teacher_announcements_seen_at', this.latest);
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
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
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
                                <?php foreach ($userNotifications as $n): ?>
                                    <div class="p-4 border-b border-slate-50 hover:bg-slate-50 transition-colors cursor-pointer">
                                        <div class="flex justify-between items-start mb-1">
                                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($n['title']) ?></h4>
                                            <span class="text-[9px] text-slate-400 font-bold"><?= date('H:i', strtotime($n['created_at'])) ?></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 leading-relaxed"><?= htmlspecialchars($n['content']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pl-2 lg:pl-4 border-l border-slate-100">
                    <span class="hidden md:block text-sm font-semibold text-slate-700"><?= htmlspecialchars($teacherName) ?></span>
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                        <?= strtoupper(substr(htmlspecialchars($teacherName), 0, 1)) ?>
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