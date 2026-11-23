<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $year */
/** @var array<int, string> $yearOptions */
/** @var array<int, string> $groups */
/** @var array<string, mixed>|null $placement */
/** @var array<string, mixed> $filters */
/** @var bool $canManage */
/** @var list<string> $types */
$weekdays = array_map(static fn (int $d): string => __('calendar.weekdays.' . $d), range(1, 7));
$query = static function (array $extra) use ($filters): string {
    $q = array_filter($extra + $filters, static fn ($v) => $v !== null && $v !== '');

    return url('/calendar') . ($q !== [] ? '?' . http_build_query($q) : '');
};
$title = static fn (array $e): string => localized($e, 'title');
$scopeLabel = static function (array $e): string {
    if ($e['group_code'] !== null) {
        return __('groups.one') . ' ' . $e['group_code'];
    }

    return $e['course'] !== null ? __('groups.course_n', ['n' => $e['course']]) : __('calendar.all_college');
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('nav.calendar'),
    'subtitle' => $placement !== null ? __('calendar.for_group', ['group' => $placement['code']]) : __('calendar.subtitle'),
    'actions' => $year === null ? '' :
        '<a href="' . e(url('/calendar/print?' . http_build_query(array_filter($filters, static fn ($v) => $v !== null && $v !== '')))) . '" class="btn btn-light" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . e(__('calendar.pdf')) . '</a>'
        . ($canManage
            ? '<form method="post" action="' . e(url('/calendar/holidays/' . $year['id'])) . '" data-confirm="' . e(__('calendar.holidays_confirm')) . '">' . csrf_field()
              . '<button type="submit" class="btn btn-light"><i class="bi bi-flag me-1" aria-hidden="true"></i>' . e(__('calendar.add_holidays')) . '</button></form>'
              . '<a href="' . e(url('/calendar/create?year=' . $year['id'] . (isset($month) ? '&date=' . $month . '-01' : ''))) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('calendar.create')) . '</a>'
            : ''),
]) ?>

