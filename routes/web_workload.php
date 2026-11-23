<?php

declare(strict_types=1);

use App\Controllers\WorkloadController;
use App\Core\Router;

/**
 * Stage 8: teaching workload — plans, distribution of hours, plan/fact, reports.
 * Planning needs workload.manage; record-level scope is checked in the controller.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'workload', 'middleware' => ['can:workload.view,workload.manage']], function (Router $router): void {
    $router->get('/', [WorkloadController::class, 'index'], [], 'workload');
    $router->get('/teachers/{id:\d+}', [WorkloadController::class, 'teacher']);
    $router->post('/teachers/{id:\d+}/plan', [WorkloadController::class, 'savePlan']);
    $router->post('/plans/{planId:\d+}/status', [WorkloadController::class, 'planStatus']);
    $router->post('/plans/{planId:\d+}/assignments', [WorkloadController::class, 'storeAssignment']);
    $router->get('/assignments/{id:\d+}', [WorkloadController::class, 'assignment']);
    $router->put('/assignments/{id:\d+}', [WorkloadController::class, 'updateAssignment']);
    $router->delete('/assignments/{id:\d+}', [WorkloadController::class, 'deleteAssignment']);
    $router->post('/assignments/{id:\d+}/actuals', [WorkloadController::class, 'storeActual']);
    $router->delete('/actuals/{id:\d+}', [WorkloadController::class, 'deleteActual']);
    $router->get('/reports', [WorkloadController::class, 'reports'], [], 'workload.reports');
    $router->get('/reports/export', [WorkloadController::class, 'export']);
    $router->get('/reports/print', [WorkloadController::class, 'print']);
});
