<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $leaves */
/** @var string $month */
/** @var string $status */
/** @var string $type */
/** @var bool $canManage */
?>
<?= $this->partial('partials/page-header', ['heading' => __('hr.leaves'), 'subtitle' => __('hr.leaves_hint')]) ?>
<?= $this->partial('hr/_nav', ['active' => 'leaves']) ?>

<div class="card">
    <form method="get" action="<?= e(url('/hr/leaves')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="month"><?= e(__('hr.month')) ?></label>
            <input type="month" class="form-control" id="month" name="month" value="<?= e($month) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status"><option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (['requested', 'approved', 'rejected', 'cancelled'] as $s): ?><option value="<?= e($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(__('hr.leave_statuses.' . $s)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="type"><?= e(__('fields.type')) ?></label>
            <select class="form-select" id="type" name="type"><option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (App\Services\HrService::LEAVE_TYPES as $t): ?><option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e(__('hr.leave_types.' . $t)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
        <?php if ($status === 'requested'): ?><div class="col-12 small text-muted"><?= e(__('hr.requested_all_dates')) ?></div><?php endif; ?>
    </form>
    <?php if ($leaves === []): ?>
        <div class="app-empty"><i class="bi bi-calendar2-check" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.no_leaves')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th scope="col"><?= e(__('hr.employee')) ?></th>
                    <th scope="col"><?= e(__('fields.type')) ?></th>
                    <th scope="col"><?= e(__('hr.period')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                    <?php if ($canManage): ?><th scope="col" class="text-end"><?= e(__('common.actions')) ?></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($leaves as $l): ?>
                    <tr>
                        <td><a href="<?= e(url('/hr/employees/' . $l['employee_id'] . '?tab=leaves')) ?>" class="fw-semibold text-decoration-none"><?= e(full_name($l)) ?></a>
                            <div class="small text-muted"><?= e($l['position'] ?: '') ?></div></td>
                        <td class="small"><?= e(__('hr.leave_types.' . $l['type'])) ?><?= $l['reason'] ? '<div class="text-muted">' . e($l['reason']) . '</div>' : '' ?><?= $l['decision_comment'] ? '<div class="text-muted fst-italic">' . e($l['decision_comment']) . '</div>' : '' ?></td>
                        <td class="small text-nowrap"><?= e(format_date($l['start_date'])) ?>–<?= e(format_date($l['end_date'])) ?><div class="text-muted"><?= e(__('hr.days', ['n' => $l['days']])) ?></div></td>
                        <td><?= $this->partial('hr/_leave_status', ['status' => $l['status']]) ?></td>
                        <?php if ($canManage): ?>
                            <td class="text-end">
                                <?php if ($l['status'] === 'requested'): ?>
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        <form method="post" action="<?= e(url('/hr/leaves/' . $l['id'] . '/approve')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-success"><?= e(__('hr.approve')) ?></button></form>
                                        <form method="post" action="<?= e(url('/hr/leaves/' . $l['id'] . '/reject')) ?>" class="d-flex gap-1"><?= csrf_field() ?>
                                            <label class="visually-hidden" for="c<?= e($l['id']) ?>"><?= e(__('fields.comment')) ?></label>
                                            <input type="text" class="form-control form-control-sm" id="c<?= e($l['id']) ?>" name="comment" maxlength="500" placeholder="<?= e(__('hr.reject_reason')) ?>" required>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('hr.reject')) ?></button></form>
                                    </div>
                                <?php elseif ($l['status'] === 'approved'): ?>
                                    <form method="post" action="<?= e(url('/hr/leaves/' . $l['id'] . '/cancel')) ?>" data-confirm="<?= e(__('hr.leave_cancel_confirm')) ?>" data-confirm-danger><?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= e(__('hr.cancel')) ?></button></form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
