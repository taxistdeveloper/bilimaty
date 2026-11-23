<?php

declare(strict_types=1);

use App\Controllers\PracticeController;
use App\Core\Router;

/**
 * Practice: assignments to supervising teachers, "График программы прохождения практики",
 * approval, the practice-on-a-date report. Record-level access is checked in the controller.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'practice', 'middleware' => ['can:practice.view,practice.manage,practice.fill']], function (Router $router): void {
    $router->get('/', [PracticeController::class, 'index'], [], 'practice');
    $router->get('/day', [PracticeController::class, 'day'], [], 'practice.day');
    $router->get('/create', [PracticeController::class, 'create'], ['can:practice.manage']);
    $router->post('/', [PracticeController::class, 'store'], ['can:practice.manage']);
    $router->get('/{id:\d+}', [PracticeController::class, 'show']);
    $router->get('/{id:\d+}/edit', [PracticeController::class, 'edit'], ['can:practice.manage']);
    $router->put('/{id:\d+}', [PracticeController::class, 'update'], ['can:practice.manage']);
    $router->delete('/{id:\d+}', [PracticeController::class, 'destroy'], ['can:practice.manage']);
    $router->post('/{id:\d+}/schedule', [PracticeController::class, 'saveSchedule'], ['can:practice.fill']);
    $router->post('/{id:\d+}/approve', [PracticeController::class, 'approve'], ['can:practice.manage']);
    $router->post('/{id:\d+}/return', [PracticeController::class, 'returnForRework'], ['can:practice.manage']);
    $router->get('/{id:\d+}/print', [PracticeController::class, 'print'], [], 'practice.print');
});
