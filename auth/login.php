<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/* =========================
   CSRF TOKEN GENERATION
========================= */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    /* =========================
       CSRF VALIDATION
    ========================= */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        /* =========================
           RATE LIMITING (Basic)
        ========================= */
        $ip = $_SERVER['REMOTE_ADDR'];
        $lock_key = "login_attempt_" . md5($ip);
        $max_attempts = 5;
        $lockout_time = 900; // 15 minutes
        
        // Initialize attempt tracking if not exists
        if (!isset($_SESSION[$lock_key])) {
            $_SESSION[$lock_key] = [
                'count' => 0,
                'locked_until' => 0,
                'first_attempt' => time()
            ];
        }
        
        // Check if locked out
        if ($_SESSION[$lock_key]['locked_until'] >= time()) {
            $remaining = ceil(($_SESSION[$lock_key]['locked_until'] - time()) / 60);
            $error = "Too many login attempts. Try again in {$remaining} minutes.";
        } else if ($error === null) {
            // Reset lockout if expired
            $_SESSION[$lock_key] = [
                'count' => 0,
                'locked_until' => 0,
                'first_attempt' => time()
            ];
            
            /* =========================
               AUTHENTICATION
            ========================= */
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Use prepared statement (already using PDO - secure)
                $stmt = $pdo->prepare("SELECT id, email, password, role, school_id, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password (prevents timing attacks with password_verify)
                if ($user && password_verify($password, $user['password'])) {
                    
                    // Check account status
                    if ($user['status'] !== 'active') {
                        $error = "Login failed. Please contact support."; // Don't reveal why
                        $_SESSION[$lock_key]['count']++;
                    } else {
                        
                        /* =========================
                           REGENERATE SESSION ID (prevents fixation)
                        ========================= */
                        session_regenerate_id(true);
                        
                        // Clear rate limit on success
                        unset($_SESSION[$lock_key]);
                        
                        /* =========================
                           SET SESSION DATA
                        ========================= */
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
                        
                        /* =========================
                           REDIRECT BY ROLE
                        ========================= */
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
                            // Role not found in redirect map - treat as error
                            $error = "Login failed. Please contact support.";
                            $_SESSION[$lock_key]['count']++;
                        }
                    }
                } else {
                    // Increment failed attempts
                    $_SESSION[$lock_key]['count']++;
                    
                    // Lock after max attempts
                    if ($_SESSION[$lock_key]['count'] >= $max_attempts) {
                        $_SESSION[$lock_key]['locked_until'] = time() + $lockout_time;
                    }
                    
                    $error = "Login failed. Please check your credentials."; // Generic message
                }
            } else {
                $error = "Login failed. Please check your credentials.";
                $_SESSION[$lock_key]['count']++;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>E-Shkolla - Secure Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950">

<div class="flex min-h-full">
  <!-- Left Section: Login Form -->
  <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-8 lg:w-1/2 lg:px-16">
    <div class="mx-auto w-full max-w-md">
      
      <!-- Branding Header -->
      <div class="mb-10">
        <div class="mb-6 flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-600 dark:bg-blue-600">
            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10.5 1.5H3.75A2.25 2.25 0 001.5 3.75v12.5A2.25 2.25 0 003.75 18.5h12.5a2.25 2.25 0 002.25-2.25V9.5M10.5 1.5v8m0 0l3-3m-3 3l-3-3M18.5 1.5v4.5m0 0h-4.5m4.5 0l-3-3"/>
            </svg>
          </div>
          <div>
            <h1 class="text-lg font-bold text-gray-900 dark:text-white">E-Shkolla</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">School Management System</p>
          </div>
        </div>
        
        <div>
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-white leading-tight">Sign in to your account</h2>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Access your school dashboard securely</p>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="mb-8">
        <?php if ($error): ?>
          <div class="rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 p-4" role="alert">
            <div class="flex gap-3">
              <svg class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
              </svg>
              <div>
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                  <?= htmlspecialchars($error) ?>
                </p>
                <p class="mt-1 text-xs text-red-700 dark:text-red-300">If you continue to experience issues, please contact your school administrator.</p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="rounded-lg border border-green-200 dark:border-green-900/50 bg-green-50 dark:bg-green-950/30 p-4" role="status">
            <div class="flex gap-3">
              <svg class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                Login successful. Redirecting to your dashboard...
              </p>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Login Form -->
      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <!-- Email Input -->
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email address</label>
          <input 
            id="email" 
            type="email" 
            name="email" 
            required 
            autocomplete="email" 
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 outline-none transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 dark:focus:border-blue-500 dark:focus:ring-blue-500/20"
            placeholder="name@school.edu"
            aria-describedby="email-help"
          />
          <p id="email-help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use your school email address</p>
        </div>

        <!-- Password Input -->
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
          <input 
            id="password" 
            type="password" 
            name="password" 
            required 
            autocomplete="current-password"
            class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 outline-none transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 dark:focus:border-blue-500 dark:focus:ring-blue-500/20"
            placeholder="Enter your password"
          />
        </div>

        <!-- Submit Button -->
        <button 
          type="submit"
          class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors duration-200 focus:outline-2 focus:outline-offset-2 focus:outline-blue-600"
        >
          Sign in securely
        </button>
      </form>

      <!-- Trust Footer -->
      <div class="mt-8 border-t border-gray-200 dark:border-gray-800 pt-8">
        <div class="grid grid-cols-3 gap-4 mb-6">
          <div class="text-center">
            <svg class="h-5 w-5 text-green-600 dark:text-green-400 mx-auto mb-1.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Encrypted</p>
          </div>
          <div class="text-center">
            <svg class="h-5 w-5 text-green-600 dark:text-green-400 mx-auto mb-1.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Secure</p>
          </div>
          <div class="text-center">
            <svg class="h-5 w-5 text-green-600 dark:text-green-400 mx-auto mb-1.5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M13 7H7v6h6V7z"/>
              <path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2V2a1 1 0 112 0v1a2 2 0 012 2v2h1a2 2 0 012 2v2h1a2 2 0 012 2v6a2 2 0 01-2 2h-1v1a1 1 0 11-2 0v-1h-2v1a1 1 0 11-2 0v-1h-2v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-6a2 2 0 012-2h1V9a2 2 0 012-2h2V6a2 2 0 012-2H7V2z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Private</p>
          </div>
        </div>
        
        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
          Need help? <a href="#" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">Contact your administrator</a>
        </p>
      </div>

    </div>
  </div>

  <!-- Right Section: Image (Desktop only) -->
  <div class="relative hidden w-1/2 lg:block bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-800 dark:to-blue-900">
    <!-- Subtle background pattern -->
    <div class="absolute inset-0 opacity-10">
      <svg class="h-full w-full" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
          </pattern>
        </defs>
        <rect width="400" height="400" fill="url(#grid)"/>
      </svg>
    </div>

    <!-- Content overlay -->
    <div class="relative h-full flex flex-col items-center justify-center px-8 text-center">
      <div class="max-w-xs">
        <svg class="h-20 w-20 text-white/90 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C6.5 6.253 2 10.998 2 17s4.5 10.747 10 10.747c5.5 0 10-4.998 10-10.747S17.5 6.253 12 6.253z"/>
        </svg>
        <h3 class="text-xl font-semibold text-white mb-2">Learning Management</h3>
        <p class="text-sm text-blue-100">
          A trusted platform for teachers, students, and parents to collaborate and succeed together.
        </p>
      </div>
    </div>
  </div>

</div>

</body>
</html>
