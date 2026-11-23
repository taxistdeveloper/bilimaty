<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $colleges */
/** @var array<string, int> $states */
/** @var bool $archived */
/** @var int $archivedCount */
/** @var float $monthly */
/** @var float $paidYear */
/** @var array{n: int, total: string} $unpaid */
$money = static fn (float|string $v): string => number_format((float) $v, 0, ',', ' ') . ' ₸';
$cards = [
    ['platform.stat_colleges', count($colleges), 'bi-buildings'],
    ['platform.stat_attention', ($states['expiring'] ?? 0) + ($states['grace'] ?? 0) + ($states['readonly'] ?? 0), 'bi-exclamation-triangle'],
    ['platform.stat_monthly', $money($monthly), 'bi-graph-up'],
    ['platform.stat_paid_year', $money($paidYear), 'bi-cash-coin'],
];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('platform.colleges'),
    'subtitle' => __('platform.colleges_hint'),
    'actions' => '<a href="' . e(url('/?format=csv')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>'
        . '<a href="' . e(url('/colleges/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('platform.college_create')) . '</a>',
]) ?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon]): ?>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
            <i class="bi <?= e($icon) ?> fs-3 text-primary" aria-hidden="true"></i>
            <div><div class="fs-5 fw-bold lh-1"><?= e($value) ?></div><div class="small text-muted"><?= e(__($label)) ?></div></div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= !$archived ? ' active' : '' ?>" href="<?= e(url('/')) ?>"><?= e(__('platform.working')) ?></a></li>
    <li class="nav-item"><a class="nav-link<?= $archived ? ' active' : '' ?>" href="<?= e(url('/?archived=1')) ?>"><?= e(__('platform.archive')) ?> <span class="badge text-bg-light"><?= e($archivedCount) ?></span></a></li>
</ul>
<?php if ((int) $unpaid['n'] > 0): ?>
    <div class="alert alert-warning small"><i class="bi bi-receipt me-1" aria-hidden="true"></i><?= e(__('platform.unpaid_invoices', ['count' => $unpaid['n'], 'total' => $money($unpaid['total'])])) ?></div>
<?php endif; ?>

<div class="card">
    <?php if ($colleges === []): ?>
        <div class="app-empty"><i class="bi bi-buildings" aria-hidden="true"></i><p class="mb-0"><?= e(__('platform.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th scope="col"><?= e(__('platform.college')) ?></th>
                    <th scope="col"><?= e(__('platform.state')) ?></th>
                    <th scope="col"><?= e(__('platform.paid_until')) ?></th>
                    <th scope="col" class="text-end d-none d-md-table-cell"><?= e(__('platform.students')) ?></th>
                    <th scope="col" class="text-end d-none d-md-table-cell"><?= e(__('platform.teachers')) ?></th>
                    <th scope="col" class="text-end d-none d-lg-table-cell"><?= e(__('platform.active_users_30')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('platform.last_login')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($colleges as $c): $s = $c['stats']; ?>
                    <tr>
                        <td><a href="<?= e(url('/colleges/' . $c['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($c['name']) ?></a>
                            <div class="small text-muted">/<?= e($c['code']) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?></div></td>
                        <td><?= $this->partial('platform/_state', ['state' => $c['state']]) ?></td>
                        <td class="small text-nowrap"><?= e(format_date($c['paid_until'])) ?></td>
                        <?php if ($s['error'] !== null): ?>
                            <td colspan="4" class="small text-danger d-none d-md-table-cell"><?= e(__('platform.db_unavailable')) ?></td>
                        <?php else: ?>
                            <td class="text-end small d-none d-md-table-cell<?= $c['max_students'] !== null && $s['students'] > (int) $c['max_students'] ? ' text-danger fw-semibold' : '' ?>"><?= e($s['students']) ?><?= $c['max_students'] !== null ? ' / ' . e($c['max_students']) : '' ?></td>
                            <td class="text-end small d-none d-md-table-cell"><?= e($s['teachers']) ?></td>
                            <td class="text-end small d-none d-lg-table-cell"><?= e($s['active_users_30']) ?></td>
                            <td class="small d-none d-lg-table-cell"><?= e($s['last_login'] ? format_date($s['last_login'], 'd.m.Y H:i') : '—') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
