<?php

declare(strict_types=1);

use App\Controllers\ExamController;
use App\Controllers\TranscriptController;
use App\Core\Router;

/**
 * Stage 7: ratings & exams (schedule, statements, retakes) and transcripts.
 * Record-level rules are checked in the controllers.
 *
 * @var Router $router
 */

$router->get('/exams/my', [ExamController::class, 'my'], ['can:exams.view'], 'exams.my');
$router->group(['prefix' => 'exams', 'middleware' => ['can:exams.view,exams.manage']], function (Router $router): void {
    $router->get('/', [ExamController::class, 'index'], [], 'exams');
    $router->get('/create', [ExamController::class, 'create'], ['can:exams.manage']);
    $router->post('/', [ExamController::class, 'store'], ['can:exams.manage']);
    $router->get('/{id:\d+}', [ExamController::class, 'show']);
    $router->get('/{id:\d+}/edit', [ExamController::class, 'edit'], ['can:exams.manage']);
    $router->put('/{id:\d+}', [ExamController::class, 'update'], ['can:exams.manage']);
    $router->post('/{id:\d+}/statement', [ExamController::class, 'saveStatement']);
    $router->post('/{id:\d+}/status', [ExamController::class, 'status']);
    $router->get('/{id:\d+}/print', [ExamController::class, 'print']);
    $router->post('/{id:\d+}/retakes', [ExamController::class, 'scheduleRetake'], ['can:exams.manage']);
    $router->post('/{id:\d+}/retakes/{retakeId:\d+}', [ExamController::class, 'completeRetake']);
});

$router->get('/transcripts/my', [TranscriptController::class, 'my'], ['can:transcripts.view'], 'transcripts.my');
$router->group(['prefix' => 'transcripts', 'middleware' => ['can:transcripts.view']], function (Router $router): void {
    $router->get('/', [TranscriptController::class, 'index'], [], 'transcripts');
    $router->get('/{id:\d+}', [TranscriptController::class, 'show']);
    $router->get('/{id:\d+}/export', [TranscriptController::class, 'export']);
    $router->post('/{id:\d+}/revoke', [TranscriptController::class, 'revoke'], ['can:transcripts.issue']);
});
$router->get('/students/{id:\d+}/transcript', [TranscriptController::class, 'preview'], ['can:transcripts.view']);
$router->post('/students/{id:\d+}/transcript/issue', [TranscriptController::class, 'issue'], ['can:transcripts.issue']);
