<?php
// /public/app/public/index.php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../repo.php';
require_once __DIR__ . '/../grade_repo.php';
require_once __DIR__ . '/../report_repo.php';
require_once __DIR__ . '/../coverage_repo.php';
require_once __DIR__ . '/../coverage_repo.php';

auth_start();

$route  = $_GET['r'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    'login'              => ['login.php',              'GET|POST', 'public'],
    'logout'             => ['logout.php',             'POST',     'auth'],
    'password/force'     => ['password_force.php',     'GET|POST', 'auth'],
    'password'           => ['password.php',           'GET|POST', 'auth'],
    'dashboard'          => ['dashboard.php',          'GET',      'auth'],
    'me/report'          => ['me_report.php',          'GET',      'student'],

    'students'           => ['students_list.php',      'GET',      'auth'],
    'students/new'       => ['students_new.php',       'GET|POST', 'admin'],
    'students/view'      => ['students_view.php',      'GET',      'auth'],
    'students/edit'      => ['students_edit.php',      'GET|POST', 'admin'],

    'teachers'           => ['teachers_list.php',      'GET',      'admin'],
    'teachers/new'       => ['teachers_new.php',       'GET|POST', 'admin'],

    'enrollments'        => ['enrollments_list.php',   'GET',      'admin'],
    'enrollments/new'    => ['enrollments_new.php',    'GET|POST', 'admin'],

    'assignments'        => ['assignments_list.php',   'GET',      'auth'],
    'assignments/new'    => ['assignments_new.php',    'GET|POST', 'admin'],
    'assignments/delete' => ['assignments_delete.php', 'GET|POST', 'admin'],

    'results/entry'      => ['results_entry.php',      'GET|POST', 'auth'],
    'results/anomalies'  => ['results_anomalies.php',  'GET|POST', 'admin'],
    'results/approve'    => ['results_approve.php',    'GET|POST', 'admin'],

    'reports/student'    => ['reports_student.php',    'GET',      'auth'],
    'reports/class'      => ['reports_class.php',      'GET',      'auth'],
    'reports/remarks'    => ['reports_remarks.php',    'GET|POST', 'admin'],
    'reports/publish'    => ['reports_publish.php',    'GET|POST', 'admin'],

    'settings'           => ['settings.php',           'GET|POST', 'admin'],
  'coverage'           => ['coverage.php',           'GET',      'auth'],
'class-arms/subjects'=> ['class_arms_subjects.php','GET|POST', 'admin'],
'exemptions'         => ['exemptions.php',         'GET|POST', 'admin'],
];

if (!isset($routes[$route])) {
    http_response_code(404);
    echo "Not found";
    exit;
}

[$pageFile, $allowedMethods, $access] = $routes[$route];

if (!in_array($method, explode('|', $allowedMethods), true)) {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

// Access gate
if ($access === 'auth')    auth_require();
if ($access === 'admin')   auth_require_role('admin');
if ($access === 'student') auth_require_role('student');

// Force password change on initial login (except for logout + force route)
auth_enforce_password_change($route);

$pageTitle = 'School';
$error = null;

// CSRF on every non-GET
try {
    csrf_verify();
} catch (AppException $e) {
    http_response_code(400);
    $error = $e->getMessage();
    require __DIR__ . '/pages/_error.php';
    exit;
}

ob_start();
try {
    require __DIR__ . '/pages/' . $pageFile;
} catch (AppException $e) {
    $error = $e->getMessage();
    require __DIR__ . '/pages/_error.php';
} catch (Throwable $e) {
    http_response_code(500);
    $error = 'Something went wrong.';
    if (($_ENV['APP_DEBUG'] ?? '1') === '1') {
        $error .= ' — ' . $e->getMessage();
    }
    require __DIR__ . '/pages/_error.php';
}
$content = ob_get_clean();

require __DIR__ . '/layout.php';