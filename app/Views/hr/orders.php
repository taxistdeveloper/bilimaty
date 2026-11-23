<?php
/** @var App\Core\View $this */
/** @var array{type: string, status: string, year: int} $filters */
/** @var list<array<string, mixed>> $orders */
$years = range((int) date('Y') + 1, (int) date('Y') - 5);
?>
<?= $this->partial('partials/page-header', ['heading' => __('hr.orders'), 'subtitle' => __('hr.orders_hint')]) ?>
<?= $this->partial('hr/_nav', ['active' => 'orders']) ?>

<div class="card">
    <form method="get" action="<?= e(url('/hr/orders')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="year"><?= e(__('hr.year')) ?></label>
            <select class="form-select" id="year" name="year"><?php foreach ($years as $y): ?><option value="<?= e($y) ?>"<?= $filters['year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="type"><?= e(__('fields.type')) ?></label>
            <select class="form-select" id="type" name="type"><option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (App\Services\HrService::ORDER_TYPES as $t): ?><option value="<?= e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= e(__('hr.order_types.' . $t)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status"><option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (['draft', 'signed', 'cancelled'] as $s): ?><option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(__('hr.order_statuses.' . $s)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
    <?php if ($orders === []): ?>
        <div class="app-empty"><i class="bi bi-file-earmark-ruled" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.no_orders')) ?></p><p class="small text-muted mb-0"><?= e(__('hr.orders_empty_hint')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th scope="col"><?= e(__('fields.number')) ?></th>
                    <th scope="col"><?= e(__('fields.order_date')) ?></th>
                    <th scope="col"><?= e(__('fields.type')) ?></th>
                    <th scope="col"><?= e(__('hr.employee')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('fields.effective_date')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= e(url('/hr/orders/' . $o['id'])) ?>" class="fw-semibold text-decoration-none text-nowrap"><?= e($o['number'] ?? __('hr.no_number')) ?></a></td>
                        <td class="small"><?= e(format_date($o['order_date'])) ?></td>
                        <td class="small"><?= e(__('hr.order_types.' . $o['type'])) ?></td>
                        <td class="small"><?= e(full_name($o)) ?> <span class="text-muted">· № <?= e($o['personnel_number']) ?></span></td>
                        <td class="small d-none d-md-table-cell"><?= e(format_date($o['effective_date'])) ?></td>
                        <td><?= $this->partial('hr/_order_status', ['status' => $o['status']]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
