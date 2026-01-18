<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla | Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full">

<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 dark:border-white/10 dark:bg-gray-900">
        
        <div class="flex h-16 shrink-0 items-center border-b border-gray-100 dark:border-gray-800">
            <img src="/E-Shkolla/images/icon.png" alt="E-Shkolla" class="h-10 w-auto" />
            <span class="ml-3 text-xl font-bold text-indigo-600 dark:text-white tracking-tight">E-Shkolla</span>
        </div>

        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                
                <li>
                    <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500 mb-4 px-2">Menuja Kryesore</div>
                    <ul role="list" class="-mx-2 space-y-1.5">
                        <li>
                            <a href="/E-Shkolla/teacher-dashboard" class="flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400 group transition-all">
                                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                                Paneli Kryesor
                            </a>
                        </li>
                        <li>
                            <a href="/E-Shkolla/teacher-classes" class="flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white group transition-all">
                                <i data-lucide="book-open" class="w-5 h-5 text-gray-400 group-hover:text-indigo-600"></i>
                                Klasat e Mia
                            </a>
                        </li>
                        <li>
                            <a href="/E-Shkolla/teacher-schedule" class="flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white group transition-all">
                                <i data-lucide="calendar-days" class="w-5 h-5 text-gray-400 group-hover:text-indigo-600"></i>
                                Orari Mësimor
                            </a>
                        </li>
                    </ul>
                </li>

                <li>
                    <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500 mb-4 px-2">Personalizimi</div>
                    <ul role="list" class="-mx-2 space-y-1.5">
                        <li>
                            <a href="/E-Shkolla/teacher-profile" class="flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white group transition-all">
                                <i data-lucide="user-circle" class="w-5 h-5 text-gray-400 group-hover:text-indigo-600"></i>
                                Profili Im
                            </a>
                        </li>
                        <li>
                            <a href="/E-Shkolla/logout" class="flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10 transition-all">
                                <i data-lucide="log-out" class="w-5 h-5"></i>
                                Çkyçu
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="-mx-6 mt-auto border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-black/20 px-6 py-4">
                    <?php
                        // Supozojmë se session_start() është thirrur në index.php
                        require_once __DIR__  . '/../../../db.php';
                        $user_id = $_SESSION['user']['id'];
                        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="flex items-center gap-x-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-[13px] font-bold text-white shadow-sm">
                            <?= strtoupper(substr($user['name'], 0, 1)) . strtoupper(substr(strrchr($user['name'], " "), 1, 1)) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($user['name']) ?></p>
                            <p class="truncate text-xs font-medium text-indigo-600 dark:text-indigo-400">Mësues</p>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
</div>

<script>
    // Inicializimi i ikonave
    lucide.createIcons();
</script>

</body>
</html>