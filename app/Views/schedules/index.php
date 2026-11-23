<?php
/** @var App\Core\View $this */
/** @var array<int, string> $yearOptions */
/** @var array<string, mixed>|null $semester */
/** @var list<array<string, mixed>> $semesters */
/** @var string $mode */
/** @var int|null $entityId */
/** @var array<string, array<int, string>> $options */
/** @var bool $canManage */
/** @var string $view */
/** @var list<array<string, mixed>> $periods */
/** @var list<int> $weekdays */
/** @var array<int, array<string, mixed>> $absentTeachers teacher id => leave (HR) */
/** @var array<int, string> $days */
/** @var array<string, mixed>|null $week */
/** @var list<array<string, mixed>> $occurrences */
$modeOptions = ['group' => $options['groups'], 'teacher' => $options['teachers'], 'classroom' => $options['classrooms']];
$base = static function (array $extra) use ($semester, $mode, $entityId, $view): string {
    $q = array_filter($extra + ['semester' => $semester['id'] ?? null, 'mode' => $mode, 'id' => $entityId, 'view' => $view], static fn ($v) => $v !== null && $v !== '');

    return url('/schedule') . '?' . http_build_query($q);
};
$grid = [];
foreach ($occurrences as $o) {
    $grid[$o['date']][(int) $o['period_number']][] = $o;
}
$statusClass = ['regular' => '', 'modified' => ' is-modified', 'cancelled' => ' is-cancelled', 'moved_out' => ' is-cancelled', 'moved_in' => ' is-moved', 'suspended' => ' is-suspended'];
$entityLabel = $entityId !== null ? ($modeOptions[$mode][$entityId] ?? '') : '';
$canAdd = $canManage && $entityId !== null && $mode !== 'classroom';

