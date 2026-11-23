<?php

declare(strict_types=1);

/**
 * Front controller: every HTTP request enters here.
 */

// PHP built-in server (php -S): serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/bootstrap.php';
$app->run();
