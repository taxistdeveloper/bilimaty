<?php

declare(strict_types=1);

use App\Controllers\Platform\PlatformAuthController;
use App\Controllers\Platform\ManageController;
use App\Controllers\Platform\PlatformController;
use App\Core\Router;

/**
 * Owner's panel at <APP_URL>/platform (only with TENANCY=true). Separate accounts and session.
 *
 * @var Router $router
 */

$router->get('/lang/{locale:[a-z]{2}}', [App\Controllers\LocaleController::class, 'switch']);
$router->get('/login', [PlatformAuthController::class, 'showLogin'], [], 'platform.login');
$router->post('/login', [PlatformAuthController::class, 'login']);

$router->group(['middleware' => ['platform']], function (Router $router): void {
    $router->post('/logout', [PlatformAuthController::class, 'logout']);
    $router->get('/password', [PlatformAuthController::class, 'showPassword']);
    $router->post('/password', [PlatformAuthController::class, 'password']);

    $router->get('/', [PlatformController::class, 'index'], [], 'platform.home');
    $router->get('/colleges/create', [PlatformController::class, 'create']);
    $router->post('/colleges', [PlatformController::class, 'store']);
    $router->get('/colleges/{id:\d+}', [PlatformController::class, 'show']);
    $router->get('/colleges/{id:\d+}/edit', [PlatformController::class, 'edit']);
    $router->put('/colleges/{id:\d+}', [PlatformController::class, 'update']);
    $router->post('/colleges/{id:\d+}/status', [PlatformController::class, 'status']);
    $router->post('/colleges/{id:\d+}/admin-password', [PlatformController::class, 'adminPassword']);
    $router->post('/colleges/{id:\d+}/invoices', [PlatformController::class, 'storeInvoice']);
    $router->get('/invoices/{id:\d+}', [PlatformController::class, 'invoice']);
    $router->post('/invoices/{id:\d+}/pay', [PlatformController::class, 'payInvoice']);
    $router->post('/invoices/{id:\d+}/cancel', [PlatformController::class, 'cancelInvoice']);
    $router->post('/colleges/{id:\d+}/impersonate', [ManageController::class, 'impersonate']);
    $router->get('/colleges/{id:\d+}/users', [ManageController::class, 'users']);
    $router->post('/colleges/{id:\d+}/users', [ManageController::class, 'addAdmin']);
    $router->post('/colleges/{id:\d+}/users/{userId:\d+}/active', [ManageController::class, 'userActive']);
    $router->post('/colleges/{id:\d+}/users/{userId:\d+}/password', [ManageController::class, 'userPassword']);
    $router->post('/colleges/{id:\d+}/backups', [ManageController::class, 'backup']);
    // No file extension in the address: the PHP built-in server would treat it as a static file.
    $router->get('/colleges/{id:\d+}/backups/{name:[a-z0-9\-]+}/download', [ManageController::class, 'downloadBackup']);
    $router->post('/colleges/{id:\d+}/archive', [ManageController::class, 'archive']);
    $router->post('/colleges/{id:\d+}/delete-database', [ManageController::class, 'deleteDatabase']);
    $router->get('/plans', [ManageController::class, 'plans']);
    $router->post('/plans', [ManageController::class, 'storePlan']);
    $router->put('/plans/{id:\d+}', [ManageController::class, 'updatePlan']);
    $router->get('/announcements', [ManageController::class, 'announcements']);
    $router->post('/announcements', [ManageController::class, 'storeAnnouncement']);
    $router->delete('/announcements/{id:\d+}', [ManageController::class, 'deleteAnnouncement']);
    $router->get('/reports', [PlatformController::class, 'reports']);
    $router->get('/settings', [PlatformController::class, 'settings']);
    $router->put('/settings', [PlatformController::class, 'saveSettings']);
});
