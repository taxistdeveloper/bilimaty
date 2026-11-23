<?php

declare(strict_types=1);

use App\Controllers\ReportController;
use App\Core\Router;

/**
 * Reports hub and academic reports by group (scope is checked in ReportService).
 *
 * @var Router $router
 */

$router->get('/reports', [ReportController::class, 'index'], ['can:reports.view,workload.view,workload.manage,surveys.results'], 'reports');
$router->get('/reports/performance', [ReportController::class, 'performance'], ['can:reports.view'], 'reports.performance');
$router->get('/reports/attendance', [ReportController::class, 'attendance'], ['can:reports.view'], 'reports.attendance');
