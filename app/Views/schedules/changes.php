<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array<string, array<int, string>> $options */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var bool $canManage */
$name = static fn (?string $last, ?string $first, ?string $middle): string => $last === null ? '—' : full_name(['last_name' => $last, 'first_name' => $first, 'middle_name' => $middle], true);
$room = static fn (?string $number, ?string $building): string => $number === null ? '—' : trim(($building ? $building . '-' : '') . $number);
$typeClass = ['modify' => 'app-badge-warning', 'cancel' => 'app-badge-danger', 'reschedule' => 'app-badge-primary'];
?>
<?= $this->partial('partials/page-header', ['heading' => __('schedule.changes_title'), 'subtitle' => __('schedule.changes_subtitle'), 'back' => '/schedule']) ?>

<div class="card">
    <form method="get" action="<?= e(url('/schedule/changes')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="from"><?= e(__('audit.date_from')) ?></label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="to"><?= e(__('audit.date_to')) ?></label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="group"><?= e(__('fields.group_id')) ?></label>
            <select class="form-select" id="group" name="group">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($options['groups'] as $id => $label): ?><option value="<?= e($id) ?>"<?= $filters['group'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="teacher"><?= e(__('teachers.one')) ?></label>
            <select class="form-select" id="teacher" name="teacher">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($options['teachers'] as $id => $label): ?><option value="<?= e($id) ?>"<?= $filters['teacher'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/schedule/changes')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>
    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><p class="mb-0"><?= e(__('schedule.no_changes')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('fields.date')) ?></th>
                    <th scope="col"><?= e(__('schedule.lesson')) ?></th>
                    <th scope="col"><?= e(__('schedule.change')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('schedule.reason')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('audit.user')) ?></th>
                    <?php if ($canManage): ?><th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(format_date($row['lesson_date'])) ?><div class="text-muted"><?= e(__('schedule.period_n', ['n' => $row['period_number']])) ?></div></td>
                        <td class="small">
                            <div class="fw-semibold"><?= e($row['group_code']) ?><?= $row['subgroup'] !== null ? '/' . e($row['subgroup']) : '' ?> · <?= e(localized(['name_ru' => $row['subject_name_ru'], 'name_kk' => $row['subject_name_kk']])) ?></div>
                            <div class="text-muted"><?= e($name($row['teacher_last_name'], $row['teacher_first_name'], $row['teacher_middle_name'])) ?> · <?= e(__('schedule.room')) ?> <?= e($room($row['classroom_number'], $row['classroom_building'])) ?></div>
                        </td>
                        <td class="small">
                            <span class="badge <?= e($typeClass[$row['change_type']] ?? '') ?>"><?= e(__('schedule.change_types.' . $row['change_type'])) ?></span>
                            <?php if ($row['new_teacher_id'] !== null): ?><div><?= e(__('schedule.new_teacher')) ?>: <?= e($name($row['new_teacher_last_name'], $row['new_teacher_first_name'], $row['new_teacher_middle_name'])) ?></div><?php endif; ?>
                            <?php if ($row['new_classroom_id'] !== null): ?><div><?= e(__('schedule.new_classroom')) ?>: <?= e($room($row['new_classroom_number'], $row['new_classroom_building'])) ?></div><?php endif; ?>
                            <?php if ($row['change_type'] === 'reschedule'): ?><div><?= e(__('schedule.moved_to', ['date' => format_date((string) $row['new_date']), 'n' => $row['new_period_number']])) ?></div><?php endif; ?>
                        </td>
                        <td class="small d-none d-lg-table-cell"><?= e($row['reason']) ?></td>
                        <td class="small d-none d-md-table-cell"><?= e($name($row['author_last_name'], $row['author_first_name'], $row['author_middle_name'])) ?><div class="text-muted"><?= e(format_date($row['updated_at'], 'd.m.Y H:i')) ?></div></td>
                        <?php if ($canManage): ?>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('/schedule/changes/' . $row['id'])) ?>" data-confirm="<?= e(__('schedule.revert_confirm')) ?>">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button type="submit" class="btn btn-sm btn-light" aria-label="<?= e(__('schedule.revert')) ?>" title="<?= e(__('schedule.revert')) ?>"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/schedule/changes']) ?>
</div>
