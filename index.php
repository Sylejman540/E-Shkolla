<?php

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptName = dirname($_SERVER['SCRIPT_NAME']);

if ($scriptName !== '/') {
    $uri = str_replace($scriptName, '', $uri);
}

$uri = rtrim($uri, '/') ?: '/';

$routes = [
    '/'       => 'controllers/home.php',
    '/login'  => 'auth/login.php',
    '/logout' => 'auth/logout.php',
    '/super-admin-dashboard' => 'dashboard/superadmin-dashboard/partials/dashboard.php',
    '/super-admin-schools' => 'dashboard/superadmin-dashboard/partials/school/school.php',
    '/super-admin-users' => 'dashboard/superadmin-dashboard/partials/users/users.php',
    '/school-admin-dashboard' => 'dashboard/schooladmin-dashboard/index.php' 
];

if (array_key_exists($uri, $routes)) {
    require $routes[$uri];
} else {
    http_response_code(404);
    echo "404 - Page not found";
}
