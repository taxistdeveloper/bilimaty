<?php

declare(strict_types=1);

/**
 * RBAC catalogue — the single source of truth for the RBAC seeder.
 * Changing this file and running `php cli.php db:seed` syncs roles and permissions.
 * super_admin is not listed in role grants: Auth::can() always allows it.
 */
return [
    'permissions' => [
        // slug => [module, name_ru, name_kk]
        'dashboard.view' => ['system', 'Просмотр главной страницы', 'Басты бетті қарау'],
        'users.view' => ['system', 'Просмотр пользователей', 'Пайдаланушыларды қарау'],
        'users.manage' => ['system', 'Управление пользователями', 'Пайдаланушыларды басқару'],
        'roles.manage' => ['system', 'Управление ролями и правами', 'Рөлдер мен құқықтарды басқару'],
        'settings.manage' => ['system', 'Настройки системы', 'Жүйе баптаулары'],
        'audit.view' => ['system', 'Журнал аудита', 'Аудит журналы'],
        'dictionaries.manage' => ['system', 'Управление справочниками', 'Анықтамалықтарды басқару'],

        'students.view' => ['people', 'Просмотр студентов', 'Студенттерді қарау'],
        'students.manage' => ['people', 'Управление студентами', 'Студенттерді басқару'],
        'teachers.view' => ['people', 'Просмотр преподавателей', 'Оқытушыларды қарау'],
        'teachers.manage' => ['people', 'Управление преподавателями', 'Оқытушыларды басқару'],
        'groups.view' => ['academic', 'Просмотр групп', 'Топтарды қарау'],
        'groups.manage' => ['academic', 'Управление группами', 'Топтарды басқару'],
        'academic_years.manage' => ['academic', 'Учебные годы и семестры', 'Оқу жылдары мен семестрлер'],
        'calendar.view' => ['academic', 'Просмотр академического календаря', 'Академиялық күнтізбені қарау'],
        'calendar.manage' => ['academic', 'Управление академическим календарём', 'Академиялық күнтізбені басқару'],
        'curricula.view' => ['academic', 'Просмотр учебных планов', 'Оқу жоспарларын қарау'],
        'curricula.manage' => ['academic', 'Управление учебными планами', 'Оқу жоспарларын басқару'],

        'schedule.view' => ['schedule', 'Просмотр расписания', 'Сабақ кестесін қарау'],
        'schedule.manage' => ['schedule', 'Управление расписанием', 'Сабақ кестесін басқару'],

        'journal.view' => ['journal', 'Просмотр электронного журнала', 'Электрондық журналды қарау'],
        'journal.manage' => ['journal', 'Ведение электронного журнала', 'Электрондық журналды жүргізу'],
        'journal.approve' => ['journal', 'Утверждение журналов', 'Журналдарды бекіту'],
        'grading.manage' => ['journal', 'Настройка схем оценивания', 'Бағалау схемаларын баптау'],

        'exams.view' => ['exams', 'Просмотр экзаменов', 'Емтихандарды қарау'],
        'exams.manage' => ['exams', 'Управление экзаменами', 'Емтихандарды басқару'],
        'transcripts.view' => ['exams', 'Просмотр транскриптов', 'Транскрипттерді қарау'],
        'transcripts.issue' => ['exams', 'Выдача транскриптов', 'Транскрипттерді беру'],

        'workload.view' => ['workload', 'Просмотр учебной нагрузки', 'Оқу жүктемесін қарау'],
        'workload.manage' => ['workload', 'Планирование учебной нагрузки', 'Оқу жүктемесін жоспарлау'],

        'practice.view' => ['practice', 'Практика: просмотр графиков', 'Практика: кестелерді қарау'],
        'practice.manage' => ['practice', 'Практика: назначение и приёмка графиков', 'Практика: тағайындау және кестелерді қабылдау'],
        'practice.fill' => ['practice', 'Практика: заполнение своего графика', 'Практика: өз кестесін толтыру'],

        'documents.request' => ['documents', 'Подача заявлений', 'Өтініш беру'],
        'documents.process' => ['documents', 'Обработка заявлений', 'Өтініштерді өңдеу'],

        'surveys.respond' => ['surveys', 'Прохождение анкет', 'Сауалнамаға қатысу'],
        'surveys.manage' => ['surveys', 'Конструктор анкет', 'Сауалнама құрастырушы'],
        'surveys.results' => ['surveys', 'Результаты анкетирования', 'Сауалнама нәтижелері'],

        'reports.view' => ['reports', 'Отчёты', 'Есептер'],

        'children.view' => ['children', 'Успеваемость своих детей', 'Өз балаларының үлгерімі'],

        'hr.view' => ['hr', 'Кадры: просмотр', 'Кадрлар: қарау'],
        'hr.manage' => ['hr', 'Кадры: личные дела, приказы, отпуска', 'Кадрлар: жеке істер, бұйрықтар, демалыстар'],
        'leave.request' => ['hr', 'Заявки на отпуск и больничный', 'Демалыс пен еңбекке жарамсыздық өтінімдері'],
    ],

    /*
     * Administrative permissions. A user manager may assign a role only if they hold
     * every administrative permission that role contains (prevents privilege escalation
     * through role assignment). Domain permissions (journal, surveys...) are not restricted.
     */
    'administrative' => [
        'users.view', 'users.manage', 'roles.manage', 'settings.manage', 'audit.view', 'dictionaries.manage',
    ],

    /*
     * Roles that see data of the whole college. Everyone else is limited by AccessScope:
     * a department head — to the departments they head, a curator — to the groups they curate.
     */
    'college_wide_roles' => ['super_admin', 'admin', 'deputy_director', 'academic_office', 'hr'],

    'roles' => [
        // slug => [name_ru, name_kk, permissions]
        'super_admin' => ['Супер-администратор', 'Супер-әкімші', []],
        'admin' => ['Администратор колледжа', 'Колледж әкімшісі', [
            'dashboard.view', 'users.view', 'users.manage', 'roles.manage', 'settings.manage', 'audit.view',
            'dictionaries.manage', 'students.view', 'students.manage', 'teachers.view', 'teachers.manage',
            'groups.view', 'groups.manage', 'academic_years.manage', 'calendar.view', 'curricula.view', 'schedule.view',
            'journal.view', 'exams.view', 'transcripts.view', 'workload.view', 'documents.process',
            'surveys.manage', 'surveys.results', 'reports.view', 'hr.view', 'leave.request', 'practice.view', 'practice.manage']],
        'deputy_director' => ['Заместитель директора по УР', 'Директордың оқу ісі жөніндегі орынбасары', [
            'dashboard.view', 'audit.view', 'students.view', 'teachers.view', 'groups.view',
            'academic_years.manage', 'calendar.view', 'calendar.manage', 'curricula.view', 'curricula.manage',
            'schedule.view', 'journal.view', 'journal.approve', 'grading.manage', 'exams.view', 'exams.manage',
            'transcripts.view', 'transcripts.issue', 'workload.view', 'workload.manage',
            'documents.process', 'surveys.manage', 'surveys.results', 'reports.view', 'hr.view', 'leave.request', 'practice.view', 'practice.manage']],
        'department_head' => ['Заведующий отделением', 'Бөлім меңгерушісі', [
            'dashboard.view', 'students.view', 'students.manage', 'teachers.view', 'groups.view', 'groups.manage',
            'calendar.view', 'curricula.view', 'schedule.view', 'journal.view', 'journal.approve', 'exams.view',
            'transcripts.view', 'workload.view', 'documents.process', 'surveys.results', 'reports.view', 'hr.view', 'leave.request', 'practice.view']],
        'academic_office' => ['Учебная часть', 'Оқу бөлімі', [
            'dashboard.view', 'students.view', 'teachers.view', 'groups.view', 'academic_years.manage',
            'calendar.view', 'calendar.manage', 'curricula.view', 'curricula.manage', 'schedule.view', 'schedule.manage',
            'journal.view', 'exams.view', 'exams.manage', 'transcripts.view', 'transcripts.issue',
            'workload.view', 'workload.manage', 'documents.process', 'reports.view', 'leave.request', 'practice.view', 'practice.manage']],
        'teacher' => ['Преподаватель', 'Оқытушы', [
            'dashboard.view', 'calendar.view', 'curricula.view', 'schedule.view', 'journal.view', 'journal.manage',
            'exams.view', 'workload.view', 'surveys.respond', 'reports.view', 'leave.request', 'practice.fill']],
        'curator' => ['Куратор', 'Куратор', [
            'dashboard.view', 'students.view', 'groups.view', 'calendar.view', 'schedule.view',
            'journal.view', 'exams.view', 'surveys.respond', 'reports.view', 'leave.request']],
        'student' => ['Студент', 'Студент', [
            'dashboard.view', 'calendar.view', 'schedule.view', 'journal.view', 'exams.view',
            'transcripts.view', 'documents.request', 'surveys.respond',
        ]],
        'hr' => ['Отдел кадров', 'Кадр бөлімі', [
            'dashboard.view', 'calendar.view', 'teachers.view', 'hr.view', 'hr.manage', 'leave.request', 'reports.view',
        ]],
        // Sees only the students linked to them in student_guardians (grades, attendance, exams).
        'parent' => ['Родитель', 'Ата-ана', [
            'dashboard.view', 'calendar.view', 'children.view',
        ]],
    ],
];
