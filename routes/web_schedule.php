<?php

declare(strict_types=1);

use App\Controllers\ScheduleController;
use App\Core\Router;

/**
 * Stage 5: timetable pages. Regular lessons are edited through /api/schedule.
 *
 * @var Router $router
 */

$router->get('/schedule', [ScheduleController::class, 'index'], ['can:schedule.view'], 'schedule');
$router->get('/schedule/changes', [ScheduleController::class, 'changes'], ['can:schedule.view'], 'schedule.changes');
$router->post('/schedule/entries/{id:\d+}/changes', [ScheduleController::class, 'storeChange'], ['can:schedule.manage']);
$router->delete('/schedule/changes/{id:\d+}', [ScheduleController::class, 'destroyChange'], ['can:schedule.manage']);
