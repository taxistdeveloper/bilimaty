<?php

declare(strict_types=1);

/**
 * Sidebar menu. An item is shown when the user has ANY of its permissions.
 * 'stage' marks modules not implemented yet: they are rendered disabled ("coming soon").
 * 'roles' (optional) additionally limits an item to users having one of these roles.
 */
return [
    ['section' => 'nav.section_main'],
    ['label' => 'nav.dashboard', 'icon' => 'bi-grid-1x2', 'path' => '/dashboard', 'can' => ['dashboard.view']],
    ['label' => 'nav.children', 'icon' => 'bi-people', 'path' => '/children', 'can' => ['children.view'], 'roles' => ['parent']],

    ['section' => 'nav.section_academic'],
    ['label' => 'nav.schedule', 'icon' => 'bi-calendar-week', 'path' => '/schedule', 'can' => ['schedule.view']],
    ['label' => 'nav.calendar', 'icon' => 'bi-calendar3', 'path' => '/calendar', 'can' => ['calendar.view']],
    ['label' => 'nav.curricula', 'icon' => 'bi-journal-text', 'path' => '/curricula', 'can' => ['curricula.view', 'curricula.manage']],
    ['label' => 'nav.academic_years', 'icon' => 'bi-calendar-range', 'path' => '/academic-years', 'can' => ['academic_years.manage']],
    ['label' => 'nav.my_groups', 'icon' => 'bi-collection', 'path' => '/groups/my', 'can' => ['journal.manage'], 'roles' => ['teacher']],
    ['label' => 'nav.my_subjects', 'icon' => 'bi-book', 'path' => '/journal/subjects', 'can' => ['journal.manage'], 'roles' => ['teacher']],
    ['label' => 'nav.journal', 'icon' => 'bi-journal-bookmark', 'path' => '/journal', 'can' => ['journal.view', 'journal.manage']],
    ['label' => 'nav.attendance', 'icon' => 'bi-calendar-check', 'path' => '/reports/attendance', 'can' => ['reports.view'], 'roles' => ['teacher', 'curator']],
    ['label' => 'nav.my_grades', 'icon' => 'bi-mortarboard', 'path' => '/grades', 'can' => ['journal.view'], 'roles' => ['student']],
    ['label' => 'nav.exams', 'icon' => 'bi-clipboard-check', 'path' => '/exams', 'can' => ['exams.view', 'exams.manage']],
    ['label' => 'nav.transcripts', 'icon' => 'bi-file-earmark-text', 'path' => '/transcripts', 'can' => ['transcripts.view']],
    ['label' => 'nav.practice', 'icon' => 'bi-briefcase', 'path' => '/practice', 'can' => ['practice.view', 'practice.manage', 'practice.fill']],
    ['label' => 'nav.workload', 'icon' => 'bi-hourglass-split', 'path' => '/workload', 'can' => ['workload.view', 'workload.manage']],

    ['section' => 'nav.section_people'],
    ['label' => 'nav.students', 'icon' => 'bi-people', 'path' => '/students', 'can' => ['students.view']],
    ['label' => 'nav.teachers', 'icon' => 'bi-person-badge', 'path' => '/teachers', 'can' => ['teachers.view']],
    ['label' => 'nav.groups', 'icon' => 'bi-collection', 'path' => '/groups', 'can' => ['groups.view']],
    ['label' => 'nav.hr', 'icon' => 'bi-person-vcard', 'path' => '/hr', 'can' => ['hr.view', 'hr.manage']],

    ['section' => 'nav.section_services'],
    ['label' => 'nav.my_leaves', 'icon' => 'bi-calendar2-heart', 'path' => '/my/leaves', 'can' => ['leave.request']],
    ['label' => 'nav.documents', 'icon' => 'bi-envelope-paper', 'path' => '/documents', 'can' => ['documents.request', 'documents.process']],
    ['label' => 'nav.surveys', 'icon' => 'bi-ui-checks', 'path' => '/surveys', 'can' => ['surveys.respond', 'surveys.manage', 'surveys.results']],
    ['label' => 'nav.reports', 'icon' => 'bi-bar-chart-line', 'path' => '/reports', 'can' => ['reports.view', 'workload.view', 'workload.manage', 'surveys.results']],

    ['section' => 'nav.section_admin'],
    ['label' => 'nav.users', 'icon' => 'bi-person-gear', 'path' => '/users', 'can' => ['users.view']],
    ['label' => 'nav.roles', 'icon' => 'bi-key', 'path' => '/roles', 'can' => ['roles.manage']],
    ['label' => 'nav.dictionaries', 'icon' => 'bi-book', 'path' => '/dictionaries', 'can' => ['dictionaries.manage']],
    ['label' => 'nav.grading', 'icon' => 'bi-sliders', 'path' => '/grading-schemes', 'can' => ['grading.manage']],
    ['label' => 'nav.audit', 'icon' => 'bi-shield-check', 'path' => '/audit', 'can' => ['audit.view']],
    ['label' => 'nav.settings', 'icon' => 'bi-gear', 'path' => '/settings', 'can' => ['settings.manage']],
];
