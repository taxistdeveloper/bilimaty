<?php
/** @var App\Core\View $this */
/** @var int $yearId */
/** @var int|null $department */
/** @var list<array<string, mixed>> $teachers */
/** @var array<int, array<string, mixed>> $totals report rows by teacher id */
/** @var array<int, string> $years */
/** @var array<int, string> $departments */
use App\Services\WorkloadService;

$h = static fn ($v): string => WorkloadService::hours($v);
$sum = ['plan' => 0, 'fact' => 0.0, 'limit' => 0, 'remainder' => 0.0];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('workload.title'),
    'subtitle' => __('workload.subtitle'),
    'actions' => '<a href="' . e(url('/workload/reports?year=' . $yearId)) . '" class="btn btn-outline-primary"><i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>' . e(__('workload.reports')) . '</a>',
]) ?>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/workload')) ?>" class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4"><?= $this->partial('partials/filter-select', ['name' => 'year', 'label' => __('fields.academic_year_id'), 'items' => $years, 'current' => $yearId, 'all' => false]) ?></div>
        <div class="col-12 col-md-5"><?= $this->partial('partials/filter-select', ['name' => 'department', 'label' => __('fields.department_id'), 'items' => $departments, 'current' => $department]) ?></div>
        <div class="col-12 col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
</div>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('workload.fact_hint')) ?></div>

<?php if ($teachers === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-hourglass" aria-hidden="true"></i><p class="mb-0"><?= e(__('workload.no_teachers')) ?></p></div></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th><?= e(__('workload.teacher')) ?></th>
                    <th class="text-end"><?= e(__('workload.rate')) ?></th>
                    <th class="text-end"><?= e(__('workload.limit')) ?></th>
                    <th class="text-end"><?= e(__('workload.plan')) ?></th>
                    <th class="text-end"><?= e(__('workload.fact')) ?></th>
                    <th class="text-end"><?= e(__('workload.remainder')) ?></th>
                    <th style="min-width: 140px"><?= e(__('workload.progress')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $t):
                    $r = $totals[(int) $t['teacher_id']] ?? ['plan' => 0, 'fact' => 0.0, 'remainder' => 0, 'percent' => null, 'unplanned' => 0.0];
                    $limit = $t['max_hours'] !== null ? (int) $t['max_hours'] : null;
                    $sum['plan'] += $r['plan'];
                    $sum['fact'] += $r['fact'];
                    $sum['limit'] += (int) $limit;
                    $sum['remainder'] += $r['remainder'];
                    $overLimit = $limit !== null && $r['plan'] > $limit;
                    $done = min(100, max(0, (float) ($r['percent'] ?? 0)));
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/workload/teachers/' . $t['teacher_id'] . '?year=' . $yearId)) ?>" class="fw-semibold text-decoration-none"><?= e(full_name($t, true)) ?></a>
                            <div class="small text-muted"><?= e($t['department_name_ru'] !== null ? localized(['name_ru' => $t['department_name_ru'], 'name_kk' => $t['department_name_kk']]) : __('workload.no_department')) ?></div>
                        </td>
                        <td class="text-end"><?= e($t['rate'] !== null ? $h($t['rate']) : '—') ?></td>
                        <td class="text-end"><?= e($limit ?? '—') ?></td>
                        <td class="text-end fw-semibold<?= $overLimit ? ' text-danger' : '' ?>"><?= e($r['plan']) ?><?php if ($overLimit): ?> <i class="bi bi-exclamation-triangle-fill" title="<?= e(__('workload.over_limit')) ?>" aria-label="<?= e(__('workload.over_limit')) ?>"></i><?php endif; ?></td>
                        <td class="text-end"><?= e($h($r['fact'])) ?><?php if ($r['unplanned'] > 0): ?> <span class="badge app-badge-warning" title="<?= e(__('workload.unplanned_hint')) ?>">+<?= e($h($r['unplanned'])) ?></span><?php endif; ?></td>
                        <td class="text-end<?= $r['remainder'] < 0 ? ' text-danger' : '' ?>"><?= e($h($r['remainder'])) ?></td>
                        <td>
                            <div class="progress" style="height: 8px" role="progressbar" aria-label="<?= e(__('workload.progress')) ?>" aria-valuenow="<?= e((int) $done) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar<?= $r['remainder'] < 0 ? ' bg-danger' : '' ?>" style="width: <?= e((int) $done) ?>%"></div>
                            </div>
                            <div class="small text-muted"><?= e($r['percent'] !== null ? $r['percent'] . '%' : '—') ?></div>
                        </td>
                        <td>
                            <?php if ($t['plan_id'] === null): ?><span class="badge app-badge-muted"><?= e(__('workload.no_plan')) ?></span>
                            <?php else: ?><span class="badge <?= $t['plan_status'] === 'approved' ? 'app-badge-success' : 'app-badge-warning' ?>"><?= e(__('workload.statuses.' . $t['plan_status'])) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td><?= e(__('workload.total')) ?></td>
                    <td></td>
                    <td class="text-end"><?= e($sum['limit'] ?: '—') ?></td>
                    <td class="text-end"><?= e($sum['plan']) ?></td>
                    <td class="text-end"><?= e($h($sum['fact'])) ?></td>
                    <td class="text-end"><?= e($h($sum['remainder'])) ?></td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>
