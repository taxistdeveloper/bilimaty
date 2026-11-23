<?php
/** @var App\Core\View $this */
/** @var array{subjects: list<array<string, mixed>>, students: list<array<string, mixed>>} $data */
/** @var bool $print */
/** @var string $kind */
/** @var array<int, string> $groups */
/** @var array<int, string> $semesters */
/** @var int $groupId */
/** @var int $semesterId */
/** @var string $groupCode */
/** @var string $semesterTitle */
use App\Services\ReportService;
?>
<?= $this->partial('reports/_head', ['kind' => $kind, 'groups' => $groups, 'semesters' => $semesters, 'groupId' => $groupId, 'semesterId' => $semesterId, 'groupCode' => $groupCode, 'semesterTitle' => $semesterTitle, 'print' => $print]) ?>
<?php if ($data['students'] === []): ?>
    <?= $print ? '<p>' . e(__('reports.empty')) . '</p>' : '<div class="card"><div class="app-empty"><i class="bi bi-table" aria-hidden="true"></i><p class="mb-0">' . e(__('reports.empty')) . '</p></div></div>' ?>
<?php else: ?>
    <?php if (!$print): ?><p class="small text-muted"><?= e(__('reports.attendance_note')) ?></p><div class="card"><div class="table-responsive"><?php endif; ?>
    <table class="<?= $print ? '' : 'table table-sm table-bordered align-middle mb-0 app-table' ?>">
        <thead class="<?= $print ? '' : 'table-light' ?>">
        <tr>
            <th>№</th><th><?= e(__('journal.student')) ?></th>
            <?php foreach ($data['subjects'] as $s): ?><th class="text-center" title="<?= e($s['name']) ?>"><?= e($s['code']) ?><div class="small fw-normal text-muted"><?= e(__('reports.held', ['n' => $s['lessons']])) ?></div></th><?php endforeach; ?>
            <th class="text-center"><?= e(__('reports.absent')) ?></th><th class="text-center"><?= e(__('reports.excused')) ?></th><th class="text-center"><?= e(__('reports.attended')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['students'] as $i => $st): $pct = ReportService::attendance($st['lessons'], $st['absent'], $st['excused']); ?>
            <tr>
                <td><?= e($i + 1) ?></td>
                <td class="text-nowrap"><?= e($st['name']) ?></td>
                <?php foreach ($data['subjects'] as $s): $c = $st['cells'][$s['id']] ?? null; ?>
                    <td class="text-center"><?= $c === null ? '' : e($c['absent']) . ($c['excused'] > 0 ? ' <span class="text-muted">/ ' . e($c['excused']) . '</span>' : '') ?></td>
                <?php endforeach; ?>
                <td class="text-center<?= $st['absent'] > 0 ? ' text-danger fw-semibold' : '' ?>"><?= e($st['absent']) ?></td>
                <td class="text-center"><?= e($st['excused']) ?></td>
                <td class="text-center fw-semibold<?= $pct !== null && $pct < 80 ? ' text-danger' : '' ?>"><?= e($pct === null ? '—' : $pct . '%') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$print): ?></div></div><?php endif; ?>
    <p class="small text-muted mt-2"><?= e(__('reports.attendance_legend')) ?> <?php foreach ($data['subjects'] as $s): ?><?= e($s['code'] . ' — ' . $s['name']) ?>; <?php endforeach; ?></p>
<?php endif; ?>
