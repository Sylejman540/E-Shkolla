<!DOCTYPE html>
<html lang="en" class="h-full bg-white dark:bg-gray-900"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/phosphor-icons"></script>
</head>
<style>
  .submenu-link {
    display: block;
    padding: 6px 8px;
    font-size: 0.875rem;
    color: #4b5563;
    border-radius: 6px;
  }
  .submenu-link:hover {
    color: #4f46e5;
    background-color: #f9fafb;
  }
</style>
<body class="h-full">

<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
  <div class="relative flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 dark:border-white/10 dark:bg-gray-900 dark:before:pointer-events-none dark:before:absolute dark:before:inset-0 dark:before:bg-black/10">
    <a href="/E-Shkolla/super-admin-schools" class="relative flex h-16 shrink-0 items-center">
      <img src="/E-Shkolla/images/logo.png" alt="Your Company" class="w-48 h-48 dark:hidden" />
    </a>
    <nav class="relative flex flex-1 flex-col">
      <ul role="list" class="flex flex-1 flex-col gap-y-7">
        <li>
          <ul role="list" class="-mx-2 space-y-1">
            <li>
              <a href="/E-Shkolla/school-admin-dashboard" class="group flex gap-x-3 rounded-md bg-gray-50 p-2 text-sm/6 font-semibold text-indigo-600 dark:bg-white/5 dark:text-white">
                🏠 Dashboard
              </a>
            </li>
            <li>
              <button type="button" data-toggle="users" class="w-full flex items-center justify-between rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <span class="flex items-center gap-x-3">
                  👥 Përdoruesit
                </span>
                <span class="arrow transition-transform">  
                  <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z"
                      clip-rule="evenodd" />
                  </svg></span>
              </button>

              <ul data-menu="users" class="ml-9 mt-1 space-y-1 hidden">
                <li><a href="/E-Shkolla/teachers" class="submenu-link">Mësuesit</a></li>
                <li><a href="/E-Shkolla/students" class="submenu-link">Nxënësit</a></li>
                <li><a href="/E-Shkolla/parents" class="submenu-link">Prindërit</a></li>
              </ul>
            </li>
            <li>
              <button type="button" data-toggle="academy" class="w-full flex items-center justify-between rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <span class="flex items-center gap-x-3">
                  🎓 Akademia
                </span>
                <span class="arrow transition-transform">  
                  <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z"
                      clip-rule="evenodd" />
                  </svg></span>
              </button>

              <ul data-menu="academy" class="ml-9 mt-1 space-y-1 hidden">
                <li><a href="/E-Shkolla/classes" class="submenu-link">Klasat</a></li>
                <li><a href="/E-Shkolla/subjects" class="submenu-link">Lëndët</a></li>
                <li><a href="/E-Shkolla/schedule" class="submenu-link">Orari</a></li>
              </ul>
            </li>
            <li>
              <a href="/E-Shkolla/attendance" class="flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                📊 Pjesëmarrja
              </a>
            </li>
            <li>
              <a href="/E-Shkolla/reports" class="flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                📈 Raportet
              </a>
            </li>
          </ul>
        </li>
        <li>
        <div class="text-xs/6 font-semibold text-gray-400 dark:text-gray-500">Cilësimet</div>
          <ul role="list" class="-mx-2 mt-2 space-y-1">
          <li class="pt-4 border-t">
            <a href="/E-Shkolla/school-settings" class="flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
              ⚙️ Cilësimet e Shkollës
             </a>
          </li>
          <li>
            <a href="/E-Shkolla/logout" class="flex items-center gap-x-3 rounded-md p-2 text-sm font-semibold text-red-600 hover:bg-red-50">
              🚪 Dil nga llogaria
            </a>
          </li>
          </ul>
        </li>
        <li class="-mx-6 mt-auto">
          <a href="#" class="flex items-center gap-x-4 px-6 py-3 text-sm/6 font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5">
            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="" class="size-8 rounded-full bg-gray-50 outline -outline-offset-1 outline-black/5 dark:bg-gray-800 dark:outline-white/10" />
            <span class="sr-only">Your profile</span>
            <span aria-hidden="true">School Admin</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<div class="sticky top-0 z-40 flex items-center gap-x-6 bg-white px-4 py-4 shadow-xs sm:px-6 lg:hidden dark:bg-gray-900 dark:shadow-none dark:before:pointer-events-none dark:before:absolute dark:before:inset-0 dark:before:border-b dark:before:border-white/10 dark:before:bg-black/10">
  <button type="button" command="show-modal" commandfor="sidebar" class="relative -m-2.5 p-2.5 text-gray-700 lg:hidden dark:text-gray-400">
    <span class="sr-only">Open sidebar</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
      <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </button>
  <div class="relative flex-1 text-sm/6 font-semibold text-gray-900 dark:text-white">Dashboard</div>
  <a href="#" class="relative">
    <span class="sr-only">Your profile</span>
    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="" class="size-8 rounded-full bg-gray-50 outline -outline-offset-1 outline-black/5 dark:bg-gray-800 dark:outline-white/10" />
  </a>
</div>
<script>
  document.querySelectorAll('[data-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const key = button.dataset.toggle;
      const menu = document.querySelector(`[data-menu="${key}"]`);
      const arrow = button.querySelector('.arrow');

      document.querySelectorAll('[data-menu]').forEach(m => {
        if (m !== menu) m.classList.add('hidden');
      });

      document.querySelectorAll('.arrow').forEach(a => {
        if (a !== arrow) a.style.transform = 'rotate(0deg)';
      });

      menu.classList.toggle('hidden');
      arrow.style.transform = menu.classList.contains('hidden')
        ? 'rotate(0deg)'
        : 'rotate(180deg)';
    });
  });
</script>
</body>
</html>