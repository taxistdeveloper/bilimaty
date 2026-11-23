<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $data */
/** @var list<array<string, mixed>> $issued */
/** @var bool $canIssue */
/** @var bool $own */
$h = $data['header'];
$actions = $canIssue ? '<form method="post" action="' . e(url('/students/' . $h['student_id'] . '/transcript/issue')) . '" data-confirm="' . e(__('transcripts.issue_confirm')) . '">' . csrf_field()
    . '<button type="submit" class="btn btn-primary"' . ($data['items'] === [] ? ' disabled' : '') . '><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>' . e(__('transcripts.issue')) . '</button></form>' : '';
?>
<?= $this->partial('partials/page-header', [
    'heading' => $own ? __('transcripts.my_title') : __('transcripts.title'),
    'subtitle' => $h['full_name'] . ' · ' . $h['student_number'],
    'back' => $own ? null : '/students/' . $h['student_id'],
    'actions' => $actions,
]) ?>

<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e(__('transcripts.preview_note')) ?></div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100"><div class="card-body row g-2 small">
            <div class="col-md-6"><span class="text-muted"><?= e(__('fields.specialty_id')) ?>:</span> <?= e(locale() === 'kk' ? ($h['specialty_kk'] ?? '—') : ($h['specialty'] ?? '—')) ?></div>
            <div class="col-md-6"><span class="text-muted"><?= e(__('fields.qualification_id')) ?>:</span> <?= e(locale() === 'kk' ? ($h['qualification_kk'] ?? '—') : ($h['qualification'] ?? '—')) ?></div>
            <div class="col-md-6"><span class="text-muted"><?= e(__('fields.group_id')) ?>:</span> <?= e($h['group'] ?? '—') ?><?= $h['course'] ? ', ' . e(__('groups.course_n', ['n' => $h['course']])) : '' ?></div>
            <div class="col-md-6"><span class="text-muted"><?= e(__('common.status')) ?>:</span> <?= e(__('students.statuses.' . $h['status'])) ?></div>
            <div class="col-md-6"><span class="text-muted"><?= e(__('transcripts.total_hours')) ?>:</span> <?= e($data['totals']['hours']) ?></div>
            <div class="col-md-6"><span class="text-muted">GPA:</span> <?= e($data['gpa'] !== null ? number_format((float) $data['gpa'], 2) : __('transcripts.gpa_off')) ?></div>
            <?php if ($data['pending'] > 0): ?><div class="col-12 text-warning-emphasis"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= e(__('transcripts.pending', ['count' => $data['pending']])) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= e(__('transcripts.issued_list')) ?></div>
            <?php if ($issued === []): ?><div class="card-body small text-muted"><?= e(__('transcripts.none_issued')) ?></div><?php endif; ?>
            <div class="list-group list-group-flush">
                <?php foreach ($issued as $t): ?>
                    <a href="<?= e(url('/transcripts/' . $t['id'])) ?>" target="_blank" rel="noopener" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small">
                        <span><strong><?= e($t['number']) ?></strong> · <?= e(format_date($t['issued_at'])) ?></span>
                        <span class="badge <?= $t['status'] === 'issued' ? 'app-badge-success' : 'app-badge-danger' ?>"><?= e(__('transcripts.statuses.' . $t['status'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($data['items'] === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-journal-x" aria-hidden="true"></i><p class="mb-0"><?= e(__('transcripts.no_items')) ?></p></div></div>
<?php else: ?>
    <?= $this->partial('transcripts/_table', ['items' => $data['items'], 'showLetter' => $data['gpa'] !== null, 'tableClass' => 'app']) ?>
    <p class="small text-muted"><?= e(__('transcripts.hours_note')) ?></p>
<?php endif; ?>

<?php if ($h['history'] !== []): ?>
    <div class="card mt-3">
        <div class="card-header fw-semibold"><?= e(__('transcripts.history')) ?></div>
        <ul class="list-group list-group-flush small">
            <?php foreach ($h['history'] as $row): ?>
                <li class="list-group-item"><?= e(format_date($row['from'])) ?> — <?= e($row['to'] !== null ? format_date($row['to']) : __('students.now')) ?> · <strong><?= e($row['group']) ?></strong> · <?= e(__('students.reasons.' . $row['reason'])) ?><?= $row['end_reason'] ? ' → ' . e(__('students.end_reasons.' . $row['end_reason'])) : '' ?><?= $row['order'] ? ' · № ' . e($row['order']) : '' ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
