<?php
/**
 * Summary grade sheet of a group (сводная ведомость): marks by subject, subject totals in the table
 * footer and the group totals under the table.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $data
 * @var bool $print
 * @var string $kind
 * @var array<int, string> $groups
 * @var array<int, string> $semesters
 * @var int $groupId
 * @var int $semesterId
 * @var string $groupCode
 * @var string $semesterTitle
 */
$pct = static fn (?float $v): string => $v === null ? '—' : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . '%';
$avg = static fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '');
$markClass = static fn (int $m): string => match ($m) { 5 => 'text-success', 2 => 'text-danger', default => '' };
$summary = $data['summary'] ?? null;
?>
<?= $this->partial('reports/_head', ['kind' => $kind, 'groups' => $groups, 'semesters' => $semesters, 'groupId' => $groupId, 'semesterId' => $semesterId, 'groupCode' => $groupCode, 'semesterTitle' => $semesterTitle, 'print' => $print]) ?>
<?php if ($data['students'] === []): ?>
    <?= $print ? '<p>' . e(__('reports.empty')) . '</p>' : '<div class="card"><div class="app-empty"><i class="bi bi-table" aria-hidden="true"></i><p class="mb-0">' . e(__('reports.empty')) . '</p></div></div>' ?>
<?php else: ?>
    <?php if (!$print): ?><p class="small text-muted"><?= e(__('reports.gs_note')) ?></p><div class="card mb-3"><div class="table-responsive"><?php endif; ?>
    <table class="gs <?= $print ? '' : 'table table-sm table-bordered align-middle mb-0 app-table' ?>">
        <thead class="<?= $print ? '' : 'table-light' ?>">
        <tr>
            <th>№</th><th class="l"><?= e(__('journal.student')) ?></th>
            <?php foreach ($data['subjects'] as $s): ?><th class="text-center" title="<?= e($s['name']) ?>"><?= e($s['code']) ?><?= $s['status'] === 'approved' ? ' ✓' : '' ?></th><?php endforeach; ?>
            <th class="text-center"><?= e(__('reports.gs_average')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['students'] as $i => $st): ?>
            <tr>
                <td class="text-center"><?= e($i + 1) ?></td>
                <td class="l text-nowrap"><?= e($st['name']) ?></td>
                <?php foreach ($data['subjects'] as $s): $g = $st['cells'][$s['id']]['grade'] ?? null; ?>
                    <td class="text-center">
                        <?php if ($g !== null): ?>
                            <span class="fw-semibold <?= $print ? ($g['mark'] === 2 ? 'm2' : '') : $markClass($g['mark']) ?>"<?= $g['percent'] !== null ? ' title="' . e(rtrim(rtrim(number_format($g['percent'], 1, ',', ''), '0'), ',')) . '%"' : '' ?>><?= e($g['mark']) ?></span><?php if ($g['letter'] !== null): ?><span class="lt <?= $print ? '' : 'small text-muted ms-1' ?>"><?= e($g['letter']) ?></span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                <td class="text-center fw-semibold"><?= e($avg($st['stats']['average'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="<?= $print ? '' : 'table-light' ?>">
        <?php foreach (['absolute' => $pct, 'quality' => $pct, 'average' => $avg] as $key => $fmt): ?>
            <tr>
                <td></td>
                <td class="l fw-semibold"><?= e(__('reports.gs_' . $key)) ?></td>
                <?php foreach ($data['subjects'] as $s): ?><td class="text-center fw-semibold"><?= e($fmt($data['subject_stats'][$s['id']][$key] ?? null)) ?></td><?php endforeach; ?>
                <td class="text-center fw-bold"><?= e($fmt($summary[$key] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td></td>
            <td class="l small text-muted"><?= e(__('reports.gs_counts')) ?></td>
            <?php foreach ($data['subjects'] as $s): $c = $data['subject_stats'][$s['id']]['counts'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0]; ?><td class="text-center small text-nowrap"><?= e($c[5] . '/' . $c[4] . '/' . $c[3] . '/' . $c[2]) ?></td><?php endforeach; ?>
            <td></td>
        </tr>
        </tfoot>
    </table>
    <?php if (!$print): ?></div></div><?php endif; ?>
    <p class="small text-muted mt-2 mb-3"><?php foreach ($data['subjects'] as $s): ?><?= e($s['code'] . ' — ' . $s['name']) ?>; <?php endforeach; ?></p>

    <?php if ($summary !== null): ?>
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('reports.gs_group_total')) ?></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <?php foreach (['absolute' => $pct($summary['absolute']), 'quality' => $pct($summary['quality']), 'average' => $avg($summary['average'])] as $key => $value): ?>
                            <div class="col-12 col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted"><?= e(__('reports.gs_' . $key)) ?></div>
                                    <div class="fs-3 fw-bold"><?= e($value) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <table class="table table-sm mb-2">
                        <tbody>
                        <tr><td class="w-25"><?= e(__('reports.gs_students')) ?></td><td><span class="fw-semibold"><?= e($summary['students']) ?></span> <span class="text-muted small">(<?= e(__('reports.gs_graded', ['n' => $summary['graded']])) ?>)</span></td></tr>
                        <?php foreach ($summary['categories'] as $key => $names): if ($key === 'ungraded' && $names === []) { continue; } ?>
                            <tr>
                                <td><?= e(__('reports.gs_cat.' . $key)) ?></td>
                                <td><span class="fw-semibold<?= $key === 'failing' && $names !== [] ? ' text-danger' : '' ?>"><?= e(count($names)) ?></span><?php if ($names !== []): ?> <span class="small text-muted">— <?= e(implode(', ', $names)) ?></span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0"><?= e(__('reports.gs_formula')) ?></p>
                </div>
            </div>
    <?php endif; ?>
<?php endif; ?>
