<?php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'bilimaty_platform'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => (string) env('DB_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            // unicode_ci works on both MySQL 8 and the MariaDB bundled with XAMPP.
            'collation' => 'utf8mb4_unicode_ci',
            'timezone' => env('DB_TIMEZONE', '+05:00'),
        ],
    ],
];
