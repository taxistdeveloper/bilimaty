<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var list<array<string, mixed>> $exams */
/** @var array<string, array<int, string>> $options */
/** @var bool $canManage */
$statusClass = ['planned' => 'app-badge-primary', 'open' => 'app-badge-warning', 'closed' => 'app-badge-success'];
$byDate = [];
foreach ($exams as $exam) {
    $byDate[$exam['exam_date']][] = $exam;
}
$select = static function (string $name, string $label, array $items, mixed $current, bool $all = true): string {
    $html = '<label class="form-label small text-muted mb-1" for="ef_' . e($name) . '">' . e($label) . '</label><select class="form-select" id="ef_' . e($name) . '" name="' . e($name) . '">';
    if ($all) {
        $html .= '<option value="">' . e(__('common.all')) . '</option>';
    }
    foreach ($items as $v => $t) {
        $html .= '<option value="' . e($v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($t) . '</option>';
    }

    return $html . '</select>';
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('exams.title'),
    'subtitle' => __('exams.subtitle'),
    'actions' => $canManage ? '<a href="' . e(url('/exams/create?semester=' . ($filters['semester'] ?? ''))) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('exams.create')) . '</a>' : '',
]) ?>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/exams')) ?>" class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4"><?= $select('semester', __('fields.semester'), $options['semesters'], $filters['semester'], false) ?></div>
        <div class="col-6 col-md-2"><?= $select('group', __('fields.group_id'), $options['groups'], $filters['group']) ?></div>
        <div class="col-6 col-md-2"><?= $select('type', __('exams.type'), $options['types'], $filters['type']) ?></div>
        <div class="col-6 col-md-2"><?= $select('status', __('common.status'), ['planned' => __('exams.statuses.planned'), 'open' => __('exams.statuses.open'), 'closed' => __('exams.statuses.closed')], $filters['status']) ?></div>
        <div class="col-6 col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
</div>

<?php if ($exams === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-clipboard-check" aria-hidden="true"></i><p class="mb-0"><?= e(__('exams.empty')) ?></p></div></div>
<?php endif; ?>
<?php foreach ($byDate as $date => $list): ?>
    <h2 class="h6 fw-bold mt-3 mb-2"><?= e(__('calendar.weekdays.' . (new DateTimeImmutable($date))->format('N'))) ?>, <?= e(format_date($date)) ?></h2>
    <div class="card mb-2">
        <div class="list-group list-group-flush">
            <?php foreach ($list as $exam): ?>
                <a href="<?= e(url('/exams/' . $exam['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap gap-3 align-items-center">
                    <div class="text-center app-sched__daytime">
                        <div class="fw-bold"><?= e($exam['start_time'] !== null ? substr((string) $exam['start_time'], 0, 5) : '—') ?></div>
                        <div class="small text-muted"><?= e($exam['classroom_number'] !== null ? __('schedule.room') . ' ' . trim(($exam['classroom_building'] ? $exam['classroom_building'] . '-' : '') . $exam['classroom_number']) : '') ?></div>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold"><?= e(localized(['name_ru' => $exam['subject_name_ru'], 'name_kk' => $exam['subject_name_kk']])) ?> <span class="badge app-badge-primary"><?= e(localized(['name_ru' => $exam['type_name_ru'], 'name_kk' => $exam['type_name_kk']])) ?></span></div>
                        <div class="small text-muted">
                            <?= e($exam['group_code']) ?> · <?= e(__('exams.forms.' . $exam['exam_form'])) ?> ·
                            <?= e(full_name(['last_name' => $exam['examiner_last_name'], 'first_name' => $exam['examiner_first_name'], 'middle_name' => $exam['examiner_middle_name']], true)) ?>
                            <?php if ($exam['period_start'] !== null): ?> · <?= e(__('exams.period')) ?> <?= e(format_date($exam['period_start'], 'd.m')) ?>–<?= e(format_date($exam['exam_date'], 'd.m')) ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge <?= e($statusClass[$exam['status']] ?? '') ?>"><?= e(__('exams.statuses.' . $exam['status'])) ?></span>
                        <div class="small text-muted"><?= e($exam['statement_number']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
