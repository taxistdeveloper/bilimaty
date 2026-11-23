<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $teacher */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed> $year */
/** @var list<array<string, mixed>> $lines */
/** @var array<string, mixed> $report detail report (one row per line, same order as $lines is not guaranteed) */
/** @var array<int, string> $years */
/** @var array<string, array<int, string>> $options */
/** @var bool $canManage */
/** @var int $planHours */
use App\Core\Session;
use App\Services\WorkloadService;

$h = static fn ($v): string => WorkloadService::hours($v);
$yearId = (int) $year['id'];
$draft = $plan !== null && $plan['status'] === 'draft';
$editable = $canManage && $draft && $year['status'] !== 'archived';
$fill = Session::getFlash('fill');
$totals = $report['totals'];
$query = 'year=' . $yearId . '&teacher=' . $teacher['id'];
$bySemester = [];
foreach ($report['rows'] as $row) {
    $bySemester[$row['label'][1]][] = $row;
}
ksort($bySemester);
$none = ['' => '—'];
$actions = '<a href="' . e(url('/workload/reports/print?' . $query . '&by=detail')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
    . '<a href="' . e(url('/workload/reports/export?' . $query . '&by=detail')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>CSV</a>';
?>
<?= $this->partial('partials/page-header', [
    'heading' => full_name($teacher),
    'subtitle' => __('workload.teacher_title') . ' · ' . ($teacher['department_name_ru'] !== null ? localized(['name_ru' => $teacher['department_name_ru'], 'name_kk' => $teacher['department_name_kk']]) : __('workload.no_department')),
    'back' => can('workload.manage') || App\Services\AccessScope::departmentIds() !== [] ? '/workload?year=' . $yearId : null,
    'actions' => $actions,
]) ?>

<form method="get" action="<?= e(url('/workload/teachers/' . $teacher['id'])) ?>" class="row g-2 align-items-end mb-3">
    <div class="col-8 col-md-4"><?= $this->partial('partials/filter-select', ['name' => 'year', 'label' => __('fields.academic_year_id'), 'items' => $years + [$yearId => str_replace('-', '–', (string) $year['title'])], 'current' => $yearId, 'all' => false]) ?></div>
    <div class="col-4 col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><?= e(__('common.show')) ?></button></div>
</form>

<?php if (is_array($fill)): ?>
    <div class="alert alert-info small">
        <?= e(__('workload.fill_result', ['created' => $fill['created'], 'hours' => $fill['hours']])) ?>
        <?php if ($fill['without_curriculum'] > 0): ?><br><?= e(__('workload.fill_no_curriculum', ['count' => $fill['without_curriculum']])) ?><?php endif; ?>
        <?php if ($fill['limit_reached']): ?><br><strong><?= e(__('workload.fill_limit')) ?></strong><?php endif; ?>
    </div>
<?php endif; ?>
<?php if (error('status')): ?><div class="alert alert-danger small"><?= e(error('status')) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('workload.plan_header')) ?></span>
                <?php if ($plan !== null): ?><span class="badge <?= $plan['status'] === 'approved' ? 'app-badge-success' : 'app-badge-warning' ?>"><?= e(__('workload.statuses.' . $plan['status'])) ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($canManage && ($plan === null || $draft)): ?>
                    <form method="post" action="<?= e(url('/workload/teachers/' . $teacher['id'] . '/plan')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="academic_year_id" value="<?= e($yearId) ?>">
                        <div class="row g-2">
                            <div class="col-6"><?= $this->partial('partials/field', ['name' => 'rate', 'label' => __('workload.rate'), 'value' => $plan['rate'] ?? '', 'attrs' => ['inputmode' => 'decimal', 'placeholder' => '1.00'], 'idPrefix' => 'plan_']) ?></div>
                            <div class="col-6"><?= $this->partial('partials/field', ['name' => 'max_hours', 'label' => __('workload.limit'), 'type' => 'number', 'value' => $plan['max_hours'] ?? '', 'attrs' => ['min' => 1, 'max' => 3000], 'idPrefix' => 'plan_']) ?></div>
                        </div>
                        <div class="form-text mt-n2 mb-2"><?= e(__('workload.limit_hint')) ?></div>
                        <?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'value' => $plan['note'] ?? '', 'attrs' => ['maxlength' => 500], 'idPrefix' => 'plan_', 'ownsInput' => old('workload_type_id') === null]) ?>
                        <button type="submit" class="btn btn-primary"><?= e($plan === null ? __('workload.create_plan') : __('common.save')) ?></button>
                    </form>
                <?php elseif ($plan === null): ?>
                    <p class="text-muted mb-0"><?= e(__('workload.no_plan_hint')) ?></p>
                <?php else: ?>
                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted fw-normal"><?= e(__('workload.rate')) ?></dt><dd class="col-6"><?= e($plan['rate'] !== null ? $h($plan['rate']) : '—') ?></dd>
                        <dt class="col-6 text-muted fw-normal"><?= e(__('workload.limit')) ?></dt><dd class="col-6"><?= e($plan['max_hours'] ?? '—') ?></dd>
                        <?php if ($plan['note']): ?><dt class="col-6 text-muted fw-normal"><?= e(__('fields.note')) ?></dt><dd class="col-6"><?= e($plan['note']) ?></dd><?php endif; ?>
                        <?php if ($plan['approved_at']): ?><dt class="col-6 text-muted fw-normal"><?= e(__('workload.approved_at')) ?></dt><dd class="col-6"><?= e(format_date((string) $plan['approved_at'], 'd.m.Y H:i')) ?></dd><?php endif; ?>
                    </dl>
                <?php endif; ?>

                <?php if ($canManage && $plan !== null): ?>
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                        <?php if ($draft): ?>
                            <form method="post" action="<?= e(url('/workload/plans/' . $plan['id'] . '/status')) ?>" data-confirm="<?= e(__('workload.fill_confirm')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="fill">
                                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-magic me-1" aria-hidden="true"></i><?= e(__('workload.fill')) ?></button>
                            </form>
                            <form method="post" action="<?= e(url('/workload/plans/' . $plan['id'] . '/status')) ?>" data-confirm="<?= e(__('workload.approve_confirm')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= e(__('workload.approve')) ?></button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/workload/plans/' . $plan['id'] . '/status')) ?>" class="d-flex flex-wrap gap-2 align-items-start w-100">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
                                <div class="flex-grow-1">
                                    <label class="visually-hidden" for="reopenReason"><?= e(__('schedule.reason')) ?></label>
                                    <input id="reopenReason" name="reason" class="form-control form-control-sm<?= error('reason') ? ' is-invalid' : '' ?>" maxlength="255" required placeholder="<?= e(__('schedule.reason')) ?>">
                                    <?php if (error('reason')): ?><div class="invalid-feedback d-block"><?= e(error('reason')) ?></div><?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-unlock me-1" aria-hidden="true"></i><?= e(__('workload.reopen')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="row g-3 h-100">
            <?php foreach ([
                ['workload.limit', $plan['max_hours'] ?? null, 'bi-speedometer2', ''],
                ['workload.plan', $totals['plan'], 'bi-calendar-check', $plan !== null && $plan['max_hours'] !== null && $totals['plan'] > (int) $plan['max_hours'] ? 'text-danger' : ''],
                ['workload.fact', $totals['fact'], 'bi-check2-all', ''],
                ['workload.remainder', $totals['remainder'], 'bi-hourglass-split', $totals['remainder'] < 0 ? 'text-danger' : ''],
            ] as [$label, $value, $icon, $class]): ?>
                <div class="col-6">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-muted"><i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e(__($label)) ?></div>
                        <div class="h4 fw-bold mb-0 <?= e($class) ?>"><?= e($value === null ? '—' : $h($value)) ?></div>
                    </div></div>
                </div>
            <?php endforeach; ?>
            <?php if ($totals['unplanned'] > 0): ?>
                <div class="col-12"><div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= e(__('workload.unplanned_total', ['hours' => $h($totals['unplanned'])])) ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($report['rows'] === []): ?>
    <div class="card mb-3"><div class="app-empty"><i class="bi bi-hourglass" aria-hidden="true"></i><p class="mb-0"><?= e(__('workload.no_lines')) ?></p></div></div>
