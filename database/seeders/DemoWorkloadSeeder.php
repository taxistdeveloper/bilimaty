<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Repositories\WorkloadRepository;
use App\Services\WorkloadService;

/**
 * Stage 8 demo: workload plans of the current year for teachers who keep journals.
 * Planned hours come from the curriculum (auto-fill) plus a few manual lines; the annual
 * limit is a DEMO value, not a college norm. Uses WorkloadService, so all checks apply. Runs once.
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_workload_seeded'") === '1') {
        $out('Demo (workload): already seeded, skipped.');

        return;
    }
    $yearId = Database::fetchColumn("SELECT id FROM academic_years WHERE status = 'active' LIMIT 1");
    if ($yearId === null) {
        $out('Demo (workload): no active academic year, skipped.');

        return;
    }
    $yearId = (int) $yearId;
    $repository = new WorkloadRepository();
    $service = new WorkloadService($repository);
    $type = static fn (string $code): int => (int) Database::fetchColumn('SELECT id FROM workload_types WHERE code = ?', [$code]);
    $semesters = Database::fetchAll('SELECT id, number, start_date FROM semesters WHERE academic_year_id = ? ORDER BY number', [$yearId]);

    $teachers = Database::fetchAll(
        'SELECT DISTINCT a.teacher_id FROM teacher_subject_assignments a JOIN semesters s ON s.id = a.semester_id WHERE s.academic_year_id = ? ORDER BY a.teacher_id',
        [$yearId]
    );
    $plans = 0;
    foreach ($teachers as $i => $row) {
        $teacherId = (int) $row['teacher_id'];
        $planId = $service->savePlan($yearId, $teacherId, ['rate' => '1.00', 'max_hours' => 720, 'note' => 'ДЕМО: лимит не является нормой колледжа'], null);
        $plan = $repository->plan($planId);
        $service->fill($plan);
        // journals without a curriculum: plan the theory hours by hand, consultations and exams too
        foreach ($repository->teacherJournals($teacherId, $yearId) as $j) {
            $line = ['semester_id' => (int) $j['semester_id'], 'group_id' => (int) $j['group_id'], 'subject_id' => (int) $j['subject_id'], 'subgroup' => null, 'note' => null];
            foreach ([['theory', 48], ['consultation', 4], ['exam', 6]] as [$code, $hours]) {
                try {
                    $service->saveAssignment($plan, $line + ['workload_type_id' => $type($code), 'hours' => $hours]);
                } catch (App\Core\ValidationException) {
                    // already planned from the curriculum or the limit is reached — keep what fits
                }
            }
        }
        // manually recorded exam hours in the first semester (kinds outside the journal)
        $examLine = Database::fetch(
            'SELECT wa.id FROM workload_assignments wa WHERE wa.plan_id = ? AND wa.workload_type_id = ? AND wa.semester_id = ? LIMIT 1',
            [$planId, $type('exam'), (int) $semesters[0]['id']]
        );
        if ($examLine !== null) {
            $assignment = $repository->assignment((int) $examLine['id']);
            $date = min(date('Y-m-d'), date('Y-m-d', strtotime($semesters[0]['start_date'] . ' +40 days')));
            $service->addActual($assignment, ['work_date' => $date, 'hours' => '2', 'note' => 'Приём рейтинга (демо)']);
        }
        if ($i % 2 === 0) {
            $service->approve($repository->plan($planId));
        }
        $plans++;
    }

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_workload_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
    $out("Demo (workload): {$plans} plans.");
};
