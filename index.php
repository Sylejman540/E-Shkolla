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
    '/school-admin-dashboard' => 'dashboard/schooladmin-dashboard/partials/dashboard.php',
    '/teachers' => 'dashboard/schooladmin-dashboard/partials/teacher/teacher.php',
    '/students' => 'dashboard/schooladmin-dashboard/partials/students/students.php',
    '/parents' => 'dashboard/schooladmin-dashboard/partials/parent/parents.php',
    '/classes' => 'dashboard/schooladmin-dashboard/partials/classes/class.php',
    '/subjects' => 'dashboard/schooladmin-dashboard/partials/subject/subject.php',
    '/teacher-dashboard' => 'dashboard/teacher-dashboard/partials/dashboard.php',
    '/teacher-classes' => 'dashboard/teacher-dashboard/partials/classes/classes.php',
    '/teacher-assignments' => 'dashboard/teacher-dashboard/partials/assignments/assignments.php',
];

if (array_key_exists($uri, $routes)) {
    require $routes[$uri];
} else {
    http_response_code(404);
    echo "404 - Page not found";
}
