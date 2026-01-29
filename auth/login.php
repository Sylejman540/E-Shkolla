<?php
/* =========================================================
   SESSION CONFIG (MUST BE FIRST)
========================================================= */
if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 7);

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'secure'   => false, // true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../db.php';

/* =========================================================
   SECURITY HEADERS
========================================================= */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/* =========================================================
   CSRF
========================================================= */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================================================
   AUTO LOGIN (REMEMBER ME)
========================================================= */
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_token'])) {

    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.role, u.school_id, u.status
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.token_hash = ?
          AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $_COOKIE['remember_token'])]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['status'] === 'active') {

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'        => (int)$user['id'],
            'email'     => $user['email'],
            'school_id' => (int)$user['school_id'],
            'role'      => $user['role']
        ];

        $_SESSION['login_time'] = time();

        $redirects = [
            'super_admin'  => '/E-Shkolla/super-admin-dashboard',
            'school_admin' => '/E-Shkolla/school-admin-dashboard',
            'teacher'      => '/E-Shkolla/teacher-dashboard',
            'student'      => '/E-Shkolla/student-dashboard',
            'parent'       => '/E-Shkolla/parent-dashboard'
        ];

        header('Location: ' . ($redirects[$user['role']] ?? '/'));
        exit;
    }
}

/* =========================================================
   LOGIN (POST)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);
    $ip       = $_SERVER['REMOTE_ADDR'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(401);
        exit('Invalid credentials');
    }

    $stmt = $pdo->prepare("
        SELECT id, email, password, role, school_id, status
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        exit('Invalid credentials');
    }

    if ($user['status'] !== 'active') {
        http_response_code(403);
        exit('Account inactive');
    }

    /* =========================================================
       LOGOUT OTHER DEVICES
    ========================================================= */
    $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")
        ->execute([$user['id']]);

    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
        ->execute([$user['id']]);

    /* =========================================================
       CREATE SESSION
    ========================================================= */
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'email'     => $user['email'],
        'school_id' => (int)$user['school_id'],
        'role'      => $user['role']
    ];

    $_SESSION['login_time'] = time();

    $pdo->prepare("
        INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_activity)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([
        $user['id'],
        session_id(),
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    /* =========================================================
       REMEMBER ME
    ========================================================= */
    if ($remember) {
        $token = bin2hex(random_bytes(32));

        $pdo->prepare("
            INSERT INTO remember_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ")->execute([
            $user['id'],
            hash('sha256', $token)
        ]);

        setcookie(
            'remember_token',
            $token,
            time() + (60 * 60 * 24 * 30),
            '/',
            '',
            false, // true on HTTPS
            true
        );
    }

    /* =========================================================
       REDIRECT
    ========================================================= */
    $redirects = [
        'super_admin'  => '/E-Shkolla/super-admin-dashboard',
        'school_admin' => '/E-Shkolla/school-admin-dashboard',
        'teacher'      => '/E-Shkolla/teacher-dashboard',
        'student'      => '/E-Shkolla/student-dashboard',
        'parent'       => '/E-Shkolla/parent-dashboard'
    ];

    header('Location: ' . ($redirects[$user['role']] ?? '/'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="sq" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla – Kyçje në sistem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="images/icon.png" type="image/png">
</head>

<style>
@keyframes gradientShift {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

.animate-gradient-bg {
  background-size: 400% 400%;
  animation: gradientShift 12s ease infinite;
}
</style>

<body class="h-full bg-gray-50 dark:bg-gray-950">

<div class="flex min-h-full">

  <!-- LEFT SIDE -->
  <div class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
    <div class="mx-auto w-full max-w-md">

      <div class="mb-10">
        <div class="mb-6 flex items-center">
          <div class="flex h-20 w-20 items-center justify-center">
            <img src="images/icon.png" alt="E-Shkolla Logo" class="h-20 w-20 object-contain">
          </div>
          <div class="leading-tight">
            <h1 class="text-lg font-bold text-gray-900 dark:text-white">E-Shkolla</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Sistemi i Menaxhimit Shkollor
            </p>
          </div>
        </div>

        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
          Kyçu në llogarinë tënde
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Qasje e sigurt në panelin shkollor
        </p>
      </div>

      <!-- ERROR MESSAGE PLACEHOLDER -->
      <!--
      <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 border border-red-200">
        <span class="font-bold">Gabim!</span> Mesazh gabimi
      </div>
      -->

      <form method="POST" class="space-y-5">

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Adresa e email-it
          </label>
          <input
            type="email"
            name="email"
            required
            placeholder="emri@email.com"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-gray-900 outline-none transition
                   focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                   dark:bg-gray-900 dark:border-gray-700 dark:text-white">
          <p class="mt-1 text-xs text-gray-500">
            Përdor email-in që ke të regjistruar në shkollë
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Fjalëkalimi
          </label>
          <div class="relative">
            <input
              id="password"
              type="password"
              name="password"
              required
              placeholder="Shkruaj fjalëkalimin"
              class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-11 text-gray-900 outline-none transition
                     focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20
                     dark:bg-gray-900 dark:border-gray-700 dark:text-white">

            <button type="button"
                    id="togglePassword"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-blue-600">
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg"
                   class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                   stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5
                         c4.478 0 8.268 2.943 9.542 7
                         -1.274 4.057 -5.064 7 -9.542 7
                         -4.477 0 -8.268 -2.943 -9.542 -7z"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M15 12a3 3 0 11-6 0
                         3 3 0 016 0z"/>
              </svg>
            </button>
          </div>
        </div>

        <label class="flex items-center text-sm">
          <input type="checkbox" name="remember" class="mr-2">
          Më mbaj të kyçur
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white
                       hover:bg-blue-700 transition">
          Kyçu
        </button>
      </form>

      <div class="mt-8 text-center text-xs text-gray-500">
        Të dhënat janë të enkriptuara dhe të mbrojtura
      </div>

    </div>
  </div>

  <!-- RIGHT SIDE -->
  <div class="relative hidden lg:block w-1/2 overflow-hidden">
    <img src="https://images.pexels.com/photos/4145192/pexels-photo-4145192.jpeg"
         alt="Nxënës"
         class="absolute inset-0 h-full w-full object-cover">

    <div class="absolute inset-0 bg-gradient-to-br from-blue-800 to-purple-700
                opacity-60 animate-gradient-bg"></div>

    <div class="relative z-10 flex h-full flex-col items-center justify-center
                text-center px-8 text-white">
      <img src="images/icon.png" alt="Logo" class="h-20 w-20 object-contain mb-4">
      <h3 class="text-2xl font-semibold mb-2">
        Platformë për Arsimin Digjital
      </h3>
      <p class="max-w-sm text-sm text-blue-100">
        E-Shkolla ndihmon mësuesit, nxënësit dhe prindërit të bashkëpunojnë.
      </p>
    </div>
  </div>

</div>

<script>
const toggleBtn = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');

toggleBtn.addEventListener('click', () => {
  const isPassword = passwordInput.type === 'password';
  passwordInput.type = isPassword ? 'text' : 'password';

  eyeIcon.innerHTML = isPassword
    ? `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
         d="M3 3l18 18" />`
    : `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
         d="M2.458 12C3.732 7.943 7.523 5 12 5
            c4.478 0 8.268 2.943 9.542 7
            -1.274 4.057 -5.064 7 -9.542 7
            -4.477 0 -8.268 -2.943 -9.542 -7z"/>`;
});
</script>

</body>
</html>