<?php endif; ?>
<?php foreach ($bySemester as $semester => $rows): ?>
    <div class="card mb-3">
        <div class="card-header fw-semibold"><?= e($semester) ?></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th><?= e(__('fields.group_id')) ?></th>
                    <th><?= e(__('curricula.subject')) ?></th>
                    <th><?= e(__('fields.workload_type_id')) ?></th>
                    <th class="text-end" title="<?= e(__('workload.curriculum_hint')) ?>"><?= e(__('workload.curriculum')) ?></th>
                    <th class="text-end"><?= e(__('workload.plan')) ?></th>
                    <th class="text-end"><?= e(__('workload.fact')) ?></th>
                    <th class="text-end"><?= e(__('workload.remainder')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap"><?= e($r['label'][2]) ?></td>
                        <td>
                            <?php if ($r['assignment_id'] !== null): ?><a href="<?= e(url('/workload/assignments/' . $r['assignment_id'])) ?>" class="text-decoration-none"><?= e($r['label'][3]) ?></a>
                            <?php else: ?><?= e($r['label'][3]) ?> <span class="badge app-badge-warning" title="<?= e(__('workload.unplanned_hint')) ?>"><?= e(__('workload.unplanned')) ?></span><?php endif; ?>
                        </td>
                        <td><?= e($r['label'][4]) ?></td>
                        <td class="text-end text-muted"><?= e($r['curriculum'] ?? '—') ?></td>
                        <td class="text-end fw-semibold"><?= e($r['plan']) ?></td>
                        <td class="text-end"><?= e($h($r['fact'])) ?> <i class="bi <?= ($r['source'] ?? '') === 'journal' ? 'bi-journal-bookmark' : 'bi-pencil-square' ?> text-muted small" title="<?= e(__('workload.source.' . ($r['source'] ?? 'manual'))) ?>" aria-label="<?= e(__('workload.source.' . ($r['source'] ?? 'manual'))) ?>"></i></td>
                        <td class="text-end<?= $r['remainder'] < 0 ? ' text-danger' : '' ?>"><?= e($h($r['remainder'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($editable): ?>
    <div class="card" id="add-line">
        <div class="card-header fw-semibold"><?= e(__('workload.add_line')) ?></div>
        <form method="post" action="<?= e(url('/workload/plans/' . $plan['id'] . '/assignments')) ?>" class="card-body" novalidate>
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'semester_id', 'label' => __('fields.semester_id'), 'type' => 'select', 'options' => $options['semesters'], 'required' => true, 'idPrefix' => 'wa_']) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'workload_type_id', 'label' => __('fields.workload_type_id'), 'type' => 'select', 'options' => $options['types'], 'required' => true, 'idPrefix' => 'wa_']) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'options' => $none + $options['groups'], 'idPrefix' => 'wa_']) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'subgroup', 'label' => __('fields.subgroup'), 'type' => 'select', 'options' => $none + [1 => '1', 2 => '2', 3 => '3', 4 => '4'], 'idPrefix' => 'wa_']) ?></div>
                <div class="col-12 col-md-6"><?= $this->partial('partials/field', ['name' => 'subject_id', 'label' => __('curricula.subject'), 'type' => 'select', 'options' => $none + $options['subjects'], 'idPrefix' => 'wa_']) ?></div>
                <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'hours', 'label' => __('workload.hours'), 'type' => 'number', 'required' => true, 'attrs' => ['min' => 1, 'max' => 2000], 'idPrefix' => 'wa_']) ?></div>
                <div class="col-6 col-md-4"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'attrs' => ['maxlength' => 255], 'idPrefix' => 'wa_', 'ownsInput' => old('workload_type_id') !== null]) ?></div>
            </div>
            <div class="form-text mb-3"><?= e(__('workload.add_line_hint')) ?></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('workload.add_line')) ?></button>
        </form>
    </div>
<?php endif; ?>
