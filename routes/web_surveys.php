<?php

declare(strict_types=1);

use App\Controllers\SurveyController;
use App\Core\Router;

/**
 * Stage 10: surveys — builder, answering, results with comparisons.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'surveys', 'middleware' => ['can:surveys.respond,surveys.manage,surveys.results']], function (Router $router): void {
    $router->get('/', [SurveyController::class, 'index'], [], 'surveys');
    $router->get('/{id:\d+}', [SurveyController::class, 'show']);
    $router->post('/{id:\d+}/answers', [SurveyController::class, 'answer'], ['can:surveys.respond']);
    $router->get('/{id:\d+}/results', [SurveyController::class, 'results']);
    $router->get('/{id:\d+}/results/export', [SurveyController::class, 'export']);
    $router->get('/{id:\d+}/results/print', [SurveyController::class, 'print']);

    $router->get('/create', [SurveyController::class, 'create'], ['can:surveys.manage']);
    $router->post('/', [SurveyController::class, 'store'], ['can:surveys.manage']);
    $router->get('/{id:\d+}/edit', [SurveyController::class, 'edit'], ['can:surveys.manage']);
    $router->put('/{id:\d+}', [SurveyController::class, 'update'], ['can:surveys.manage']);
    $router->post('/{id:\d+}/status', [SurveyController::class, 'status'], ['can:surveys.manage']);
    $router->post('/{id:\d+}/questions', [SurveyController::class, 'storeQuestion'], ['can:surveys.manage']);
    $router->get('/{id:\d+}/questions/{questionId:\d+}/edit', [SurveyController::class, 'editQuestion'], ['can:surveys.manage']);
    $router->put('/{id:\d+}/questions/{questionId:\d+}', [SurveyController::class, 'updateQuestion'], ['can:surveys.manage']);
    $router->delete('/{id:\d+}/questions/{questionId:\d+}', [SurveyController::class, 'deleteQuestion'], ['can:surveys.manage']);
    $router->post('/{id:\d+}/questions/{questionId:\d+}/move', [SurveyController::class, 'moveQuestion'], ['can:surveys.manage']);
});
