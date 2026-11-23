<?php

declare(strict_types=1);

use App\Controllers\GradingSchemeController;
use App\Controllers\JournalController;
use App\Core\Router;

/**
 * Stage 6: electronic journal and grading schemes.
 * Record-level access is checked by App\Policies\JournalPolicy in the controller.
 *
 * @var Router $router
 */

$router->get('/journal/subjects', [JournalController::class, 'subjects'], ['can:journal.manage'], 'journal.subjects');
$router->get('/grades', [App\Controllers\GradeController::class, 'index'], ['can:journal.view'], 'grades');
$router->get('/grades/{id:\d+}', [App\Controllers\GradeController::class, 'show'], ['can:journal.view']);
$router->get('/journal/my', [JournalController::class, 'my'], ['can:journal.view'], 'journal.my');
$router->get('/journal/my/{id:\d+}', [JournalController::class, 'myShow'], ['can:journal.view']);

$router->group(['prefix' => 'journal', 'middleware' => ['can:journal.view,journal.manage']], function (Router $router): void {
    $router->get('/', [JournalController::class, 'index'], [], 'journal');
    $router->post('/', [JournalController::class, 'store']);
    $router->post('/generate', [JournalController::class, 'generate']);
    $router->get('/{id:\d+}', [JournalController::class, 'show']);
    $router->put('/{id:\d+}/settings', [JournalController::class, 'settings']);
    $router->post('/{id:\d+}/lessons', [JournalController::class, 'saveLesson']);
    $router->delete('/{id:\d+}/lessons/{lessonId:\d+}', [JournalController::class, 'deleteLesson']);
    $router->post('/{id:\d+}/fill', [JournalController::class, 'fill']);
    $router->post('/{id:\d+}/status', [JournalController::class, 'status']);
    $router->get('/{id:\d+}/history', [JournalController::class, 'history']);
    $router->get('/{id:\d+}/export', [JournalController::class, 'export']);
    $router->get('/{id:\d+}/print', [JournalController::class, 'print']);
});

$router->group(['prefix' => 'grading-schemes', 'middleware' => ['can:grading.manage']], function (Router $router): void {
    $router->get('/', [GradingSchemeController::class, 'index'], [], 'grading');
    $router->get('/create', [GradingSchemeController::class, 'create']);
    $router->post('/', [GradingSchemeController::class, 'store']);
    $router->get('/{id:\d+}/edit', [GradingSchemeController::class, 'edit']);
    $router->put('/{id:\d+}', [GradingSchemeController::class, 'update']);
    $router->post('/{id:\d+}/status', [GradingSchemeController::class, 'status']);
});
