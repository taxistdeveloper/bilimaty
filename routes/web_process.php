<?php

declare(strict_types=1);

use App\Controllers\AcademicYearController;
use App\Controllers\CalendarController;
use App\Controllers\CurriculumController;
use App\Core\Router;

/**
 * Stage 4: academic years, academic calendar, curricula.
 * Included from routes/web.php inside the authenticated group.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'academic-years', 'middleware' => ['can:academic_years.manage']], function (Router $router): void {
    $router->get('/', [AcademicYearController::class, 'index'], [], 'years.index');
    $router->get('/create', [AcademicYearController::class, 'create']);
    $router->post('/', [AcademicYearController::class, 'store']);
    $router->get('/{id:\d+}/edit', [AcademicYearController::class, 'edit']);
    $router->put('/{id:\d+}', [AcademicYearController::class, 'update']);
    $router->post('/{id:\d+}/activate', [AcademicYearController::class, 'activate']);
    $router->post('/{id:\d+}/archive', [AcademicYearController::class, 'archive']);
    $router->post('/{id:\d+}/restore', [AcademicYearController::class, 'restore']);
    $router->delete('/{id:\d+}', [AcademicYearController::class, 'destroy']);
});

$router->get('/calendar', [CalendarController::class, 'index'], ['can:calendar.view'], 'calendar');
$router->get('/calendar/print', [CalendarController::class, 'print'], ['can:calendar.view']);
$router->group(['prefix' => 'calendar', 'middleware' => ['can:calendar.manage']], function (Router $router): void {
    $router->get('/create', [CalendarController::class, 'create']);
    $router->post('/', [CalendarController::class, 'store']);
    $router->get('/{id:\d+}/edit', [CalendarController::class, 'edit']);
    $router->put('/{id:\d+}', [CalendarController::class, 'update']);
    $router->delete('/{id:\d+}', [CalendarController::class, 'destroy']);
    $router->post('/holidays/{yearId:\d+}', [CalendarController::class, 'holidays']);
});

$router->get('/curricula', [CurriculumController::class, 'index'], ['can:curricula.view,curricula.manage'], 'curricula.index');
$router->get('/curricula/{id:\d+}', [CurriculumController::class, 'show'], ['can:curricula.view,curricula.manage']);
$router->get('/curricula/{id:\d+}/print', [CurriculumController::class, 'print'], ['can:curricula.view,curricula.manage']);
$router->group(['prefix' => 'curricula', 'middleware' => ['can:curricula.manage']], function (Router $router): void {
    $router->get('/create', [CurriculumController::class, 'create']);
    $router->post('/', [CurriculumController::class, 'store']);
    $router->get('/{id:\d+}/edit', [CurriculumController::class, 'edit']);
    $router->put('/{id:\d+}', [CurriculumController::class, 'update']);
    $router->post('/{id:\d+}/status', [CurriculumController::class, 'status']);
    $router->post('/{id:\d+}/copy', [CurriculumController::class, 'copy']);
    $router->post('/{id:\d+}/items', [CurriculumController::class, 'storeItem']);
    $router->get('/{id:\d+}/items/{itemId:\d+}/edit', [CurriculumController::class, 'editItem']);
    $router->put('/{id:\d+}/items/{itemId:\d+}', [CurriculumController::class, 'updateItem']);
    $router->delete('/{id:\d+}/items/{itemId:\d+}', [CurriculumController::class, 'destroyItem']);
});
