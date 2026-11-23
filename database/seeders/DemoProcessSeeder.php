<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

/**
 * Stage 4 demo data: academic calendar events of the active year and an approved
 * curriculum linked to the first demo group. Runs once (settings.demo_process_seeded);
 * requires the stage 3 demo data. Dates are illustrative, not an official calendar.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_process_seeded'") === '1') {
        $out('Demo (calendar, curricula): already seeded, skipped.');

        return;
    }
    $year = Database::fetch("SELECT * FROM academic_years WHERE status = 'active' LIMIT 1");
    $software = Database::fetchColumn("SELECT id FROM specialties WHERE code = '06130100'");
    if ($year === null || $software === null) {
        $out('Demo (calendar, curricula): needs the active year and stage 3 demo data, skipped.');

        return;
    }
    $y = (int) substr((string) $year['start_date'], 0, 4);
    $n = $y + 1;
    $yearId = (int) $year['id'];

    $event = static function (string $type, string $ru, string $kk, string $start, string $end, ?int $course = null) use ($yearId): void {
        Database::execute(
            'INSERT INTO academic_calendar_events (academic_year_id, type, title_ru, title_kk, start_date, end_date, course)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$yearId, $type, $ru, $kk, $start, $end, $course]
        );
    };
    $event('theory', 'Теоретическое обучение (1 семестр)', 'Теориялық оқыту (1-семестр)', "{$y}-09-01", "{$y}-12-19");
    $event('control_week', 'Рубежный контроль 1', '1-аралық бақылау', "{$y}-10-19", "{$y}-10-24");
    $event('control_week', 'Рубежный контроль 2', '2-аралық бақылау', "{$y}-12-07", "{$y}-12-12");
    $event('exam_session', 'Зимняя экзаменационная сессия', 'Қысқы емтихан сессиясы', "{$y}-12-21", "{$y}-12-31");
    $event('holiday', 'Зимние каникулы', 'Қысқы демалыс', "{$n}-01-01", "{$n}-01-11");
    $event('theory', 'Теоретическое обучение (2 семестр)', 'Теориялық оқыту (2-семестр)', "{$n}-01-12", "{$n}-05-16");
    $event('control_week', 'Рубежный контроль 1', '1-аралық бақылау', "{$n}-03-02", "{$n}-03-07");
    $event('practice', 'Учебная практика', 'Оқу практикасы', "{$n}-05-18", "{$n}-06-06", 2);
    $event('exam_session', 'Летняя экзаменационная сессия', 'Жазғы емтихан сессиясы', "{$n}-06-08", "{$n}-06-20");
    $event('final_attestation', 'Итоговая аттестация', 'Қорытынды аттестаттау', "{$n}-06-15", "{$n}-06-27", 4);

    $holidays = 0;
    foreach ((array) Config::get('holidays', []) as $monthDay => [$ru, $kk, $days]) {
        foreach ([$y, $n] as $calendarYear) {
            $start = "{$calendarYear}-{$monthDay}";
            if ($start < $year['start_date'] || $start > $year['end_date']) {
                continue;
            }
            $end = (new DateTimeImmutable($start))->modify('+' . ((int) $days - 1) . ' days')->format('Y-m-d');
            $event('public_holiday', $ru, $kk, $start, $end);
            $holidays++;
        }
    }
    $out("Demo: calendar events created (incl. {$holidays} public holidays).");

    // Curriculum for the "Software" intake of the active year, linked to ПО-26-1.
    $qualification = Database::fetchColumn('SELECT id FROM qualifications WHERE specialty_id = ? LIMIT 1', [$software]);
    Database::execute(
        "INSERT INTO curricula (specialty_id, qualification_id, title, admission_year, study_form, base_education, semesters_count, status, approved_at)
         VALUES (?, ?, ?, ?, 'full_time', 'basic_secondary', 8, 'approved', NOW())",
        [$software, $qualification, "Программное обеспечение, набор {$y} (демо)", $y]
    );
    $planId = Database::lastInsertId();
    $rows = [
        // subject code, module, semester, total, theory, practice, lab, control
        ['ООД 01', 'ООД', 1, 72, 24, 48, 0, 'credit'], ['ООД 02', 'ООД', 1, 72, 36, 36, 0, 'exam'],
        ['ООД 05', 'ООД', 1, 96, 48, 48, 0, 'exam'], ['ООД 07', 'ООД', 1, 48, 16, 0, 32, 'diff_credit'],
        ['БМ 01', 'БМ 01', 2, 120, 40, 0, 80, 'exam'], ['ООД 05', 'ООД', 2, 72, 36, 36, 0, 'exam'],
        ['БМ 03', 'БМ 03', 2, 96, 32, 0, 64, 'diff_credit'], ['ООД 01', 'ООД', 2, 48, 16, 32, 0, 'credit'],
    ];
    foreach ($rows as $i => [$code, $module, $semester, $total, $theory, $practice, $lab, $control]) {
        Database::execute(
            'INSERT INTO curriculum_subjects (curriculum_id, subject_id, module_code, semester, hours_total, hours_theory, hours_practice, hours_lab, control_form, sort_order)
             SELECT ?, id, ?, ?, ?, ?, ?, ?, ?, ? FROM subjects WHERE code = ?',
            [$planId, $module, $semester, $total, $theory, $practice, $lab, $control, $i, $code]
        );
    }
    Database::execute("UPDATE `groups` SET curriculum_id = ? WHERE code = 'ПО-26-1'", [$planId]);
    $out('Demo: approved curriculum with ' . count($rows) . ' rows linked to group ПО-26-1.');

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_process_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
