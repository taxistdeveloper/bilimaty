<?php

declare(strict_types=1);

use App\Controllers\DictionaryController;
use App\Controllers\GroupController;
use App\Controllers\GuardianController;
use App\Controllers\StudentController;
use App\Controllers\TeacherController;
use App\Core\Router;

/**
 * Stage 3: dictionaries, teachers, groups, students.
 * Included from routes/web.php inside the authenticated group.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'dictionaries', 'middleware' => ['can:dictionaries.manage']], function (Router $router): void {
    $router->get('/', [DictionaryController::class, 'hub'], [], 'dictionaries');
    $router->get('/{dictionary:[a-z_]+}', [DictionaryController::class, 'index']);
    $router->get('/{dictionary:[a-z_]+}/create', [DictionaryController::class, 'create']);
    $router->post('/{dictionary:[a-z_]+}', [DictionaryController::class, 'store']);
    $router->get('/{dictionary:[a-z_]+}/{id:\d+}/edit', [DictionaryController::class, 'edit']);
    $router->put('/{dictionary:[a-z_]+}/{id:\d+}', [DictionaryController::class, 'update']);
    $router->delete('/{dictionary:[a-z_]+}/{id:\d+}', [DictionaryController::class, 'destroy']);
});

$router->get('/teachers', [TeacherController::class, 'index'], ['can:teachers.view'], 'teachers.index');
$router->get('/teachers/create', [TeacherController::class, 'create'], ['can:teachers.manage']);
$router->post('/teachers', [TeacherController::class, 'store'], ['can:teachers.manage']);
$router->get('/teachers/{id:\d+}', [TeacherController::class, 'show'], ['can:teachers.view']);
$router->get('/teachers/{id:\d+}/edit', [TeacherController::class, 'edit'], ['can:teachers.manage']);
$router->put('/teachers/{id:\d+}', [TeacherController::class, 'update'], ['can:teachers.manage']);

$router->get('/groups/my', [GroupController::class, 'mine'], ['can:journal.manage'], 'groups.mine');
$router->get('/groups', [GroupController::class, 'index'], ['can:groups.view'], 'groups.index');
$router->get('/groups/create', [GroupController::class, 'create'], ['can:groups.manage']);
$router->post('/groups', [GroupController::class, 'store'], ['can:groups.manage']);
$router->get('/groups/{id:\d+}', [GroupController::class, 'show'], ['can:groups.view']);
$router->get('/groups/{id:\d+}/edit', [GroupController::class, 'edit'], ['can:groups.manage']);
$router->put('/groups/{id:\d+}', [GroupController::class, 'update'], ['can:groups.manage']);
$router->delete('/groups/{id:\d+}', [GroupController::class, 'destroy'], ['can:groups.manage']);

$router->get('/students', [StudentController::class, 'index'], ['can:students.view'], 'students.index');
$router->get('/students/create', [StudentController::class, 'create'], ['can:students.manage']);
$router->post('/students', [StudentController::class, 'store'], ['can:students.manage']);
$router->get('/students/{id:\d+}', [StudentController::class, 'show'], ['can:students.view']);
$router->get('/students/{id:\d+}/edit', [StudentController::class, 'edit'], ['can:students.manage']);
$router->put('/students/{id:\d+}', [StudentController::class, 'update'], ['can:students.manage']);
$router->post('/students/{id:\d+}/transfer', [StudentController::class, 'transfer'], ['can:students.manage']);
$router->post('/students/{id:\d+}/status', [StudentController::class, 'changeStatus'], ['can:students.manage']);
$router->post('/students/{id:\d+}/guardians', [GuardianController::class, 'store'], ['can:students.manage']);
$router->delete('/students/{id:\d+}/guardians/{userId:\d+}', [GuardianController::class, 'destroy'], ['can:students.manage']);

// Bulk import from Excel / CSV (record-level permission is checked in the controller).
$router->group(['prefix' => 'import', 'middleware' => ['can:students.manage,teachers.manage']], function (Router $router): void {
    $router->get('/{type:students|teachers}', [App\Controllers\ImportController::class, 'form']);
    $router->get('/{type:students|teachers}/template', [App\Controllers\ImportController::class, 'template']);
    $router->post('/{type:students|teachers}', [App\Controllers\ImportController::class, 'preview']);
    $router->get('/{type:students|teachers}/preview/{token:[a-f0-9]{32}}', [App\Controllers\ImportController::class, 'show']);
    $router->post('/{type:students|teachers}/preview/{token:[a-f0-9]{32}}', [App\Controllers\ImportController::class, 'commit']);
    $router->get('/{type:students|teachers}/result', [App\Controllers\ImportController::class, 'result']);
    $router->post('/{type:students|teachers}/result/download', [App\Controllers\ImportController::class, 'download']);
});
