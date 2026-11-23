<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $colleges */
/** @var array<int, array<int, float>> $paid */
/** @var int $year */
$money = static fn (float $v): string => $v > 0 ? number_format($v, 0, ',', ' ') : '';
$totals = array_fill(1, 12, 0.0);
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('platform.reports'),
    'subtitle' => __('platform.reports_hint'),
    'actions' => '<a href="' . e(url('/reports?year=' . $year . '&format=csv')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>',
]) ?>
<form method="get" action="<?= e(url('/reports')) ?>" class="d-flex gap-2 align-items-end mb-3">
    <div><label class="form-label small text-muted mb-1" for="year"><?= e(__('hr.year')) ?></label>
        <select class="form-select" id="year" name="year" data-autosubmit-form><?php foreach (range((int) date('Y'), (int) date('Y') - 4) as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="btn btn-primary"><?= e(__('common.show')) ?></button>
</form>

<h2 class="h6 fw-bold"><?= e(__('platform.usage_by_college')) ?></h2>
<div class="card mb-4"><div class="table-responsive">
    <table class="table table-sm align-middle mb-0 app-table small">
        <thead class="table-light"><tr>
            <th scope="col"><?= e(__('platform.college')) ?></th><th scope="col"><?= e(__('platform.state')) ?></th>
            <?php foreach (['students', 'teachers', 'active_users_30', 'logins_30', 'grades_30', 'documents_30'] as $k): ?><th scope="col" class="text-end"><?= e(__('platform.' . $k)) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($colleges as $c): $s = $c['stats']; ?>
            <tr>
                <td><a href="<?= e(url('/colleges/' . $c['id'])) ?>" class="text-decoration-none"><?= e($c['name']) ?></a></td>
                <td><?= $this->partial('platform/_state', ['state' => $c['state']]) ?></td>
                <?php foreach (['students', 'teachers', 'active_users_30', 'logins_30', 'grades_30', 'documents_30'] as $k): ?><td class="text-end"><?= e($s[$k] ?? '—') ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>

<h2 class="h6 fw-bold"><?= e(__('platform.payments_by_month', ['year' => $year])) ?></h2>
<div class="card"><div class="table-responsive">
    <table class="table table-sm align-middle mb-0 app-table small">
        <thead class="table-light"><tr><th scope="col"><?= e(__('platform.college')) ?></th>
            <?php foreach (range(1, 12) as $m): ?><th scope="col" class="text-end"><?= e(sprintf('%02d', $m)) ?></th><?php endforeach; ?><th scope="col" class="text-end"><?= e(__('platform.total')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($colleges as $c): $row = $paid[(int) $c['id']] ?? []; ?>
            <tr><td><?= e($c['name']) ?></td>
                <?php foreach (range(1, 12) as $m): $v = $row[$m] ?? 0.0; $totals[$m] += $v; ?><td class="text-end"><?= e($money($v)) ?></td><?php endforeach; ?>
                <td class="text-end fw-semibold"><?= e($money(array_sum($row)) ?: '0') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light"><tr><th scope="row"><?= e(__('platform.total')) ?></th><?php foreach ($totals as $v): ?><th class="text-end"><?= e($money($v)) ?></th><?php endforeach; ?><th class="text-end"><?= e($money(array_sum($totals)) ?: '0') ?></th></tr></tfoot>
    </table>
</div></div>
