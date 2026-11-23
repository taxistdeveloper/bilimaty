<?php

declare(strict_types=1);

use App\Controllers\DocumentController;
use App\Core\Router;

/**
 * Stage 9: requests and certificates. Record-level access (own request / processing scope)
 * is checked in the controller.
 *
 * @var Router $router
 */

$router->group(['prefix' => 'documents', 'middleware' => ['can:documents.request,documents.process']], function (Router $router): void {
    $router->get('/', [DocumentController::class, 'index'], [], 'documents');
    $router->get('/create', [DocumentController::class, 'create']);
    $router->post('/', [DocumentController::class, 'store']);
    $router->get('/{id:\d+}', [DocumentController::class, 'show']);
    $router->get('/{id:\d+}/edit', [DocumentController::class, 'edit']);
    $router->put('/{id:\d+}', [DocumentController::class, 'update']);
    $router->delete('/{id:\d+}', [DocumentController::class, 'destroy']);
    $router->post('/{id:\d+}/status', [DocumentController::class, 'status']);
    $router->post('/{id:\d+}/comments', [DocumentController::class, 'comment']);
    $router->get('/{id:\d+}/attachment', [DocumentController::class, 'attachment']);
    $router->get('/{id:\d+}/certificate', [DocumentController::class, 'certificate']);
    $router->post('/{id:\d+}/certificate/revoke', [DocumentController::class, 'revokeCertificate']);
});
