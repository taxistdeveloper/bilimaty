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
$n = static fn ($v): string => App\Services\JournalService::normalize($v) ?? '—';
?>
<?= $this->partial('reports/_head', ['kind' => $kind, 'groups' => $groups, 'semesters' => $semesters, 'groupId' => $groupId, 'semesterId' => $semesterId, 'groupCode' => $groupCode, 'semesterTitle' => $semesterTitle, 'print' => $print]) ?>
<?php if ($data['students'] === []): ?>
    <?= $print ? '<p>' . e(__('reports.empty')) . '</p>' : '<div class="card"><div class="app-empty"><i class="bi bi-table" aria-hidden="true"></i><p class="mb-0">' . e(__('reports.empty')) . '</p></div></div>' ?>
<?php else: ?>
    <?php if (!$print): ?><p class="small text-muted"><?= e(__('reports.performance_note')) ?></p><div class="card"><div class="table-responsive"><?php endif; ?>
    <table class="<?= $print ? '' : 'table table-sm table-bordered align-middle mb-0 app-table' ?>">
        <thead class="<?= $print ? '' : 'table-light' ?>">
        <tr>
            <th>№</th><th><?= e(__('journal.student')) ?></th>
            <?php foreach ($data['subjects'] as $s): ?><th class="text-center" title="<?= e($s['name']) ?>"><?= e($s['code']) ?><?= $s['status'] === 'approved' ? ' ✓' : '' ?></th><?php endforeach; ?>
            <th class="text-center"><?= e(__('reports.debts')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['students'] as $i => $st): ?>
            <tr>
                <td><?= e($i + 1) ?></td>
                <td class="text-nowrap"><?= e($st['name']) ?></td>
                <?php foreach ($data['subjects'] as $s): $c = $st['cells'][$s['id']] ?? null; ?>
                    <td class="text-center<?= ($c['passed'] ?? null) === false ? ' text-danger fw-bold' : '' ?>"><?= e($c === null ? '' : $n($c['final'])) ?></td>
                <?php endforeach; ?>
                <td class="text-center<?= $st['debts'] > 0 ? ' text-danger fw-bold' : '' ?>"><?= e($st['debts']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$print): ?></div></div><?php endif; ?>
    <p class="small text-muted mt-2"><?php foreach ($data['subjects'] as $s): ?><?= e($s['code'] . ' — ' . $s['name']) ?>; <?php endforeach; ?></p>
<?php endif; ?>
