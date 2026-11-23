<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $journal */
/** @var array{lessons: list<array<string, mixed>>, students: list<array<string, mixed>>, scheme: ?array, components: list<array<string, mixed>>} $grid */
/** @var bool $canEdit */
/** @var bool $canSubmit */
/** @var bool $canApprove */
/** @var bool $canAdminister */
/** @var list<array<string, mixed>> $periods */
/** @var array<int, string> $teachers */
/** @var array<int, string> $schemes */
use App\Services\JournalService;

$id = (int) $journal['id'];
$scheme = $grid['scheme'];
$n = static fn ($v): string => JournalService::normalize($v) ?? '';
$statusClass = ['open' => 'app-badge-primary', 'submitted' => 'app-badge-warning', 'approved' => 'app-badge-success'];
$subject = localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]);
$statusForm = static fn (string $action, string $label, string $class, ?string $confirm = null): string => '<form method="post" action="' . e(url('/journal/' . $id . '/status')) . '"'
    . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">'
    . '<button type="submit" class="btn ' . e($class) . '">' . e($label) . '</button></form>';
$actions = '<div class="dropdown"><button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots me-1" aria-hidden="true"></i>' . e(__('common.actions')) . '</button><ul class="dropdown-menu dropdown-menu-end">'
    . '<li><a class="dropdown-item" href="' . e(url('/journal/' . $id . '/history')) . '"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>' . e(__('journal.history')) . '</a></li>'
    . '<li><a class="dropdown-item" href="' . e(url('/journal/' . $id . '/export')) . '"><i class="bi bi-filetype-csv me-2" aria-hidden="true"></i>' . e(__('journal.export_csv')) . '</a></li>'
    . '<li><a class="dropdown-item" href="' . e(url('/journal/' . $id . '/print')) . '" target="_blank" rel="noopener"><i class="bi bi-printer me-2" aria-hidden="true"></i>' . e(__('calendar.pdf')) . '</a></li>'
    . ($canAdminister ? '<li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="bi bi-gear me-2" aria-hidden="true"></i>' . e(__('journal.settings')) . '</button></li>' : '')
    . '</ul></div>';
if ($canSubmit) {
    $actions .= $statusForm('submit', __('journal.submit'), 'btn-primary', __('journal.submit_confirm'));
}
if ($canApprove && $journal['status'] !== 'approved') {
    $actions .= $statusForm('approve', __('journal.approve'), 'btn-success', __('journal.approve_confirm'));
}
if ($canApprove && $journal['status'] !== 'open') {
    $actions .= '<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reopenModal">' . e(__('journal.reopen')) . '</button>';
}
$hasErrors = errors() !== [];
$openForm = (string) old('_form', '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => $subject,
    'subtitle' => $journal['group_code'] . ' · ' . str_replace('-', '–', (string) $journal['year_title']) . ', ' . __('dashboard.semester', ['number' => $journal['semester_number']])
        . ' · ' . full_name(['last_name' => $journal['teacher_last_name'], 'first_name' => $journal['teacher_first_name'], 'middle_name' => $journal['teacher_middle_name']], true),
    'back' => '/journal',
    'actions' => $actions,
]) ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3 small">
    <span class="badge <?= e($statusClass[$journal['status']] ?? '') ?>"><?= e(__('journal.statuses.' . $journal['status'])) ?></span>
    <?php if ($scheme !== null): ?>
        <span class="text-muted"><?= e(__('journal.scheme')) ?>: <strong><?= e($scheme['title'] . ' v' . $scheme['version']) ?></strong> · <?= e(__('grading.scale')) ?> <?= e($n($scheme['grade_min'])) ?>–<?= e($n($scheme['grade_max'])) ?>
            <?php if ($scheme['pass_threshold'] !== null): ?> · <?= e(__('grading.threshold')) ?> <?= e($n($scheme['pass_threshold'])) ?><?php endif; ?></span>
    <?php else: ?>
        <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e(__('journal.no_scheme')) ?></span>
    <?php endif; ?>
    <?php if (!$canEdit): ?><span class="text-muted"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('journal.readonly')) ?></span><?php endif; ?>
</div>

