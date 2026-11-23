<?php

declare(strict_types=1);

/**
 * Timetable settings.
 */
return [
    // Teaching days: 1 = Monday … 6 = Saturday.
    'weekdays' => [1, 2, 3, 4, 5, 6],

    /*
     * Week parity ("числитель / знаменатель") is counted from the week that contains the
     * first day of the semester: that week is odd (1), the next one even (2), and so on.
     */
    'parity_basis' => 'semester_start',

    /*
     * Regular lessons do not take place for a group while an academic calendar event
     * of one of these types covers the date for that group (college-wide, its course or
     * the group itself).
     */
    'suspending_event_types' => ['holiday', 'public_holiday', 'exam_session', 'practice', 'final_attestation'],

    // Default bell schedule created by the seeder when lesson_periods is empty (editable in Dictionaries).
    'default_periods' => [
        1 => ['08:30', '10:00'],
        2 => ['10:10', '11:40'],
        3 => ['12:10', '13:40'],
        4 => ['13:50', '15:20'],
        5 => ['15:30', '17:00'],
        6 => ['17:10', '18:40'],
    ],
];
