<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;
use App\Services\JournalService;

/**
 * Stage 6 demo: a DEMO grading scheme (explicitly not a college rule — the administration
 * must create its own), journals generated from the demo timetable, lesson dates up to
 * today, random grades/absences and Rating 1. Uses JournalService, so every value passes
 * the same validation as the UI. Runs once.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_journal_seeded'") === '1') {
        $out('Demo (journal): already seeded, skipped.');

        return;
    }
    $semester = (new ScheduleRepository())->semesterOn(date('Y-m-d'));
    if ($semester === null || (int) Database::fetchColumn('SELECT COUNT(*) FROM schedule_entries WHERE semester_id = ?', [$semester['id']]) === 0) {
        $out('Demo (journal): needs the demo timetable of the current semester, skipped.');

        return;
    }

    $type = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM assessment_types WHERE code = ?', [$code]);
    Database::execute(
        "INSERT INTO grading_schemes (title, version, grade_min, grade_max, final_decimals, pass_threshold, status, approved_at, note)
         VALUES ('ДЕМО-схема (не является правилом колледжа)', 1, 0, 100, 0, 50, 'active', NOW(), 'Только для демонстрации. Создайте схему по утверждённому положению колледжа.')"
    );
    $schemeId = Database::lastInsertId();
    $order = 0;
    foreach (['current' => 40, 'rating1' => 15, 'rating2' => 15, 'exam' => 30] as $code => $weight) {
        Database::execute(
            'INSERT INTO grading_scheme_components (grading_scheme_id, assessment_type_id, weight, sort_order) VALUES (?, ?, ?, ?)',
            [$schemeId, $type($code), $weight, $order++]
        );
    }

    $journals = new JournalRepository();
    $service = new JournalService($journals);
    $created = $service->generateFromTimetable((int) $semester['id'], $schemeId, false);

    mt_srand(6);
    $lessons = 0;
    $marks = 0;
    foreach (Database::fetchAll('SELECT id FROM teacher_subject_assignments WHERE semester_id = ?', [$semester['id']]) as $row) {
        $journal = $journals->find((int) $row['id']);
        $lessons += $service->fillFromSchedule($journal, date('Y-m-d'));
        $journal = $journals->find((int) $row['id']);
        $students = $journals->students($journal);
        $rating1 = $type('rating1');
        foreach ($journals->lessons((int) $journal['id']) as $lesson) {
            foreach ($students as $student) {
                $roll = mt_rand(1, 100);
                $value = match (true) {
                    $roll <= 5 => 'н',
                    $roll <= 7 => 'у',
                    $roll <= 55 => (string) mt_rand(55, 100),
                    default => null,
                };
                if ($value !== null && $journals->studentMemberOn($journal, (int) $student['id'], (string) $lesson['lesson_date'])) {
                    $service->setLessonCell($journal, $lesson, (int) $student['id'], $value);
                    $marks++;
                }
            }
        }
        if ((int) $journal['lessons_count'] > 0 || $journals->lessons((int) $journal['id']) !== []) {
            foreach ($students as $student) {
                $service->setAssessment($journal, $rating1, (int) $student['id'], (string) mt_rand(50, 100));
            }
        }
    }
    $out("Demo: DEMO grading scheme, {$created} journals, {$lessons} lesson dates, {$marks} marks/absences, Rating 1 filled.");

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_journal_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