<?php if ($hasErrors && !in_array($openForm, ['lesson', 'settings', 'reopen'], true)): ?>
    <div class="alert alert-danger small"><?php foreach (errors() as $m): ?><div><?= e($m[0]) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-primary" data-lesson-new><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('journal.add_lesson')) ?></button>
        <form method="post" action="<?= e(url('/journal/' . $id . '/fill')) ?>" data-confirm="<?= e(__('journal.fill_confirm')) ?>">
            <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-light"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i><?= e(__('journal.fill')) ?></button>
        </form>
        <span class="small text-muted align-self-center ms-md-2"><i class="bi bi-keyboard me-1" aria-hidden="true"></i><?= e(__('journal.input_hint')) ?></span>
    </div>
<?php endif; ?>

<?php if ($grid['students'] === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-people" aria-hidden="true"></i><p class="mb-0"><?= e(__('journal.no_students')) ?></p></div></div>
<?php else: ?>
<div class="card">
    <div class="app-journal-wrap">
        <table class="table table-bordered mb-0 app-journal" id="journalGrid" data-journal="<?= e($id) ?>" data-editable="<?= $canEdit ? '1' : '0' ?>" data-approved="<?= $journal['status'] === 'approved' ? '1' : '0' ?>">
            <thead>
            <tr>
                <th scope="col" class="app-journal__name"><?= e(__('users.name')) ?></th>
                <?php foreach ($grid['lessons'] as $lesson): ?>
                    <th scope="col" class="app-journal__date" title="<?= e(($lesson['topic'] ?? '') . ($lesson['homework'] ? ' · ' . __('journal.homework') . ': ' . $lesson['homework'] : '')) ?>">
                        <?php if ($canEdit): ?>
                            <button type="button" class="app-journal__lesson-btn" data-lesson-edit="<?= e(json_encode([
                                'lesson_id' => (int) $lesson['id'], 'lesson_date' => $lesson['lesson_date'], 'lesson_period_id' => (int) $lesson['lesson_period_id'],
                                'lesson_type' => $lesson['lesson_type'], 'topic' => $lesson['topic'], 'homework' => $lesson['homework'], 'note' => $lesson['note'],
                                'hours' => (int) $lesson['hours'], 'conducted_by' => $lesson['conducted_by'], 'delete_url' => url('/journal/' . $id . '/lessons/' . $lesson['id']),
                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                        <?php endif; ?>
                        <span class="d-block"><?= e(format_date($lesson['lesson_date'], 'd.m')) ?></span>
                        <span class="d-block small text-muted fw-normal"><?= e($lesson['period_number']) ?> <?= e(__('schedule.types_short.' . $lesson['lesson_type'])) ?><?= $lesson['topic'] ? ' <i class="bi bi-chat-left-text"></i>' : '' ?></span>
                        <?php if ($canEdit): ?></button><?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <?php foreach ($grid['components'] as $c): ?>
                    <th scope="col" class="app-journal__comp" title="<?= e(localized($c) . ' · ' . $n($c['weight']) . '%' . (in_array((int) $c['assessment_type_id'], $grid['exam_types'], true) ? ' · ' . __('journal.via_statement') : '')) ?>"><?= e(localized($c, 'short')) ?><?php if (in_array((int) $c['assessment_type_id'], $grid['exam_types'], true)): ?> <i class="bi bi-file-earmark-check text-primary" aria-hidden="true"></i><?php endif; ?><span class="d-block small text-muted fw-normal"><?= e($n($c['weight'])) ?>%</span></th>
                <?php endforeach; ?>
                <th scope="col" class="app-journal__comp app-journal__final"><?= e(__('journal.final')) ?></th>
                <th scope="col" class="app-journal__comp" title="<?= e(__('journal.absences_hint')) ?>"><i class="bi bi-person-x" aria-hidden="true"></i><span class="visually-hidden"><?= e(__('journal.absences')) ?></span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($grid['students'] as $i => $s): ?>
                <?php $r = $s['result']; $sid = (int) $s['id']; $left = $s['member_to'] !== null; ?>
                <tr data-student="<?= e($sid) ?>"<?= $left ? ' class="is-left"' : '' ?>>
                    <th scope="row" class="app-journal__name">
                        <span class="text-muted small me-1"><?= e($i + 1) ?>.</span><?= e(full_name($s, true)) ?>
                        <?php if ($left): ?><span class="badge app-badge-muted ms-1" title="<?= e(__('journal.left_hint', ['date' => format_date($s['member_to'])])) ?>"><?= e(__('journal.left')) ?></span><?php endif; ?>
                    </th>
                    <?php foreach ($grid['lessons'] as $lesson): ?>
                        <?php
                        $cell = $s['cells'][(int) $lesson['id']];
                        $display = $cell['attendance'] === 'absent' ? 'н' : ($cell['attendance'] === 'excused' ? 'у' : $n($cell['grade']));
                        $cls = $cell['attendance'] !== null ? ' is-absent' : '';
                        $cls .= $cell['comment'] !== null ? ' has-comment' : '';
                        ?>
                        <td class="app-journal__cell<?= e($cls) ?><?= $cell['member'] ? '' : ' is-outside' ?>">
                            <input type="text" class="app-cell" value="<?= e($display) ?>" maxlength="6" inputmode="decimal" autocomplete="off"
                                   data-kind="lesson" data-lesson="<?= e($lesson['id']) ?>" data-comment="<?= e($cell['comment'] ?? '') ?>"
                                   aria-label="<?= e(full_name($s, true) . ', ' . format_date($lesson['lesson_date'])) ?>"
                                   title="<?= e($cell['comment'] ?? '') ?>"<?= $canEdit && $cell['member'] ? '' : ' readonly tabindex="-1"' ?>>
                        </td>
                    <?php endforeach; ?>
                    <?php foreach ($grid['components'] as $c): ?>
                        <?php $tid = (int) $c['assessment_type_id']; $value = $r['components'][$tid] ?? null; ?>
                        <td class="app-journal__cell app-journal__comp">
                            <?php if ($c['source'] === 'manual' && !in_array($tid, $grid['exam_types'], true)): ?>
                                <input type="text" class="app-cell app-cell--comp" value="<?= e($n($value)) ?>" maxlength="6" inputmode="decimal" autocomplete="off"
                                       data-kind="assessment" data-type="<?= e($tid) ?>" data-result-type="<?= e($tid) ?>"
                                       aria-label="<?= e(full_name($s, true) . ', ' . localized($c)) ?>"<?= $canEdit ? '' : ' readonly tabindex="-1"' ?>>
                            <?php else: ?>
                                <span class="app-cell-value" data-result-type="<?= e($tid) ?>"><?= e($n($value)) ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <?php
                    $final = $journal['status'] === 'approved' && $r['fixed_final'] !== null ? $r['fixed_final'] : $r['final'];
                    $finalClass = $r['passed'] === false ? ' is-fail' : ($r['passed'] === true ? ' is-pass' : '');
                    ?>
                    <td class="app-journal__comp app-journal__final<?= e($finalClass) ?>" data-result="final"
                        title="<?= e($final === null && $r['missing'] !== [] ? __('journal.final_incomplete') : '') ?>"><?= $n($final) !== '' ? e($n($final)) : '—' ?></td>
                    <td class="app-journal__comp small text-muted" data-result="absences"><?= e($r['absent'] + $r['excused'] > 0 ? $r['absent'] . '/' . $r['excused'] : '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex flex-wrap gap-3 small text-muted mt-2">
    <span><?= e(__('journal.legend_absent')) ?></span><span><?= e(__('journal.legend_excused')) ?></span>
    <span><span class="app-legend-comment"></span><?= e(__('journal.legend_comment')) ?></span>
    <span><?= e(__('journal.legend_final')) ?></span>
</div>
<?php endif; ?>

<?= $this->partial('journals/_modals', [
    'journal' => $journal, 'periods' => $periods, 'teachers' => $teachers, 'schemes' => $schemes, 'canEdit' => $canEdit,
    'canApprove' => $canApprove, 'canAdminister' => $canAdminister, 'openForm' => $hasErrors ? $openForm : '',
]) ?>

<?php $this->start('scripts'); ?>
<script src="<?= e(asset('assets/js/journal.js')) ?>"></script>
<?php $this->stop(); ?>
