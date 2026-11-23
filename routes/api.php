<?php

declare(strict_types=1);

use App\Controllers\Api\JournalController;
use App\Controllers\Api\NotificationController;
use App\Controllers\Api\ScheduleController;
use App\Controllers\GroupController;
use App\Controllers\TranscriptController;
use App\Core\Router;

/**
 * Internal JSON API, mounted under /api. Every route requires a session and
 * passes the global CSRF check for state-changing methods.
 *
 * @var Router $router
 */

$router->group(['middleware' => ['auth', 'password.fresh']], function (Router $router): void {
    $router->get('/notifications', [NotificationController::class, 'index'], [], 'api.notifications');
    $router->post('/notifications/read', [NotificationController::class, 'markAllRead'], [], 'api.notifications.read');

    // Groups visible to the user (AccessScope) and their students (no personal identifiers).
    $router->get('/groups', [GroupController::class, 'apiIndex'], ['can:groups.view'], 'api.groups');
    $router->get('/groups/{id:\d+}/students', [GroupController::class, 'apiStudents'], ['can:groups.view'], 'api.groups.students');

    // Personal assistant: answers about the user's own timetable, exams and grades.
    $router->post('/assistant', [App\Controllers\Api\AssistantController::class, 'ask'], [], 'api.assistant');
    $router->post('/assistant/listen', [App\Controllers\Api\AssistantController::class, 'listen'], [], 'api.assistant.listen');
    $router->post('/assistant/speech', [App\Controllers\Api\AssistantController::class, 'speech'], [], 'api.assistant.speech');
    $router->get('/assistant/speech/{key:[a-f0-9]{64}}', [App\Controllers\Api\AssistantController::class, 'audio']);

    // Timetable (see App\Controllers\Api\ScheduleController).
    $router->get('/schedule', [ScheduleController::class, 'index'], ['can:schedule.view'], 'api.schedule');
    $router->get('/schedule/{id:\d+}', [ScheduleController::class, 'show'], ['can:schedule.view']);
    $router->post('/schedule', [ScheduleController::class, 'store'], ['can:schedule.manage']);
    $router->put('/schedule/{id:\d+}', [ScheduleController::class, 'update'], ['can:schedule.manage']);
    $router->delete('/schedule/{id:\d+}', [ScheduleController::class, 'destroy'], ['can:schedule.manage']);

    // Electronic journal (record-level rules in App\Policies\JournalPolicy).
    $router->get('/journals/{id:\d+}', [JournalController::class, 'show'], ['can:journal.view,journal.manage'], 'api.journals.show');
    $router->post('/journals/{id:\d+}/grades', [JournalController::class, 'save'], ['can:journal.manage'], 'api.journals.grades');

    // Live transcript data (staff within scope, or the student himself).
    $router->get('/students/{id:\d+}/transcript', [TranscriptController::class, 'api'], ['can:transcripts.view'], 'api.students.transcript');

    // Workload plan/fact (same filters and scope as /workload/reports)
    $router->get('/workloads', [App\Controllers\WorkloadController::class, 'api'], ['can:workload.view,workload.manage'], 'api.workloads');

    // A student files a request (the record-level rules are in DocumentService)
    $router->post('/document-requests', [App\Controllers\DocumentController::class, 'apiStore'], ['can:documents.request'], 'api.document_requests');
});
