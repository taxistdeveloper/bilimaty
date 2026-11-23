<?php

declare(strict_types=1);

/**
 * Several colleges on one installation (SaaS). Off by default: a single college works as before.
 *
 * On: every college has its own database and is opened at <APP_URL>/<code>/…;
 * the owner's panel is at <APP_URL>/platform and keeps colleges, invoices and payments
 * in the platform database.
 */
return [
    'enabled' => filter_var(env('TENANCY', false), FILTER_VALIDATE_BOOL),
    'platform_database' => env('PLATFORM_DB_DATABASE', 'bilimaty_platform'),
    // Database of a new college: <prefix><code with "-" replaced by "_">
    'database_prefix' => env('TENANT_DB_PREFIX', 'bilimaty_'),

    // Subscription
    'warn_days' => 14,          // banner for the college administrator before the end
    'grace_days' => 7,          // default days of full access after the end, then read-only
    'trial_days' => 14,         // paid_until of a new college
    'invoice_prefix' => 'СЧ',   // СЧ-YYYY-NNNNN

    // Codes that can never be a college (paths of the site itself).
    'reserved' => ['platform', 'assets', 'api', 'login', 'logout', 'verify', 'admin', 'www', 'static', 'public', 'favicon', 'robots', 'index'],
];
