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
    '/classes' => 'dashboard/schooladmin-dashboard/partials/classes/classes.php',
    '/subjects' => 'dashboard/schooladmin-dashboard/partials/subject/subject.php',
    '/attendance' => 'dashboard/schooladmin-dashboard/partials/attendance/attendance.php',
    '/schedule' => 'dashboard/schooladmin-dashboard/partials/schedule/schedule.php',
    '/teacher-dashboard' => 'dashboard/teacher-dashboard/partials/dashboard.php',
    '/teacher-schedule' => 'dashboard/teacher-dashboard/partials/schedule/schedule.php',
    '/teacher-settings' => 'dashboard/teacher-dashboard/partials/settings.php',
    '/teacher-classes' => 'dashboard/teacher-dashboard/partials/classes/classes.php',
    '/teacher-parents' => 'dashboard/teacher-dashboard/partials/parents/parents.php',
    '/teacher-notices' => 'dashboard/teacher-dashboard/partials/notices/notices.php',
    '/class-assignments' => 'dashboard/teacher-dashboard/partials/show-classes/assignments/assignments.php',
    '/class-attendance' => 'dashboard/teacher-dashboard/partials/show-classes/attendance/attendance.php',
    '/show-classes' => 'dashboard/teacher-dashboard/partials/show-classes/dashboard.php',
    '/class-grades' => 'dashboard/teacher-dashboard/partials/show-classes/grades/grades.php',
    '/student-dashboard' => 'dashboard/student-dashboard/partials/dashboard.php',
    '/student-schedule' => 'dashboard/student-dashboard/partials/schedule/schedule.php',
    '/student-assignments' => 'dashboard/student-dashboard/partials/assignments/assignments.php',
    '/student-grades' => 'dashboard/student-dashboard/partials/grades/grades.php',
    '/parent-dashboard' => 'dashboard/parent-dashboard/partials/dashboard.php',
    '/parent-attendance' => 'dashboard/parent-dashboard/partials/attendance.php',
    '/parent-assignments' => 'dashboard/parent-dashboard/partials/assignments.php',
    '/parent-grades' => 'dashboard/parent-dashboard/partials/grades.php',
    '/parent-children' => 'dashboard/parent-dashboard/partials/parent-children.php',
    '/csv' => 'dashboard/schooladmin-dashboard/partials/teacher/import-teachers.php',
    '/csv-students' => 'dashboard/schooladmin-dashboard/partials/students/csv-students.php',
    '/classes-csv' => 'dashboard/schooladmin-dashboard/partials/classes/import-classes.php',
    '/parents-csv' => 'dashboard/schooladmin-dashboard/partials/parent/import-parents.php',
    '/school-settings' => 'dashboard/schooladmin-dashboard/partials/settings/settings.php',
    '/school-announcement' => 'dashboard/schooladmin-dashboard/partials/announcement/announcement.php'
];

if (array_key_exists($uri, $routes)) {
    require $routes[$uri];
} else {
    http_response_code(404);
    echo "404 - Page not found";
}
