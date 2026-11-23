<?php
/**
 * Practice card: details, hours counter, the schedule (editable by its supervisor while it is a draft
 * or returned), approval by the academic office.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $assignment
 * @var list<array<string, mixed>> $entries
 * @var array{limit: int, counted: float, excluded: float, remaining: float, over: float, percent: int, rows: list<array<string, mixed>>} $summary
 * @var bool $editable
 * @var bool $own
 * @var bool $canManage
 * @var list<string> $holidays
 */
use App\Services\PracticeService;

$a = $assignment;
$teacher = full_name(['last_name' => $a['teacher_last_name'], 'first_name' => $a['teacher_first_name'], 'middle_name' => $a['teacher_middle_name']]);
$fmt = static fn (float $h): string => PracticeService::fmt($h);

// Rows of the form: the old input after a failed save, else the stored rows, else empty rows.
$rows = [];
$old = old('entries', null);
if (is_array($old)) {
    foreach ($old as $r) {
        $rows[] = ['topic' => (string) ($r['topic'] ?? ''), 'date' => (string) ($r['date'] ?? ''), 'time_start' => (string) ($r['time_start'] ?? ''),
            'time_end' => (string) ($r['time_end'] ?? ''), 'place' => (string) ($r['place'] ?? ''), 'is_distance' => !empty($r['is_distance'])];
    }
} else {
    foreach ($entries as $e) {
        $rows[] = ['topic' => (string) $e['topic'], 'date' => (string) $e['entry_date'], 'time_start' => substr((string) $e['time_start'], 0, 5),
            'time_end' => substr((string) $e['time_end'], 0, 5), 'place' => (string) $e['place'], 'is_distance' => (bool) $e['is_distance']];
    }
}
if ($editable && $rows === []) {
    for ($i = 0; $i < 5; $i++) {
        $rows[] = ['topic' => $i === 0 ? (string) ($a['module_name'] ?? '') : '', 'date' => '', 'time_start' => '', 'time_end' => '', 'place' => '', 'is_distance' => false];
    }
}

$actions = '<a href="' . e(url('/practice/' . $a['id'] . '/print')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('practice.print')) . '</a>';
if ($canManage && $a['status'] !== 'approved') {
    $actions .= '<a href="' . e(url('/practice/' . $a['id'] . '/edit')) . '" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>';
}
?>
<?= $this->partial('partials/page-header', ['heading' => $a['title'], 'subtitle' => __('practice.types.' . $a['practice_type']) . ' · ' . $a['group_code'], 'back' => '/practice', 'actions' => $actions]) ?>

