<?php

declare(strict_types=1);

use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\LocaleController;
use App\Controllers\ProfileController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\TranscriptController;
use App\Controllers\UserController;
use App\Core\Router;

/** @var Router $router */

$router->get('/', [HomeController::class, 'index'], [], 'home');
$router->get('/lang/{locale:[a-z]{2}}', [LocaleController::class, 'switch'], [], 'locale.switch');

// Public transcript verification (QR code): document facts only, no personal data, no login.
$router->get('/verify', [TranscriptController::class, 'verify'], [], 'verify');
// Several colleges: one-time entry of the platform owner (link from the owner's panel).
$router->get('/owner-login', [App\Controllers\OwnerLoginController::class, 'enter']);
$router->get('/verify/{code:[A-Fa-f0-9]{32}}', [TranscriptController::class, 'verify']);

$router->group(['middleware' => ['guest']], function (Router $router): void {
    $router->get('/login', [AuthController::class, 'showLogin'], [], 'login');
    $router->post('/login', [AuthController::class, 'login']);
});

$router->group(['middleware' => ['auth', 'password.fresh']], function (Router $router): void {
    $router->post('/logout', [AuthController::class, 'logout'], [], 'logout');
    // Demo mode (not in production): "view as role" for presentations.
    $router->post('/demo/role', [App\Controllers\DemoRoleController::class, 'switch'], [], 'demo.role');
    $router->post('/demo/back', [App\Controllers\DemoRoleController::class, 'restore'], [], 'demo.back');
    $router->get('/dashboard', [DashboardController::class, 'index'], ['can:dashboard.view'], 'dashboard');

    // Personal cabinet: always the signed-in user, no id in the URL.
    $router->get('/profile', [ProfileController::class, 'show'], [], 'profile');
    $router->put('/profile', [ProfileController::class, 'update']);
    $router->get('/profile/password', [ProfileController::class, 'password'], [], 'profile.password');
    $router->put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Users. Record-level rules (super admin, self) are checked by UserPolicy in the controller.
    $router->get('/users', [UserController::class, 'index'], ['can:users.view'], 'users.index');
    $router->get('/users/create', [UserController::class, 'create'], ['can:users.manage'], 'users.create');
    $router->post('/users', [UserController::class, 'store'], ['can:users.manage']);
    $router->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], ['can:users.manage'], 'users.edit');
    $router->put('/users/{id:\d+}', [UserController::class, 'update'], ['can:users.manage']);
    $router->post('/users/{id:\d+}/toggle-active', [UserController::class, 'toggleActive'], ['can:users.manage']);
    $router->post('/users/{id:\d+}/reset-password', [UserController::class, 'resetPassword'], ['can:users.manage']);

    // Roles and the permission matrix.
    $router->group(['middleware' => ['can:roles.manage']], function (Router $router): void {
        $router->get('/roles', [RoleController::class, 'index'], [], 'roles.index');
        $router->post('/roles', [RoleController::class, 'store']);
        $router->get('/roles/{id:\d+}/edit', [RoleController::class, 'edit'], [], 'roles.edit');
        $router->put('/roles/{id:\d+}', [RoleController::class, 'update']);
        $router->delete('/roles/{id:\d+}', [RoleController::class, 'destroy']);
    });

    $router->get('/audit', [AuditController::class, 'index'], ['can:audit.view'], 'audit.index');

    $router->get('/settings', [SettingsController::class, 'edit'], ['can:settings.manage'], 'settings');
    $router->put('/settings', [SettingsController::class, 'update'], ['can:settings.manage']);

    require __DIR__ . '/web_academic.php';
    require __DIR__ . '/web_process.php';
    require __DIR__ . '/web_schedule.php';
    require __DIR__ . '/web_journal.php';
    require __DIR__ . '/web_exams.php';
    require __DIR__ . '/web_workload.php';
    require __DIR__ . '/web_documents.php';
    require __DIR__ . '/web_surveys.php';
    require __DIR__ . '/web_children.php';
    require __DIR__ . '/web_reports.php';
    require __DIR__ . '/web_hr.php';
    require __DIR__ . '/web_practice.php';
});
