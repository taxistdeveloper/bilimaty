<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Repositories\ScheduleRepository;
use App\Services\ScheduleService;

/**
 * Stage 5 demo timetable for the semester that contains today (or the first semester
 * of the active year). Entries go through ScheduleService, so the demo obeys the same
 * conflict, curriculum, subgroup and parity rules as the UI. Runs once.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_schedule_seeded'") === '1') {
        $out('Demo (timetable): already seeded, skipped.');

        return;
    }
    $repo = new ScheduleRepository();
    $semester = $repo->semesterOn(date('Y-m-d'));
    if ($semester === null) {
        $first = Database::fetchColumn("SELECT s.id FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE y.status = 'active' ORDER BY s.number LIMIT 1");
        $semester = $first !== null ? $repo->semester((int) $first) : null;
    }
    $teacher = static fn (string $login): ?int => ($id = Database::fetchColumn('SELECT t.id FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.login = ?', [$login])) === null ? null : (int) $id;
    $group = static fn (string $code): ?int => ($id = Database::fetchColumn('SELECT id FROM `groups` WHERE code = ?', [$code])) === null ? null : (int) $id;
    $subject = static fn (string $code): ?int => ($id = Database::fetchColumn('SELECT id FROM subjects WHERE code = ?', [$code])) === null ? null : (int) $id;
    $room = static fn (string $number): ?int => ($id = Database::fetchColumn("SELECT id FROM classrooms WHERE number = ? AND building = ''", [$number])) === null ? null : (int) $id;
    $period = static fn (int $n): ?int => ($id = Database::fetchColumn('SELECT id FROM lesson_periods WHERE number = ?', [$n])) === null ? null : (int) $id;

    if ($semester === null || $group('ПО-26-1') === null || $teacher('kim.os') === null || $period(1) === null) {
        $out('Demo (timetable): needs stage 3–4 demo data and the bell schedule, skipped.');

        return;
    }

    // group, subgroup, subject, teacher login, room, weekday, period, parity, type
    $plan = [
        ['ПО-26-1', null, 'ООД 05', 'nurlanova.ze', '101', 1, 1, 'every', 'lecture'],
        ['ПО-26-1', null, 'ООД 01', 'smagulova.dk', '105', 1, 2, 'every', 'practice'],
        ['ПО-26-1', 1, 'ООД 07', 'kim.os', '201', 2, 1, 'every', 'lab'],
        ['ПО-26-1', 2, 'ООД 07', 'petrov.av', '202', 2, 1, 'every', 'lab'],
        ['ПО-26-1', null, 'ООД 02', 'petrov.av', '105', 2, 2, 'every', 'lecture'],
        ['ПО-26-1', null, 'ООД 05', 'nurlanova.ze', '101', 3, 1, 'odd', 'practice'],
        ['ПО-26-1', null, 'ООД 01', 'smagulova.dk', '105', 3, 1, 'even', 'practice'],
        ['ПО-26-1', null, 'ООД 02', 'petrov.av', '105', 4, 2, 'every', 'practice'],
        ['ПО-26-1', null, 'ООД 05', 'nurlanova.ze', '101', 5, 1, 'every', 'practice'],
        ['ПО-25-1', null, 'БМ 01', 'kim.os', '201', 1, 1, 'every', 'lecture'],
        ['ПО-25-1', null, 'БМ 03', 'seitqali.ab', '202', 1, 2, 'every', 'lab'],
        ['ПО-25-1', null, 'ООД 05', 'nurlanova.ze', '101', 2, 2, 'every', 'lecture'],
        ['ПО-25-1', null, 'БМ 01', 'kim.os', '201', 3, 2, 'every', 'lab'],
        ['ПО-25-1', null, 'БМ 03', 'seitqali.ab', '202', 4, 1, 'every', 'lecture'],
        ['ВТ-25-1', null, 'ПМ 02', 'seitqali.ab', '210', 1, 1, 'every', 'lecture'],
        ['ВТ-25-1', null, 'ООД 05', 'nurlanova.ze', '101', 1, 3, 'every', 'lecture'],
        ['ВТ-25-1', null, 'ПМ 02', 'seitqali.ab', '210', 2, 1, 'every', 'lab'],
        ['ВТ-25-1', null, 'ООД 07', 'kim.os', '201', 4, 2, 'every', 'practice'],
        ['УА-26-1', null, 'ПМ 05', 'omarov.dm', '301', 1, 1, 'every', 'lecture'],
        ['УА-26-1', null, 'ООД 05', 'nurlanova.ze', '101', 1, 2, 'every', 'practice'],
        ['УА-26-1', null, 'ООД 01', 'smagulova.dk', '105', 2, 1, 'every', 'practice'],
        ['УА-26-1', null, 'ПМ 05', 'omarov.dm', '301', 3, 2, 'every', 'practice'],
        ['УА-26-1', null, 'ООД 02', 'petrov.av', '105', 5, 2, 'every', 'lecture'],
    ];

    $service = new ScheduleService($repo);
    $ids = [];
    foreach ($plan as $i => [$g, $sub, $s, $t, $r, $wd, $p, $parity, $type]) {
        $ids[$i] = $service->saveEntry([
            'semester_id' => (int) $semester['id'], 'group_id' => $group($g), 'subgroup' => $sub, 'subject_id' => $subject($s),
            'teacher_id' => $teacher($t), 'classroom_id' => $room($r), 'weekday' => $wd, 'lesson_period_id' => $period($p),
            'week_parity' => $parity, 'lesson_type' => $type, 'note' => null,
        ]);
    }
    $out('Demo: ' . count($ids) . ' timetable lessons created for semester ' . $semester['number'] . ' of ' . $semester['year_title'] . '.');

    // One substitution and one rescheduling in the current (or first) week of the semester.
    $today = max(date('Y-m-d'), (string) $semester['start_date']);
    $monday = (new DateTimeImmutable($today))->modify('monday this week');
    if ($monday->format('Y-m-d') < $semester['start_date']) {
        $monday = $monday->modify('+7 days');
    }
    try {
        $service->changeOccurrence($repo->find($ids[0]), $monday->format('Y-m-d'), [
            'change_type' => 'modify', 'new_teacher_id' => $teacher('petrov.av'), 'new_classroom_id' => null,
            'new_date' => null, 'new_lesson_period_id' => null, 'reason' => 'Больничный лист (демо)',
        ]);
        $service->changeOccurrence($repo->find($ids[7]), $monday->modify('+3 days')->format('Y-m-d'), [
            'change_type' => 'reschedule', 'new_teacher_id' => null, 'new_classroom_id' => null,
            'new_date' => $monday->modify('+4 days')->format('Y-m-d'), 'new_lesson_period_id' => $period(3), 'reason' => 'Общеколледжное мероприятие (демо)',
        ]);
        $out('Demo: substitution and rescheduling added for the week of ' . $monday->format('d.m.Y') . '.');
    } catch (App\Core\ValidationException $e) {
        $out('Demo: changes skipped (' . implode(' ', array_merge(...array_values($e->errors))) . ').');
    }

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_schedule_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
