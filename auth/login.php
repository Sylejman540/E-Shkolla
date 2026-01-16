<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $lock_key = "login_attempt_" . md5($ip);
        $max_attempts = 5;
        $lockout_time = 900; 
        
        if (!isset($_SESSION[$lock_key])) {
            $_SESSION[$lock_key] = [
                'count' => 0,
                'locked_until' => 0,
                'first_attempt' => time()
            ];
        }
        
        if ($_SESSION[$lock_key]['locked_until'] >= time()) {
            $remaining = ceil(($_SESSION[$lock_key]['locked_until'] - time()) / 60);
            $error = "Too many login attempts. Try again in {$remaining} minutes.";
        } else if ($error === null) {
            $_SESSION[$lock_key] = [
                'count' => 0,
                'locked_until' => 0,
                'first_attempt' => time()
            ];
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT id, email, password, role, school_id, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    
                    if ($user['status'] !== 'active') {
                        $error = "Kyçja dështoi. Ju lutemi kontaktoni mbështetjen."; // Don't reveal why
                        $_SESSION[$lock_key]['count']++;
                    } else {
                        session_regenerate_id(true);

                        unset($_SESSION[$lock_key]);
                        
                        $_SESSION['user'] = [
                            'id' => (int) $user['id'],
                            'email' => $user['email'],
                            'school_id' => (int) $user['school_id'],
                            'role' => $user['role']
                        ];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['status'] = $user['status'];
                        $_SESSION['login_time'] = time();
                        
                        $success = true;
                        
                        $role_redirects = [
                            'super_admin' => '/E-Shkolla/super-admin-dashboard',
                            'school_admin' => '/E-Shkolla/school-admin-dashboard',
                            'teacher' => '/E-Shkolla/teacher-dashboard',
                            'student' => '/E-Shkolla/student-dashboard',
                            'parent' => '/E-Shkolla/parent-dashboard'
                        ];
                        
                        if (isset($role_redirects[$user['role']])) {
                            header("Location: {$role_redirects[$user['role']]}");
                            exit;
                        } else {

                            $error = "Kyçja dështoi. Ju lutemi kontaktoni mbështetjen.";
                            $_SESSION[$lock_key]['count']++;
                        }
                    }
                } else {
                    $_SESSION[$lock_key]['count']++;

                    if ($_SESSION[$lock_key]['count'] >= $max_attempts) {
                        $_SESSION[$lock_key]['locked_until'] = time() + $lockout_time;
                    }
                    
                    $error = "Kyçja dështoi. Ju lutemi kontrolloni të dhënat tuaja."; // Generic message
                }
            } else {
                $error = "Kyçja dështoi. Ju lutemi kontrolloni të dhënat tuaja.";
                $_SESSION[$lock_key]['count']++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shkolla – Kyçje e Sigurt</title>
    <script src="https://cdn.tailwindcss.com"></script>
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


  <div class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
    <div class="mx-auto w-full max-w-md">

      <div class="mb-10">
        <div class="mb-6 flex items-center">
          
          <div class="flex h-20 w-20 items-center justify-center">
            <img src="images/icon.png" alt="E-Shkolla Logo" class="h-20 w-20 object-contain">
          </div>

          <div class="leading-tight">
            <h1 class="text-lg font-bold text-gray-900 dark:text-white">
              E-Shkolla
            </h1>
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

      <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
          <div id="logout-alert" style="color: green; padding: 10px; background: #eaffea; border: 1px solid green; margin-bottom: 10px; transition: opacity 0.5s ease;">
              Jeni çkyçur me sukses.
          </div>

          <script>
              setTimeout(function() {
                  const alert = document.getElementById('logout-alert');
                  if (alert) {
                      alert.style.opacity = '0'; // Fade out effect
                      setTimeout(() => alert.remove(), 500); // Remove from DOM after fade
                  }
              }, 5000);
          </script>
      <?php endif; ?>


      <?php if ($error): ?>
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          <?= htmlspecialchars($error) ?>
          <p class="mt-1 text-xs text-red-700">
            Nëse problemi vazhdon, kontakto administratorin e shkollës.
          </p>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Adresa e email-it
          </label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20" placeholder="nxenesi@shkolla.edu">
          <p class="mt-1 text-xs text-gray-500">
            Përdor email-in e shkollës
          </p>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Fjalëkalimi
          </label>

          <div class="relative">
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Shkruaj fjalëkalimin" class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 pr-11 text-gray-900 dark:text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">

            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center px-3" aria-label="Shfaq ose fshih fjalëkalimin">
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" style="stroke: black !important;">
                <path style="stroke: black !important;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path style="stroke: black !important;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </button>
          </div>
        </div>


        <button type="submit" class="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">Kyçu në mënyrë të sigurt</button>
      </form>
      <div class="mt-8 text-center text-xs text-gray-500">
        Të dhënat janë të enkriptuara dhe të mbrojtura
      </div>
    </div>
  </div>

  <div class="relative hidden lg:block w-1/2 overflow-hidden">
    <img src="https://images.pexels.com/photos/4145192/pexels-photo-4145192.jpeg" alt="Nxënës në klasë duke mësuar" class="absolute inset-0 h-full w-full object-cover">

    <div class="absolute inset-0 bg-gradient-to-br from-blue-800 to-purple-700 opacity-60 animate-gradient-bg"></div>

    <div class="relative z-10 flex h-full flex-col items-center justify-center text-center px-8 text-white">
      <div>
        <img src="images/icon.png" alt="E-Shkolla Logo" class="h-20 w-20 object-contain">
      </div>
      <h3 class="text-2xl font-semibold mb-2">Platformë për Arsimin Digjital</h3>
      <p class="max-w-sm text-sm text-blue-100">
        E-Shkolla ndihmon mësuesit, nxënësit dhe prindërit të bashkëpunojnë në një ambient modern dhe të sigurt shkollor.
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

    // We hard-code the style into every path to kill the white line forever
    if (isPassword) {
      eyeIcon.innerHTML = `
        <path style="stroke: black !important;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.362-4.162M6.423 6.423A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.978 9.978 0 01-4.043 5.362M15 12a3 3 0 00-3-3"/>`;
    } else {
      eyeIcon.innerHTML = `
        <path style="stroke: black !important;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        <path style="stroke: black !important;" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>`;
    }
  });
</script>
</body>
</html>

