<?php

declare(strict_types=1);

use App\Controllers\ChildrenController;
use App\Core\Router;

/**
 * Parent's area: own children only (checked in the controller through student_guardians).
 *
 * @var Router $router
 */

$router->group(['prefix' => 'children', 'middleware' => ['can:children.view']], function (Router $router): void {
    $router->get('/', [ChildrenController::class, 'index'], [], 'children');
    $router->get('/{id:\d+}', [ChildrenController::class, 'show']);
    $router->get('/{id:\d+}/journal/{journalId:\d+}', [ChildrenController::class, 'journal']);
});