<?php if ($year === null): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><p class="mb-0"><?= e(__('dashboard.no_active_year')) ?></p></div></div>
<?php else: ?>
<div class="card mb-3">
    <form method="get" action="<?= e(url('/calendar')) ?>" class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="year"><?= e(__('fields.academic_year_id')) ?></label>
            <select class="form-select" id="year" name="year">
                <?php foreach ($yearOptions as $id => $label): ?>
                    <option value="<?= e($id) ?>"<?= (int) $year['id'] === $id ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($placement === null): ?>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted mb-1" for="group"><?= e(__('fields.group_id')) ?></label>
                <select class="form-select" id="group" name="group">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <?php foreach ($groups as $id => $code): ?>
                        <option value="<?= e($id) ?>"<?= $filters['group'] === $id ? ' selected' : '' ?>><?= e($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 col-xl-1">
                <label class="form-label small text-muted mb-1" for="course"><?= e(__('fields.course')) ?></label>
                <select class="form-select" id="course" name="course">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <?php for ($c = 1; $c <= 4; $c++): ?><option value="<?= e($c) ?>"<?= $filters['course'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endfor; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-2 col-xl-2">
            <label class="form-label small text-muted mb-1" for="type"><?= e(__('fields.type')) ?></label>
            <select class="form-select" id="type" name="type">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($types as $type): ?><option value="<?= e($type) ?>"<?= $filters['type'] === $type ? ' selected' : '' ?>><?= e(__('calendar.types.' . $type)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
        </div>
    </form>
</div>

<?php if ($year['status'] === 'archived'): ?>
    <div class="alert alert-light border small"><i class="bi bi-archive me-1" aria-hidden="true"></i><?= e(__('years.archived_readonly')) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-0" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $tab === 'month' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabMonth" type="button" role="tab" aria-controls="tabMonth" aria-selected="<?= $tab === 'month' ? 'true' : 'false' ?>">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= e(__('calendar.tab_month')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $tab === 'list' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabList" type="button" role="tab" aria-controls="tabList" aria-selected="<?= $tab === 'list' ? 'true' : 'false' ?>">
            <i class="bi bi-list-ul me-1" aria-hidden="true"></i><?= e(__('calendar.tab_list')) ?> <span class="badge text-bg-light"><?= e(count($events)) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content card border-top-0 rounded-top-0">
    <div class="tab-pane fade<?= $tab === 'month' ? ' show active' : '' ?>" id="tabMonth" role="tabpanel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-bottom">
            <div class="btn-group" role="group" aria-label="<?= e(__('calendar.navigation')) ?>">
                <a class="btn btn-light<?= $prevMonth === null ? ' disabled' : '' ?>" href="<?= e($prevMonth !== null ? $query(['month' => $prevMonth]) : '#') ?>" aria-label="<?= e(__('common.prev')) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                <a class="btn btn-light<?= $nextMonth === null ? ' disabled' : '' ?>" href="<?= e($nextMonth !== null ? $query(['month' => $nextMonth]) : '#') ?>" aria-label="<?= e(__('common.next')) ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            </div>
            <h2 class="h5 fw-bold mb-0"><?= e(App\Services\CalendarGrid::monthName($month)) ?></h2>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e(__('calendar.choose_month')) ?></button>
                <ul class="dropdown-menu dropdown-menu-end app-month-menu">
                    <?php foreach ($months as $m): ?>
                        <li><a class="dropdown-item<?= $m === $month ? ' active' : '' ?>" href="<?= e($query(['month' => $m])) ?>"><?= e(App\Services\CalendarGrid::monthName($m)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0 app-cal">
                <thead>
                <tr><?php foreach ($weekdays as $i => $wd): ?><th scope="col" class="text-center small<?= $i >= 5 ? ' text-danger' : '' ?>"><?= e($wd) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($weeks as $week): ?>
                    <tr>
                        <?php foreach ($week as $day): ?>
                            <td class="app-cal__day<?= $day['in_month'] ? '' : ' is-other' ?><?= $day['today'] ? ' is-today' : '' ?><?= $day['weekend'] ? ' is-weekend' : '' ?><?= $day['semester'] === null && $day['in_month'] ? ' is-break' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="app-cal__num"><?= e($day['day']) ?></span>
                                    <?php if ($canManage && $day['in_month'] && $day['date'] >= $year['start_date'] && $day['date'] <= $year['end_date']): ?>
                                        <a href="<?= e(url('/calendar/create?year=' . $year['id'] . '&date=' . $day['date'])) ?>" class="app-cal__add" aria-label="<?= e(__('calendar.create') . ' ' . format_date($day['date'])) ?>"><i class="bi bi-plus" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php foreach ($day['events'] as $event): ?>
                                    <?php $label = $title($event); ?>
                                    <?php if ($canManage): ?>
                                        <a href="<?= e(url('/calendar/' . $event['id'] . '/edit')) ?>" class="app-ev app-ev--<?= e($event['type']) ?>" title="<?= e($label . ' · ' . $scopeLabel($event)) ?>"><?= e($label) ?></a>
                                    <?php else: ?>
                                        <span class="app-ev app-ev--<?= e($event['type']) ?>" title="<?= e($label . ' · ' . $scopeLabel($event)) ?>"><?= e($label) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap gap-3 p-3 border-top small">
            <?php foreach ($types as $type): ?>
                <span class="d-inline-flex align-items-center gap-1"><span class="app-ev-dot app-ev--<?= e($type) ?>"></span><?= e(__('calendar.types.' . $type)) ?></span>
            <?php endforeach; ?>
            <span class="d-inline-flex align-items-center gap-1"><span class="app-ev-dot app-cal-break"></span><?= e(__('calendar.no_semester')) ?></span>
        </div>
    </div>

    <div class="tab-pane fade<?= $tab === 'list' ? ' show active' : '' ?>" id="tabList" role="tabpanel">
        <?php if ($events === []): ?>
            <div class="app-empty"><i class="bi bi-calendar-event" aria-hidden="true"></i><p class="mb-0"><?= e(__('calendar.empty')) ?></p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 app-table">
                    <thead class="table-light">
                    <tr>
                        <th scope="col"><?= e(__('fields.date')) ?></th>
                        <th scope="col"><?= e(__('calendar.event')) ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= e(__('calendar.audience')) ?></th>
                        <?php if ($canManage): ?><th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td class="small text-nowrap">
                                <?= e(format_date($event['start_date'])) ?><?= $event['end_date'] !== $event['start_date'] ? ' — ' . e(format_date($event['end_date'])) : '' ?>
                            </td>
                            <td>
                                <span class="app-ev-dot app-ev--<?= e($event['type']) ?> me-1"></span>
                                <span class="fw-semibold"><?= e($title($event)) ?></span>
                                <div class="small text-muted"><?= e(__('calendar.types.' . $event['type'])) ?><?= $event['note'] ? ' · ' . e($event['note']) : '' ?></div>
                            </td>
                            <td class="small d-none d-md-table-cell"><?= e($scopeLabel($event)) ?></td>
                            <?php if ($canManage): ?>
                                <td class="text-end"><a href="<?= e(url('/calendar/' . $event['id'] . '/edit')) ?>" class="btn btn-sm btn-light" aria-label="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
