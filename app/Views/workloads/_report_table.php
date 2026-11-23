<?php
/**
 * Plan/fact report table shared by the reports page and its A4 print.
 *
 * @var array<string, mixed> $report rows, totals, curriculum, limits
 * @var list<string> $header label columns
 * @var bool $doc print document (plain table)
 * @var int $yearId
 */
use App\Services\WorkloadService;

$h = static fn ($v): string => WorkloadService::hours($v);
$t = $report['totals'];
?>
<?php if (!$doc): ?><div class="table-responsive"><?php endif; ?>
<table class="<?= $doc ? '' : 'table table-sm table-hover align-middle mb-0 app-table' ?>">
    <thead class="<?= $doc ? '' : 'table-light' ?>">
    <tr>
        <?php foreach ($header as $col): ?><th><?= e($col) ?></th><?php endforeach; ?>
        <?php if ($report['limits']): ?><th class="text-end"><?= e(__('workload.rate')) ?></th><th class="text-end"><?= e(__('workload.limit')) ?></th><?php endif; ?>
        <?php if ($report['curriculum']): ?><th class="text-end" title="<?= e(__('workload.curriculum_hint')) ?>"><?= e(__('workload.curriculum')) ?></th><?php endif; ?>
        <th class="text-end"><?= e(__('workload.plan')) ?></th>
        <th class="text-end"><?= e(__('workload.fact')) ?></th>
        <th class="text-end"><?= e(__('workload.remainder')) ?></th>
        <th class="text-end">%</th>
        <th class="text-end" title="<?= e(__('workload.unplanned_hint')) ?>"><?= e(__('workload.unplanned')) ?></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($report['rows'] as $r): ?>
        <tr>
            <?php foreach ($r['label'] as $i => $cell): ?>
                <td<?= $i === 0 ? ' class="fw-semibold"' : '' ?>>
                    <?php if (!$doc && $i === 0 && $report['by'] === 'teacher'): ?><a href="<?= e(url('/workload/teachers/' . $r['teacher_id'] . '?year=' . $yearId)) ?>" class="text-decoration-none"><?= e($cell) ?></a>
                    <?php elseif (!$doc && $i === 3 && $report['by'] === 'detail' && !empty($r['assignment_id'])): ?><a href="<?= e(url('/workload/assignments/' . $r['assignment_id'])) ?>" class="text-decoration-none"><?= e($cell) ?></a>
                    <?php else: ?><?= e($cell) ?><?php endif; ?>
                </td>
            <?php endforeach; ?>
            <?php if ($report['limits']): ?>
                <td class="text-end"><?= e($r['rate'] !== null ? $h($r['rate']) : '—') ?></td>
                <td class="text-end"><?= e($r['limit'] ?? '—') ?></td>
            <?php endif; ?>
            <?php if ($report['curriculum']): ?><td class="text-end"><?= e($r['curriculum'] ?? '—') ?></td><?php endif; ?>
            <td class="text-end fw-semibold<?= !empty($r['over_limit']) ? ' text-danger' : '' ?>"><?= e($r['plan']) ?><?= !empty($r['over_limit']) ? ' !' : '' ?></td>
            <td class="text-end"><?= e($h($r['fact'])) ?></td>
            <td class="text-end<?= $r['remainder'] < 0 ? ' text-danger' : '' ?>"><?= e($h($r['remainder'])) ?></td>
            <td class="text-end"><?= e($r['percent'] ?? '—') ?></td>
            <td class="text-end"><?= e($r['unplanned'] > 0 ? $h($r['unplanned']) : '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="<?= $doc ? '' : 'table-light' ?>">
    <tr class="fw-semibold">
        <td colspan="<?= e(count($header)) ?>"><strong><?= e(__('workload.total')) ?></strong></td>
        <?php if ($report['limits']): ?><td></td><td class="text-end"><?= e($t['limit'] ?: '—') ?></td><?php endif; ?>
        <?php if ($report['curriculum']): ?><td class="text-end"><?= e($t['curriculum'] ?: '—') ?></td><?php endif; ?>
        <td class="text-end"><strong><?= e($t['plan']) ?></strong></td>
        <td class="text-end"><strong><?= e($h($t['fact'])) ?></strong></td>
        <td class="text-end"><strong><?= e($h($t['remainder'])) ?></strong></td>
        <td class="text-end"><?= e($t['percent'] ?? '—') ?></td>
        <td class="text-end"><?= e($t['unplanned'] > 0 ? $h($t['unplanned']) : '') ?></td>
    </tr>
    </tfoot>
</table>
<?php if (!$doc): ?></div><?php endif; ?>
