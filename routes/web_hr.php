<?php

declare(strict_types=1);

use App\Controllers\HrController;
use App\Controllers\LeaveController;
use App\Core\Router;

/**
 * Personnel department (HR). Viewing: hr.view (department heads — their departments, checked in the
 * controllers); changes: hr.manage. Own leave requests: leave.request.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'hr', 'middleware' => ['can:hr.view,hr.manage']], function (Router $router): void {
    $router->get('/', [HrController::class, 'index'], [], 'hr');
    $router->get('/employees', [HrController::class, 'employees'], [], 'hr.employees');
    $router->get('/employees/create', [HrController::class, 'create'], ['can:hr.manage']);
    $router->post('/employees', [HrController::class, 'store'], ['can:hr.manage']);
    $router->post('/employees/register-teachers', [HrController::class, 'registerTeachers'], ['can:hr.manage']);
    $router->get('/employees/{id:\d+}', [HrController::class, 'show']);
    $router->get('/employees/{id:\d+}/edit', [HrController::class, 'edit'], ['can:hr.manage']);
    $router->put('/employees/{id:\d+}', [HrController::class, 'update'], ['can:hr.manage']);
    $router->post('/employees/{id:\d+}/orders', [HrController::class, 'storeOrder'], ['can:hr.manage']);
    $router->post('/employees/{id:\d+}/leaves', [LeaveController::class, 'record'], ['can:hr.manage']);
    $router->post('/employees/{id:\d+}/trainings', [HrController::class, 'storeTraining'], ['can:hr.manage']);
    $router->delete('/trainings/{id:\d+}', [HrController::class, 'deleteTraining'], ['can:hr.manage']);

    $router->get('/orders', [HrController::class, 'orders'], [], 'hr.orders');
    $router->get('/orders/{id:\d+}', [HrController::class, 'order']);
    $router->post('/orders/{id:\d+}/sign', [HrController::class, 'signOrder'], ['can:hr.manage']);
    $router->post('/orders/{id:\d+}/cancel', [HrController::class, 'cancelOrder'], ['can:hr.manage']);

    $router->get('/leaves', [LeaveController::class, 'index'], [], 'hr.leaves');
    $router->post('/leaves/{id:\d+}/approve', [LeaveController::class, 'approve'], ['can:hr.manage']);
    $router->post('/leaves/{id:\d+}/reject', [LeaveController::class, 'reject'], ['can:hr.manage']);
    $router->post('/leaves/{id:\d+}/cancel', [LeaveController::class, 'cancel'], ['can:hr.manage']);

    $router->get('/qualification', [HrController::class, 'qualification'], [], 'hr.qualification');
});

$router->get('/my/leaves', [LeaveController::class, 'my'], ['can:leave.request'], 'my.leaves');
$router->post('/my/leaves', [LeaveController::class, 'request'], ['can:leave.request']);
$router->post('/my/leaves/{id:\d+}/cancel', [LeaveController::class, 'cancelOwn'], ['can:leave.request']);