<?php if ($a['status'] === 'returned' && $a['return_reason']): ?>
    <div class="alert alert-danger d-flex gap-2" role="status"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        <div><strong><?= e(__('practice.return_title')) ?>:</strong> <?= e($a['return_reason']) ?></div></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('common.status')) ?></dt><dd class="col-sm-8"><?= $this->partial('practice/_status', ['assignment' => $a]) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.f_group')) ?></dt><dd class="col-sm-8"><?= e($a['group_code']) ?> · <?= e(__('fields.course')) ?>: <?= e($a['course']) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.f_teacher')) ?></dt><dd class="col-sm-8"><?= e($teacher) ?></dd>
                    <?php if ($a['module_name']): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.f_module')) ?></dt><dd class="col-sm-8"><?= e($a['module_name']) ?></dd><?php endif; ?>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.f_deadline')) ?></dt>
                    <dd class="col-sm-8<?= PracticeService::isOverdue($a) ? ' text-danger fw-semibold' : '' ?>"><?= e(format_date($a['deadline'])) ?></dd>
                    <?php if ($a['first_date'] !== null): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.period')) ?></dt><dd class="col-sm-8"><?= e(format_date($a['first_date'])) ?> – <?= e(format_date($a['last_date'])) ?></dd><?php endif; ?>
                    <?php if ($a['submitted_at']): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.submitted_at')) ?></dt><dd class="col-sm-8"><?= e(format_date($a['submitted_at'], 'd.m.Y H:i')) ?></dd><?php endif; ?>
                    <?php if ($a['teacher_comment']): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('practice.teacher_comment')) ?></dt><dd class="col-sm-8 fst-italic"><?= e($a['teacher_comment']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100" data-practice-summary data-limit="<?= e($a['hours']) ?>">
            <div class="card-body">
                <div class="small text-muted"><?= e(__('practice.progress')) ?></div>
                <div class="fs-3 fw-bold"><span data-sum-counted><?= e($fmt($summary['counted'])) ?></span> <span class="fs-6 text-muted fw-normal">/ <?= e($a['hours']) ?> ч.</span></div>
                <div class="progress my-2" role="progressbar" aria-label="<?= e(__('practice.progress')) ?>" style="height: 8px">
                    <div class="progress-bar<?= $summary['over'] > 0 ? ' bg-danger' : ($summary['percent'] >= 100 ? ' bg-success' : '') ?>" data-sum-bar style="width: <?= e($summary['percent']) ?>%"></div>
                </div>
                <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('practice.remaining')) ?></span><span data-sum-remaining><?= e($fmt($summary['remaining'])) ?></span></div>
                <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('practice.over')) ?></span><span class="text-danger" data-sum-over><?= e($fmt($summary['over'])) ?></span></div>
                <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('practice.excluded')) ?></span><span data-sum-excluded><?= e($fmt($summary['excluded'])) ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage && in_array($a['status'], ['submitted', 'approved'], true)): ?>
    <div class="card mb-3 border-<?= $a['status'] === 'submitted' ? 'warning' : 'success' ?>">
        <div class="card-body d-flex flex-wrap gap-3 align-items-start">
            <?php if ($a['status'] === 'submitted'): ?>
                <form method="post" action="<?= e(url('/practice/' . $a['id'] . '/approve')) ?>"><?= csrf_field() ?>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= e(__('practice.approve')) ?></button></form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/practice/' . $a['id'] . '/return')) ?>" class="d-flex flex-wrap gap-2 flex-grow-1"><?= csrf_field() ?>
                <label for="reason" class="visually-hidden"><?= e(__('practice.return_reason')) ?></label>
                <input type="text" id="reason" name="reason" class="form-control flex-grow-1<?= error('reason') ? ' is-invalid' : '' ?>" style="min-width: 240px; max-width: 520px"
                       placeholder="<?= e(__('practice.return_reason')) ?>" maxlength="1000" value="<?= e(old('reason', '')) ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= e(__('practice.return')) ?></button>
                <?php if (error('reason')): ?><div class="invalid-feedback d-block w-100"><?= e(error('reason')) ?></div><?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><?= e(__('practice.schedule')) ?></span>
    </div>
    <?php if (errors()['entries'] ?? null): ?>
        <div class="alert alert-danger m-3 mb-0" role="alert"><ul class="mb-0 ps-3"><?php foreach (errors()['entries'] as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($editable): ?>
        <form method="post" action="<?= e(url('/practice/' . $a['id'] . '/schedule')) ?>" data-practice-form data-holidays="<?= e(json_encode($holidays)) ?>"
              data-label-weekend="<?= e(__('practice.not_counted_weekend')) ?>" data-label-holiday="<?= e(__('practice.not_counted_holiday')) ?>" data-submit-confirm="<?= e(__('practice.submit_confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save" data-action>
            <p class="small text-muted px-3 pt-3 mb-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('practice.rules')) ?></p>
            <div class="table-responsive">
                <table class="table align-middle mb-0 app-table practice-table">
                    <thead class="table-light"><tr>
                        <th style="width: 2.5rem">№</th>
                        <th style="min-width: 220px"><?= e(__('practice.col_topic')) ?></th>
                        <th style="width: 150px"><?= e(__('practice.col_date')) ?></th>
                        <th style="width: 105px"><?= e(__('practice.col_start')) ?></th>
                        <th style="width: 105px"><?= e(__('practice.col_end')) ?></th>
                        <th style="width: 110px"><?= e(__('practice.col_hours')) ?></th>
                        <th style="min-width: 180px"><?= e(__('practice.col_place')) ?></th>
                        <th style="width: 56px" class="text-center"><?= e(__('practice.col_distance')) ?></th>
                        <th style="width: 44px"></th>
                    </tr></thead>
                    <tbody data-rows>
                    <?php foreach ($rows as $i => $r): ?>
                        <?= $this->partial('practice/_row', ['i' => $i, 'r' => $r]) ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <template data-row-template><?= $this->partial('practice/_row', ['i' => '__i__', 'r' => ['topic' => '', 'date' => '', 'time_start' => '', 'time_end' => '', 'place' => '', 'is_distance' => false]]) ?></template>
            <div class="card-body border-top">
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" data-add-row><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('practice.add_row')) ?></button>
                <div class="mb-3" style="max-width: 640px">
                    <label for="teacher_comment" class="form-label small fw-semibold"><?= e(__('practice.teacher_comment')) ?></label>
                    <textarea id="teacher_comment" name="teacher_comment" rows="2" class="form-control" maxlength="1000"><?= e(old('teacher_comment', (string) ($a['teacher_comment'] ?? ''))) ?></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline-primary" data-save><i class="bi bi-floppy me-1" aria-hidden="true"></i><?= e(__('practice.save_draft')) ?></button>
                    <button type="submit" class="btn btn-primary" data-submit><i class="bi bi-send me-1" aria-hidden="true"></i><?= e(__('practice.submit')) ?></button>
                </div>
            </div>
        </form>
    <?php elseif ($entries === []): ?>
        <div class="app-empty"><i class="bi bi-calendar2-week" aria-hidden="true"></i><p class="mb-0"><?= e(__('practice.statuses.assigned')) ?> · <?= e(__('practice.f_deadline')) ?>: <?= e(format_date($a['deadline'])) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th style="width: 2.5rem">№</th><th><?= e(__('practice.col_topic')) ?></th><th><?= e(__('practice.col_date')) ?></th><th><?= e(__('practice.time')) ?></th>
                    <th class="text-end"><?= e(__('practice.col_hours')) ?></th><th><?= e(__('practice.col_place')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $i => $e): $row = $summary['rows'][$i]; ?>
                    <tr>
                        <td class="text-muted"><?= e($i + 1) ?></td>
                        <td><?= e($e['topic']) ?></td>
                        <td class="text-nowrap"><?= e($e['entry_date'] ? format_date($e['entry_date']) : '') ?></td>
                        <td class="text-nowrap"><?= e(substr((string) $e['time_start'], 0, 5)) ?>–<?= e(substr((string) $e['time_end'], 0, 5)) ?></td>
                        <td class="text-end text-nowrap"><?= e($fmt($row['hours'])) ?><?php if ($row['reason']): ?><div class="small text-danger"><?= e(__('practice.not_counted_' . $row['reason'])) ?></div><?php endif; ?></td>
                        <td><?php if ($e['is_distance']): ?><span class="badge text-bg-info me-1"><?= e(__('practice.distance_mark')) ?></span><?php endif; ?><?= e($e['place']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($canManage && $a['status'] !== 'approved'): ?>
    <form method="post" action="<?= e(url('/practice/' . $a['id'])) ?>" class="mt-3" data-confirm="<?= e(__('practice.delete_confirm')) ?>" data-confirm-danger>
        <?= csrf_field() ?><?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
    </form>
<?php endif; ?>

<?php if ($editable): ?>
    <?php $this->start('scripts'); ?>
    <script src="<?= e(asset('assets/js/practice.js')) ?>"></script>
    <?php $this->stop(); ?>
<?php endif; ?>
