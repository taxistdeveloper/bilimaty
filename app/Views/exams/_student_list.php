<?php
/** @var App\Core\View $this */
/**
 * Upcoming events and results of one student (student's "My exams" and the parent's view).
 *
 * @var list<array<string, mixed>> $exams
 */
use App\Services\JournalService;

$today = date('Y-m-d');
$upcoming = array_values(array_filter($exams, static fn (array $e): bool => $e['exam_date'] >= $today));
$past = array_reverse(array_values(array_filter($exams, static fn (array $e): bool => $e['exam_date'] < $today)));
$render = static function (array $list, bool $showResult) {
    ob_start(); ?>
    <div class="list-group list-group-flush">
        <?php foreach ($list as $e): ?>
            <div class="list-group-item d-flex flex-wrap gap-3 align-items-center">
                <div class="text-center app-sched__daytime">
                    <div class="fw-bold"><?= e(format_date($e['exam_date'], 'd.m')) ?></div>
                    <div class="small text-muted"><?= e($e['start_time'] !== null ? substr((string) $e['start_time'], 0, 5) : '') ?></div>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold"><?= e(localized(['name_ru' => $e['subject_name_ru'], 'name_kk' => $e['subject_name_kk']])) ?> <span class="badge app-badge-primary"><?= e(localized(['name_ru' => $e['type_name_ru'], 'name_kk' => $e['type_name_kk']])) ?></span></div>
                    <div class="small text-muted"><?= e(__('exams.forms.' . $e['exam_form'])) ?><?= $e['classroom_number'] !== null ? ' · ' . e(__('schedule.room')) . ' ' . e($e['classroom_number']) : '' ?> · <?= e(full_name(['last_name' => $e['examiner_last_name'], 'first_name' => $e['examiner_first_name'], 'middle_name' => $e['examiner_middle_name']], true)) ?></div>
                </div>
                <?php if ($showResult): ?>
                    <div class="fw-bold"><?php
                        if ($e['status'] !== 'closed') {
                            echo '<span class="small text-muted fw-normal">' . e(__('exams.statuses.' . $e['status'])) . '</span>';
                        } elseif ($e['my_admission'] === 'not_admitted') {
                            echo e(__('exams.admission_values.not_admitted'));
                        } elseif ($e['my_attendance'] !== null && $e['my_attendance'] !== 'present') {
                            echo e(__('exams.attendance_values.' . $e['my_attendance']));
                        } else {
                            echo e(JournalService::normalize($e['my_value']) ?? '—');
                        }
                    ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="card mb-3">
    <div class="card-header fw-semibold"><?= e(__('exams.upcoming')) ?></div>
    <?= $upcoming === [] ? '<div class="card-body small text-muted">' . e(__('exams.no_upcoming')) . '</div>' : $render($upcoming, false) ?>
</div>
<div class="card">
    <div class="card-header fw-semibold"><?= e(__('exams.results')) ?></div>
    <?= $past === [] ? '<div class="card-body small text-muted">' . e(__('exams.no_results')) . '</div>' : $render($past, true) ?>
</div>
