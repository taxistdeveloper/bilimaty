<?php

declare(strict_types=1);

/**
 * Personnel settings. The values below are defaults to be confirmed by the college
 * (labour law and internal rules); they are not hard-coded anywhere else.
 */
return [
    // Personnel order number: <prefix>-<year>-<sequence>
    'order_prefix' => 'К',

    // Annual paid leave, calendar days (per employee it can be overridden in the personnel file).
    'annual_leave_days' => [
        'teaching' => 56,
        'administrative' => 24,
        'support' => 24,
    ],

    // Qualification categories of teaching staff (key => label key hr.categories.<key>).
    'categories' => ['none', 'teacher', 'moderator', 'expert', 'researcher', 'master', 'second', 'first', 'highest'],

    // Reminders on the HR dashboard.
    'category_warning_days' => 180,   // category expires within N days
    'training_period_years' => 5,     // last professional development older than N years
];
