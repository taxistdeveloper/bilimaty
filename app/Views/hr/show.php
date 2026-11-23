<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $employee */
/** @var list<array<string, mixed>> $orders */
/** @var list<array<string, mixed>> $leaves */
/** @var list<array<string, mixed>> $trainings */
/** @var array{entitled: int, used: int, requested: int, left: int} $balance */
/** @var int $year */
/** @var int $experience months */
/** @var bool $canManage */
/** @var array<int, string> $departments */
/** @var string $tab orders|leaves|trainings */
$e = $employee;
$statusClass = ['active' => 'app-badge-success', 'on_leave' => 'app-badge-warning', 'dismissed' => 'app-badge-danger'];
$today = date('Y-m-d');
$rows = [
    'fields.personnel_number' => $e['personnel_number'],
    'fields.department_id' => $e['department_name_ru'] !== null ? localized(['name_ru' => $e['department_name_ru'], 'name_kk' => $e['department_name_kk']]) : null,
    'fields.position' => $e['position'],
    'fields.staff_category' => __('hr.staff_categories.' . $e['staff_category']),
    'fields.employment_type' => __('hr.employment_types.' . $e['employment_type']),
    'fields.rate' => $e['rate'],
    'fields.hire_date' => format_date($e['hire_date']),
    'fields.dismissal_date' => format_date($e['dismissal_date']),
    'hr.experience' => App\Controllers\HrController::years($experience),
    'fields.qualification_category' => $e['qualification_category'] ? __('hr.categories.' . $e['qualification_category']) : null,
    'fields.category_valid_until' => format_date($e['category_valid_until']),
    'fields.phone' => $e['phone'],
    'fields.email' => $e['email'],
];
// Personal data is shown to the personnel department only.
if ($canManage) {
    $rows += ['fields.iin' => $e['iin'], 'fields.birth_date' => format_date($e['birth_date']), 'fields.address' => $e['address'], 'fields.education' => $e['education'], 'fields.note' => $e['note']];
}
$fieldOpts = static function (array $keys, string $prefix): array {
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __($prefix . $k);
    }

    return $out;
};
$orderTypes = $e['status'] === 'dismissed' ? ['hire'] : ['transfer', 'rate_change', 'dismissal', 'other', 'hire'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => full_name($e),
    'subtitle' => trim(($e['position'] ?: __('hr.employee')) . ' · № ' . $e['personnel_number']),
    'back' => '/hr/employees',
    'actions' => ($e['teacher_id'] !== null ? '<a href="' . e(url('/teachers/' . $e['teacher_id'])) . '" class="btn btn-outline-secondary"><i class="bi bi-person-badge me-1" aria-hidden="true"></i>' . e(__('hr.teacher_card')) . '</a>' : '')
        . ($canManage ? '<a href="' . e(url('/hr/employees/' . $e['id'] . '/edit')) . '" class="btn btn-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>' : ''),
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('hr.personal_file')) ?></span>
                <span class="badge <?= e($statusClass[$e['status']] ?? '') ?>"><?= e(__('hr.statuses.' . $e['status'])) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($rows as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-muted"><?= e(__($label)) ?></span>
                        <span class="text-end text-break"><?= e($value !== null && $value !== '' ? $value : '—') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('hr.balance_title', ['year' => $year])) ?></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_entitled')) ?></span><strong><?= e($balance['entitled']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_used')) ?></span><span><?= e($balance['used']) ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_requested')) ?></span><span><?= e($balance['requested']) ?></span></div>
                <div class="d-flex justify-content-between border-top mt-1 pt-1"><span class="fw-semibold"><?= e(__('hr.balance_left')) ?></span><strong class="<?= $balance['left'] < 0 ? 'text-danger' : '' ?>"><?= e($balance['left']) ?></strong></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link<?= $tab === 'orders' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-controls="orders" aria-selected="<?= $tab === 'orders' ? 'true' : 'false' ?>"><?= e(__('hr.orders')) ?> <span class="badge text-bg-light"><?= e(count($orders)) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link<?= $tab === 'leaves' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab" aria-controls="leaves" aria-selected="<?= $tab === 'leaves' ? 'true' : 'false' ?>"><?= e(__('hr.leaves')) ?> <span class="badge text-bg-light"><?= e(count($leaves)) ?></span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link<?= $tab === 'trainings' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#trainings" type="button" role="tab" aria-controls="trainings" aria-selected="<?= $tab === 'trainings' ? 'true' : 'false' ?>"><?= e(__('hr.trainings')) ?> <span class="badge text-bg-light"><?= e(count($trainings)) ?></span></button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade<?= $tab === 'orders' ? ' show active' : '' ?>" id="orders" role="tabpanel">
                <div class="card mb-3">
                    <?php if ($orders === []): ?>
                        <div class="card-body small text-muted"><?= e(__('hr.no_orders')) ?></div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($orders as $o): ?>
                                <a href="<?= e(url('/hr/orders/' . $o['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2 small">
                                    <span><strong><?= e(__('hr.order_types.' . $o['type'])) ?></strong> <span class="text-muted">· <?= e($o['number'] ?? '—') ?> <?= e(__('hr.from_date', ['date' => format_date($o['order_date'])])) ?> · <?= e(__('hr.effective', ['date' => format_date($o['effective_date'])])) ?></span></span>
                                    <?= $this->partial('hr/_order_status', ['status' => $o['status']]) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($canManage): ?>
                    <h3 class="h6 fw-bold"><?= e(__('hr.new_order')) ?></h3>
                    <?php foreach ($orderTypes as $type): ?>
                        <details class="card mb-2"<?= old('_form') === 'order' && old('type') === $type ? ' open' : '' ?>>
                            <summary class="card-header fw-semibold"><?= e(__('hr.order_types.' . $type)) ?></summary>
                            <form method="post" action="<?= e(url('/hr/employees/' . $e['id'] . '/orders')) ?>" novalidate class="card-body row">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="_form" value="order">
                                <?php $own = old('_form') === 'order' && old('type') === $type; $p = 'o_' . $type . '_'; ?>
                                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'order_date', 'label' => __('fields.order_date'), 'type' => 'date', 'value' => $today, 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own]) ?></div>
                                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'effective_date', 'label' => __('fields.effective_date'), 'type' => 'date', 'value' => $today, 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own]) ?></div>
                                <?php if (in_array($type, ['hire', 'transfer'], true)): ?>
                                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'position', 'label' => __('fields.position'), 'value' => $e['position'] ?? '', 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own, 'attrs' => ['maxlength' => 150]]) ?></div>
                                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'department_id', 'label' => __('fields.department_id'), 'type' => 'select', 'value' => $e['department_id'] ?? '', 'options' => ['' => '—'] + $departments, 'idPrefix' => $p, 'ownsInput' => $own]) ?></div>
                                <?php endif; ?>
                                <?php if (in_array($type, ['hire', 'rate_change'], true)): ?>
                                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'rate', 'label' => __('fields.rate'), 'value' => $e['rate'], 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own, 'attrs' => ['inputmode' => 'decimal']]) ?></div>
                                <?php endif; ?>
                                <?php if ($type === 'hire'): ?>
                                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'staff_category', 'label' => __('fields.staff_category'), 'type' => 'select', 'value' => $e['staff_category'], 'options' => $fieldOpts(App\Services\HrService::CATEGORIES, 'hr.staff_categories.'), 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own]) ?></div>
                                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'employment_type', 'label' => __('fields.employment_type'), 'type' => 'select', 'value' => $e['employment_type'], 'options' => $fieldOpts(App\Services\HrService::EMPLOYMENT, 'hr.employment_types.'), 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own]) ?></div>
                                <?php endif; ?>
                                <?php if ($type === 'dismissal'): ?>
                                    <div class="col-12"><?= $this->partial('partials/field', ['name' => 'reason', 'label' => __('fields.reason'), 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own, 'help' => __('hr.dismissal_hint'), 'attrs' => ['maxlength' => 255]]) ?></div>
                                <?php endif; ?>
                                <?php if ($type === 'other'): ?>
                                    <div class="col-12"><?= $this->partial('partials/field', ['name' => 'text', 'label' => __('hr.order_text'), 'required' => true, 'idPrefix' => $p, 'ownsInput' => $own, 'attrs' => ['maxlength' => 1000]]) ?></div>
                                <?php endif; ?>
                                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'basis', 'label' => __('fields.basis'), 'idPrefix' => $p, 'ownsInput' => $own, 'help' => __('hr.basis_hint'), 'attrs' => ['maxlength' => 500]]) ?></div>
                                <div class="col-12"><button type="submit" class="btn btn-primary"><?= e(__('hr.create_draft')) ?></button></div>
                            </form>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade<?= $tab === 'leaves' ? ' show active' : '' ?>" id="leaves" role="tabpanel">
                <div class="card mb-3">
                    <?php if ($leaves === []): ?>
                        <div class="card-body small text-muted"><?= e(__('hr.no_leaves')) ?></div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($leaves as $l): ?>
                                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                    <span><strong><?= e(__('hr.leave_types.' . $l['type'])) ?></strong>
                                        <span class="text-muted">· <?= e(format_date($l['start_date'])) ?>–<?= e(format_date($l['end_date'])) ?> · <?= e(__('hr.days', ['n' => $l['days']])) ?><?= $l['document_number'] ? ' · № ' . e($l['document_number']) : '' ?><?= $l['destination'] ? ' · ' . e($l['destination']) : '' ?></span></span>
                                    <span class="d-flex gap-2 align-items-center">
                                        <?= $this->partial('hr/_leave_status', ['status' => $l['status']]) ?>
                                        <?php if ($canManage && in_array($l['status'], ['requested', 'approved'], true)): ?>
                                            <form method="post" action="<?= e(url('/hr/leaves/' . $l['id'] . '/cancel')) ?>" data-confirm="<?= e(__('hr.leave_cancel_confirm')) ?>" data-confirm-danger><?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= e(__('hr.cancel')) ?></button></form>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if ($canManage && $e['status'] !== 'dismissed'): ?>
                    <div class="card"><div class="card-header fw-semibold"><?= e(__('hr.leave_record')) ?></div><div class="card-body">
                        <?= $this->partial('hr/_leave_form', ['action' => '/hr/employees/' . $e['id'] . '/leaves', 'types' => App\Services\HrService::LEAVE_TYPES, 'byHr' => true]) ?>
                    </div></div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade<?= $tab === 'trainings' ? ' show active' : '' ?>" id="trainings" role="tabpanel">
                <div class="card mb-3">
                    <?php if ($trainings === []): ?>
                        <div class="card-body small text-muted"><?= e(__('hr.no_trainings')) ?></div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($trainings as $t): ?>
                                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                    <span><strong><?= e($t['title']) ?></strong>
                                        <span class="text-muted d-block"><?= e($t['provider'] ?: '') ?> · <?= e(format_date($t['start_date'])) ?>–<?= e(format_date($t['end_date'])) ?> · <?= e(__('hr.hours', ['n' => $t['hours']])) ?><?= $t['certificate_number'] ? ' · ' . e(__('hr.certificate', ['number' => $t['certificate_number']])) : '' ?></span></span>
                                    <?php if ($canManage): ?>
                                        <form method="post" action="<?= e(url('/hr/trainings/' . $t['id'])) ?>" data-confirm="<?= e(__('hr.training_delete_confirm')) ?>" data-confirm-danger><?= csrf_field() ?><?= method_field('DELETE') ?>
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if ($canManage): ?>
                    <div class="card"><div class="card-header fw-semibold"><?= e(__('hr.training_add')) ?></div>
                        <form method="post" action="<?= e(url('/hr/employees/' . $e['id'] . '/trainings')) ?>" novalidate class="card-body row">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_form" value="training">
                            <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'title', 'label' => __('hr.training_title'), 'required' => true, 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training', 'attrs' => ['maxlength' => 255]]) ?></div>
                            <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'hours', 'label' => __('fields.hours'), 'type' => 'number', 'required' => true, 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training', 'attrs' => ['min' => 1, 'max' => 2000]]) ?></div>
                            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'provider', 'label' => __('hr.provider'), 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training', 'attrs' => ['maxlength' => 255]]) ?></div>
                            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'certificate_number', 'label' => __('hr.certificate_number'), 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training', 'attrs' => ['maxlength' => 100]]) ?></div>
                            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'start_date', 'label' => __('fields.start_date'), 'type' => 'date', 'required' => true, 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training']) ?></div>
                            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'end_date', 'label' => __('fields.end_date'), 'type' => 'date', 'required' => true, 'idPrefix' => 'tr_', 'ownsInput' => old('_form') === 'training']) ?></div>
                            <div class="col-12"><button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button></div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
