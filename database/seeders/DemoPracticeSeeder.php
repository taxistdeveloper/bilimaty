<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Repositories\PracticeRepository;
use App\Services\PracticeService;

/**
 * Demo practices in every status, for the presentation: approved, submitted for review, being filled in
 * (with a session today and a row on a Saturday that is not counted), returned with a reason, overdue.
 * Goes through PracticeService, so hours and checks are the same as in the UI. Runs once.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_practice_seeded'") === '1') {
        $out('Demo (practice): already seeded, skipped.');

        return;
    }
    $group = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM `groups` WHERE code = ?', [$code]);
    if ($group('ПО-25-1') === 0 || (int) Database::fetchColumn('SELECT COUNT(*) FROM teachers') < 6) {
        $out('Demo (practice): needs the demo groups and teachers, skipped.');

        return;
    }
    $actor = (int) Database::fetchColumn("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug IN ('academic_office', 'admin', 'super_admin') ORDER BY FIELD(r.slug, 'academic_office', 'admin', 'super_admin'), ur.user_id LIMIT 1");
    $previous = Session::get('auth');
    $as = static function (int $userId): void {
        Session::set('auth', ['user_id' => $userId, 'login_at' => time() + 60]);
        Auth::reset();
    };
    $teacherUser = static fn (int $teacherId): int => (int) Database::fetchColumn('SELECT user_id FROM teachers WHERE id = ?', [$teacherId]);
    $repo = new PracticeRepository();
    $service = new PracticeService($repo);

    /** Working days from a date (weekends and calendar holidays skipped). */
    $workdays = static function (string $from, int $count) use ($repo): array {
        $holidays = array_flip($repo->holidays($from, date('Y-m-d', strtotime($from . ' +120 days'))));
        $days = [];
        for ($d = $from; count($days) < $count; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ((int) date('N', strtotime($d)) <= 5 && !isset($holidays[$d])) {
                $days[] = $d;
            }
        }

        return $days;
    };
    $rows = static function (array $dates, array $topics, string $start, string $end, string $place, array $distance = []): array {
        $out = [];
        foreach ($dates as $i => $date) {
            $out[] = ['topic' => $topics[$i % count($topics)], 'date' => $date, 'time_start' => $start, 'time_end' => $end,
                'place' => in_array($i, $distance, true) ? 'Платформа Microsoft Teams, ссылка в группе WhatsApp' : $place, 'is_distance' => in_array($i, $distance, true) ? '1' : ''];
        }

        return $out;
    };
    $assign = static function (array $data) use ($service, $as, $actor): array {
        $as($actor);
        $id = $service->save($data, null);

        return (new PracticeRepository())->find($id) ?? [];
    };
    $year = (int) date('Y');

    // 1. Approved: educational practice of ПО-25-1 in early December, 36 hours (6 days × 6 h).
    $p = $assign(['teacher_id' => 4, 'group_id' => $group('ПО-25-1'), 'title' => 'Учебная практика', 'practice_type' => 'educational',
        'module_name' => 'БМ 01 Основы программирования', 'hours' => 36, 'deadline' => date('Y-m-d', strtotime('-12 days'))]);
    $as($teacherUser(4));
    $service->saveSchedule($p, $rows($workdays("{$year}-12-01", 6), [
        'БМ 01. Раздел 1. Инструктаж по технике безопасности, среда разработки',
        'БМ 01. Раздел 2. Алгоритмы и блок-схемы: практическая работа',
        'БМ 01. Раздел 3. Ветвления и циклы: решение задач',
        'БМ 01. Раздел 4. Массивы и строки: лабораторная работа',
        'БМ 01. Раздел 5. Функции: мини-проект',
        'БМ 01. Защита отчётов по практике',
    ], '09:00', '15:00', 'Аудитория 201', [3]), 'График составлен по рабочей программе практики.', true);
    $as($actor);
    $service->approve($repo->find((int) $p['id']) ?? []);

    // 2. Submitted, waiting for the academic office: production practice of ВТ-25-1 in May, 72 hours.
    $p = $assign(['teacher_id' => 1, 'group_id' => $group('ВТ-25-1'), 'title' => 'Производственная практика', 'practice_type' => 'production',
        'module_name' => 'ПМ 02 Компьютерные сети', 'hours' => 72, 'deadline' => date('Y-m-d', strtotime('+6 days'))]);
    $as($teacherUser(1));
    $service->saveSchedule($p, $rows($workdays(($year + 1) . '-05-18', 12), [
        'ПМ 02. Знакомство с предприятием, инструктаж по охране труда',
        'ПМ 02. Структурированная кабельная система: монтаж',
        'ПМ 02. Настройка коммутаторов, VLAN',
        'ПМ 02. Маршрутизация, статические маршруты',
        'ПМ 02. DHCP и DNS на предприятии',
        'ПМ 02. Беспроводная сеть офиса',
        'ПМ 02. Мониторинг сети',
        'ПМ 02. Резервное копирование',
        'ПМ 02. Информационная безопасность',
        'ПМ 02. Документирование сети',
        'ПМ 02. Подготовка отчёта',
        'ПМ 02. Защита отчёта по практике',
    ], '09:00', '15:00', 'ТОО «Казтелеком-Сервис», ул. Абая, 12'), '', true);

    // 3. Being filled in by the persona teacher: 36 of 72 hours, a session today, a Saturday row that is not counted.
    $p = $assign(['teacher_id' => 2, 'group_id' => $group('ПО-25-1'), 'title' => 'Производственная практика', 'practice_type' => 'production',
        'module_name' => 'БМ 03 Базы данных', 'hours' => 72, 'deadline' => date('Y-m-d', strtotime('+12 days'))]);
    $days = $workdays(date('Y-m-d'), 5);
    $saturday = date('Y-m-d', strtotime('next saturday'));
    $draft = $rows($days, [
        'БМ 03. Анализ предметной области предприятия',
        'БМ 03. Проектирование схемы базы данных',
        'БМ 03. Создание таблиц и связей',
        'БМ 03. Запросы SQL для отчётов',
        'БМ 03. Резервное копирование БД',
    ], '09:00', '15:00', 'IT-отдел ТОО «Bilimaty Soft», ул. Толе би, 44', [4]);
    $draft[] = ['topic' => 'БМ 03. Консультация по отчёту', 'date' => $saturday, 'time_start' => '10:00', 'time_end' => '12:00', 'place' => 'Аудитория 202', 'is_distance' => ''];
    $as($teacherUser(2));
    $service->saveSchedule($p, $draft, '', false);

    // 4. Returned: a holiday and an empty place had to be fixed.
    $p = $assign(['teacher_id' => 2, 'group_id' => $group('ПО-26-1'), 'title' => 'Ознакомительная практика', 'practice_type' => 'educational',
        'module_name' => 'ООД 07 Информатика', 'hours' => 18, 'deadline' => date('Y-m-d', strtotime('+3 days'))]);
    $as($teacherUser(2));
    $service->saveSchedule($p, $rows(["{$year}-12-11", "{$year}-12-14", "{$year}-12-15"], [
        'Знакомство с колледжем и IT-отделом',
        'Экскурсия в серверную, правила работы',
        'Итоговое занятие, анкета практиканта',
    ], '09:00', '15:00', 'Аудитория 202'), 'Прошу утвердить.', true);
    $as($actor);
    $service->returnForRework($repo->find((int) $p['id']) ?? [], 'Укажите конкретное место для экскурсии (адрес серверной) и добавьте программу экскурсии в тему занятия 2.');

    // 5. Overdue: assigned, nothing filled in, the deadline has passed.
    $assign(['teacher_id' => 5, 'group_id' => $group('УА-26-1'), 'title' => 'Учебная практика', 'practice_type' => 'educational',
        'module_name' => 'ПМ 05 Бухгалтерский учет', 'hours' => 24, 'deadline' => date('Y-m-d', strtotime('-3 days'))]);

    // 6. Just assigned, due later: pre-graduation practice to plan.
    $assign(['teacher_id' => 3, 'group_id' => $group('ПО-25-1'), 'title' => 'Преддипломная практика', 'practice_type' => 'pregraduation',
        'module_name' => null, 'hours' => 144, 'deadline' => date('Y-m-d', strtotime('+40 days'))]);

    Session::set('auth', $previous);
    Auth::reset();
    $out('Demo (practice): 6 practices — approved, submitted, in progress, returned, overdue, assigned.');
    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_practice_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