/** Data attributes for the occurrence modal (escaped JSON). */
$payload = static function (array $o): string {
    $change = $o['change'];

    return json_encode([
        'entry_id' => $o['entry_id'], 'date' => $o['date'], 'original_date' => $o['original_date'] ?? $o['date'], 'status' => $o['status'],
        'subject' => localized(['name_ru' => $o['subject_name_ru'], 'name_kk' => $o['subject_name_kk']]),
        'group' => $o['group_code'] . ($o['subgroup'] !== null ? ' / ' . __('schedule.subgroup_n', ['n' => $o['subgroup']]) : ''),
        'teacher' => $o['effective_teacher_name'], 'classroom' => $o['effective_classroom_label'],
        'period' => (int) $o['period_number'], 'time' => substr((string) $o['start_time'], 0, 5) . '–' . substr((string) $o['end_time'], 0, 5),
        'type' => __('schedule.types.' . $o['lesson_type']),
        'status_label' => __('schedule.statuses.' . $o['status']),
        'reason' => $change['reason'] ?? ($o['suspension'] ?? null ? localized($o['suspension'], 'title') : null),
        'change_id' => $change['id'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('nav.schedule'),
    'subtitle' => $entityLabel !== '' ? __('schedule.modes.' . $mode) . ': ' . $entityLabel : __('schedule.choose_entity'),
    'actions' => '<a href="' . e(url('/schedule/changes')) . '" class="btn btn-light"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>' . e(__('schedule.changes_title')) . '</a>'
        . ($canManage ? '<button type="button" class="btn btn-primary" data-entry-new><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('schedule.add')) . '</button>' : ''),
]) ?>

<?php if (($absentTeachers ?? []) !== []): ?>
    <div class="alert alert-warning small" role="status">
        <div class="fw-semibold"><i class="bi bi-person-exclamation me-1" aria-hidden="true"></i><?= e(__('hr.absent_teachers')) ?> <span class="fw-normal text-muted">— <?= e(__('hr.absent_teachers_hint')) ?></span></div>
        <ul class="mb-0 ps-3">
            <?php foreach ($absentTeachers as $teacherId => $l): ?>
                <li><a href="<?= e(url('/schedule?mode=teacher&id=' . $teacherId . '&date=' . ($date ?? ''))) ?>" class="alert-link"><?= e(full_name($l, true)) ?></a>:
                    <?= e(__('hr.leave_types.' . $l['type'])) ?>, <?= e(format_date($l['start_date'])) ?>–<?= e(format_date($l['end_date'])) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (errors() !== []): ?>
    <div class="alert alert-danger" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e(__('schedule.change_failed')) ?></div>
        <ul class="mb-0 small"><?php foreach (errors() as $messages): foreach ($messages as $m): ?><li><?= e($m) ?></li><?php endforeach; endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/schedule')) ?>" class="card-body row g-2 align-items-end" id="scheduleFilters">
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="f_year"><?= e(__('fields.academic_year_id')) ?></label>
            <select class="form-select" id="f_year" name="year" data-autosubmit>
                <?php foreach ($yearOptions as $id => $label): ?><option value="<?= e($id) ?>"<?= $semester !== null && (int) $semester['academic_year_id'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="f_semester"><?= e(__('fields.semester')) ?></label>
            <select class="form-select" id="f_semester" name="semester">
                <?php foreach ($semesters as $s): ?><option value="<?= e($s['id']) ?>"<?= $semester !== null && (int) $semester['id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e(__('dashboard.semester', ['number' => $s['number']])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <span class="form-label small text-muted mb-1 d-block"><?= e(__('schedule.view_by')) ?></span>
            <div class="btn-group w-100" role="group">
                <?php foreach (['group', 'teacher', 'classroom'] as $m): ?>
                    <input type="radio" class="btn-check" name="mode" id="mode_<?= e($m) ?>" value="<?= e($m) ?>"<?= $mode === $m ? ' checked' : '' ?> data-mode-switch>
                    <label class="btn btn-outline-primary" for="mode_<?= e($m) ?>"><?= e(__('schedule.modes.' . $m)) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label small text-muted mb-1" for="f_entity_<?= e($mode) ?>"><?= e(__('schedule.entity')) ?></label>
            <?php foreach ($modeOptions as $m => $list): ?>
                <select class="form-select<?= $m === $mode ? '' : ' d-none' ?>" id="f_entity_<?= e($m) ?>" name="id" data-mode-select="<?= e($m) ?>"<?= $m === $mode ? '' : ' disabled' ?>>
                    <option value=""><?= e(__('common.choose')) ?></option>
                    <?php foreach ($list as $id => $label): ?><option value="<?= e($id) ?>"<?= $m === $mode && $entityId === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            <?php endforeach; ?>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="f_date"><?= e(__('fields.date')) ?></label>
            <input type="date" class="form-control" id="f_date" name="date" value="<?= e($date ?? '') ?>"
                   min="<?= e($semester['start_date'] ?? '') ?>" max="<?= e($semester['end_date'] ?? '') ?>">
        </div>
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="col-6 col-md-3 col-xl-12 col-xxl d-flex">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
        </div>
    </form>
</div>

<?php if ($semester === null): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><p class="mb-0"><?= e(__('schedule.no_semester')) ?></p></div></div>
<?php elseif ($periods === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-bell" aria-hidden="true"></i><p class="mb-0"><?= e(__('schedule.no_periods')) ?></p></div></div>
<?php else: ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item"><a class="nav-link<?= $view === 'week' ? ' active' : '' ?>" href="<?= e($base(['view' => 'week', 'date' => $date])) ?>"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i><?= e(__('schedule.week')) ?></a></li>
        <li class="nav-item"><a class="nav-link<?= $view === 'day' ? ' active' : '' ?>" href="<?= e($base(['view' => 'day', 'date' => $date])) ?>"><i class="bi bi-calendar-day me-1" aria-hidden="true"></i><?= e(__('schedule.day')) ?></a></li>
    </ul>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-light btn-sm" href="<?= e($base(['date' => $view === 'day' ? (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d') : $week['prev']])) ?>" aria-label="<?= e(__('common.prev')) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        <span class="small fw-semibold text-nowrap">
            <?= e($view === 'day' ? __('calendar.weekdays.' . (new DateTimeImmutable($date))->format('N')) . ', ' . format_date($date) : format_date(reset($days)) . ' — ' . format_date(end($days))) ?>
            <span class="badge app-badge-primary ms-1"><?= e(__('schedule.week_n', ['n' => $week['number']])) ?> · <?= e(__('schedule.parities.' . $week['parity'])) ?></span>
        </span>
        <a class="btn btn-light btn-sm" href="<?= e($base(['date' => $view === 'day' ? (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') : $week['next']])) ?>" aria-label="<?= e(__('common.next')) ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        <a class="btn btn-light btn-sm" href="<?= e($base(['date' => date('Y-m-d')])) ?>"><?= e(__('common.today')) ?></a>
    </div>
</div>

<?php if ($entityId === null): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-hand-index" aria-hidden="true"></i><p class="mb-0"><?= e(__('schedule.choose_entity')) ?></p></div></div>
<?php elseif ($view === 'week'): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 app-sched">
                <thead>
                <tr>
                    <th scope="col" class="app-sched__period"><?= e(__('schedule.period')) ?></th>
                    <?php foreach ($days as $wd => $day): ?>
                        <th scope="col" class="text-center<?= $day === date('Y-m-d') ? ' is-today' : '' ?>">
                            <div><?= e(__('calendar.weekdays.' . $wd)) ?></div>
                            <div class="small fw-normal text-muted"><?= e(format_date($day, 'd.m')) ?></div>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $period): ?>
                    <tr>
                        <th scope="row" class="app-sched__period">
                            <div class="fw-bold"><?= e($period['number']) ?></div>
                            <div class="small text-muted fw-normal"><?= e(substr((string) $period['start_time'], 0, 5)) ?><br><?= e(substr((string) $period['end_time'], 0, 5)) ?></div>
                        </th>
                        <?php foreach ($days as $wd => $day): ?>
                            <?php $cell = $grid[$day][(int) $period['number']] ?? []; ?>
                            <td class="app-sched__cell<?= $day === date('Y-m-d') ? ' is-today' : '' ?>">
                                <?php foreach ($cell as $o): ?>
                                    <?= $this->partial('schedules/_lesson', ['o' => $o, 'mode' => $mode, 'statusClass' => $statusClass[$o['status']] ?? '', 'payload' => $payload($o), 'entityId' => $entityId]) ?>
                                <?php endforeach; ?>
                                <?php if ($canAdd && $day >= $semester['start_date'] && $day <= $semester['end_date']): ?>
                                    <button type="button" class="app-sched__add" data-entry-new data-weekday="<?= e($wd) ?>" data-period="<?= e($period['id']) ?>" aria-label="<?= e(__('schedule.add')) ?>"><i class="bi bi-plus" aria-hidden="true"></i></button>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <?php $dayLessons = array_values(array_filter($occurrences, static fn (array $o): bool => $o['date'] === $date)); ?>
        <?php if ($dayLessons === []): ?>
            <div class="app-empty"><i class="bi bi-cup-hot" aria-hidden="true"></i><p class="mb-0"><?= e(__('schedule.no_lessons_day')) ?></p></div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($dayLessons as $o): ?>
                    <div class="list-group-item d-flex gap-3 align-items-start">
                        <div class="text-center app-sched__daytime">
                            <div class="fw-bold"><?= e($o['period_number']) ?></div>
                            <div class="small text-muted"><?= e(substr((string) $o['start_time'], 0, 5)) ?>–<?= e(substr((string) $o['end_time'], 0, 5)) ?></div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <?= $this->partial('schedules/_lesson', ['o' => $o, 'mode' => $mode, 'statusClass' => $statusClass[$o['status']] ?? '', 'payload' => $payload($o), 'entityId' => $entityId]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-3 small text-muted mt-2">
    <span><span class="app-legend"></span><?= e(__('schedule.statuses.regular')) ?></span>
    <span><span class="app-legend is-modified"></span><?= e(__('schedule.statuses.modified')) ?></span>
    <span><span class="app-legend is-moved"></span><?= e(__('schedule.statuses.moved_in')) ?></span>
    <span><span class="app-legend is-cancelled"></span><?= e(__('schedule.statuses.cancelled')) ?></span>
    <span><span class="app-legend is-suspended"></span><?= e(__('schedule.statuses.suspended')) ?></span>
</div>
<?php endif; ?>

<?= $this->partial('schedules/_modals', [
    'semester' => $semester, 'mode' => $mode, 'entityId' => $entityId, 'options' => $options, 'periods' => $periods,
    'weekdays' => $weekdays, 'canManage' => $canManage,
]) ?>

<?php $this->start('scripts'); ?>
<script src="<?= e(asset('assets/js/schedule.js')) ?>"></script>
<?php $this->stop(); ?>
