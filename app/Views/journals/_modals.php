<?php
/**
 * @var App\Core\View $this
 * @var array<string, mixed> $journal
 * @var list<array<string, mixed>> $periods
 * @var array<int, string> $teachers
 * @var array<int, string> $schemes
 * @var bool $canEdit
 * @var bool $canApprove
 * @var bool $canAdminister
 * @var string $openForm form that failed validation (reopen its modal)
 */
$id = (int) $journal['id'];
$periodOptions = [];
foreach ($periods as $p) {
    $periodOptions[(int) $p['id']] = $p['number'] . ' (' . substr((string) $p['start_time'], 0, 5) . ')';
}
$types = [];
foreach (App\Services\ScheduleService::TYPES as $t) {
    $types[$t] = __('schedule.types.' . $t);
}
?>
<?php if ($canEdit): ?>
<div class="modal fade" id="lessonModal" tabindex="-1" aria-labelledby="lessonTitle" aria-hidden="true"<?= $openForm === 'lesson' ? ' data-show-on-load' : '' ?>>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="post" action="<?= e(url('/journal/' . $id . '/lessons')) ?>" novalidate id="lessonForm">
                <?= csrf_field() ?>
                <input type="hidden" name="_form" value="lesson">
                <input type="hidden" name="lesson_id" value="<?= e($openForm === 'lesson' ? old('lesson_id') : '') ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="lessonTitle" data-title-new="<?= e(__('journal.add_lesson')) ?>" data-title-edit="<?= e(__('journal.edit_lesson')) ?>"><?= e(__('journal.add_lesson')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
                </div>
                <div class="modal-body row g-2">
                    <?php $own = $openForm === 'lesson'; ?>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'lesson_date', 'label' => __('fields.date'), 'type' => 'date', 'required' => true, 'value' => date('Y-m-d'), 'attrs' => ['min' => $journal['semester_start'], 'max' => $journal['semester_end']]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'lesson_period_id', 'label' => __('schedule.period'), 'type' => 'select', 'required' => true, 'options' => $periodOptions]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'lesson_type', 'label' => __('schedule.lesson_type'), 'type' => 'select', 'required' => true, 'options' => $types]) ?></div>
                    <div class="col-12"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'topic', 'label' => __('journal.topic'), 'attrs' => ['maxlength' => 255]]) ?></div>
                    <div class="col-12"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'homework', 'label' => __('journal.homework'), 'attrs' => ['maxlength' => 500]]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'conducted_by', 'label' => __('journal.conducted_by'), 'type' => 'select', 'value' => $journal['teacher_id'], 'options' => $teachers, 'help' => __('journal.conducted_hint')]) ?></div>
                    <div class="col-md-2"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'hours', 'label' => __('journal.hours'), 'type' => 'number', 'required' => true, 'value' => 2, 'attrs' => ['min' => 1, 'max' => 8]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['idPrefix' => 'l_', 'ownsInput' => $own, 'name' => 'note', 'label' => __('fields.note'), 'attrs' => ['maxlength' => 500]]) ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto d-none" data-lesson-delete
                            data-confirm-text="<?= e(__('journal.lesson_delete_confirm')) ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                </div>
            </form>
            <form method="post" class="d-none" id="lessonDeleteForm"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
        </div>
    </div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="commentForm">
            <div class="modal-header">
                <h2 class="modal-title h5" id="commentTitle"><?= e(__('journal.comment')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small text-muted" for="commentText" data-comment-target></label>
                <textarea class="form-control" id="commentText" rows="3" maxlength="255"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canAdminister): ?>
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsTitle" aria-hidden="true"<?= $openForm === 'settings' ? ' data-show-on-load' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/journal/' . $id . '/settings')) ?>" novalidate>
            <?= csrf_field() ?><?= method_field('PUT') ?><input type="hidden" name="_form" value="settings">
            <div class="modal-header"><h2 class="modal-title h5" id="settingsTitle"><?= e(__('journal.settings')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
            <div class="modal-body">
                <?php $own = $openForm === 'settings'; ?>
                <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $own, 'name' => 'teacher_id', 'label' => __('journal.responsible'), 'type' => 'select', 'required' => true, 'value' => $journal['teacher_id'], 'options' => $teachers]) ?>
                <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $own, 'name' => 'grading_scheme_id', 'label' => __('journal.scheme'), 'type' => 'select', 'value' => $journal['grading_scheme_id'] ?? '',
                    'options' => ['' => '—'] + $schemes + ($journal['grading_scheme_id'] !== null && !isset($schemes[(int) $journal['grading_scheme_id']]) ? [(int) $journal['grading_scheme_id'] => $journal['scheme_title'] . ' v' . $journal['scheme_version']] : []),
                    'help' => __('journal.scheme_change_hint')]) ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canApprove && $journal['status'] !== 'open'): ?>
<div class="modal fade" id="reopenModal" tabindex="-1" aria-labelledby="reopenTitle" aria-hidden="true"<?= $openForm === 'reopen' ? ' data-show-on-load' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/journal/' . $id . '/status')) ?>" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="_form" value="reopen">
            <div class="modal-header"><h2 class="modal-title h5" id="reopenTitle"><?= e(__('journal.reopen')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
            <div class="modal-body">
                <p class="small text-muted"><?= e(__('journal.reopen_hint')) ?></p>
                <?= $this->partial('partials/field', ['idPrefix' => 'r_', 'ownsInput' => $openForm === 'reopen', 'name' => 'reason', 'label' => __('schedule.reason'), 'required' => true, 'attrs' => ['maxlength' => 255]]) ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('journal.reopen')) ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script type="application/json" id="journalI18n"><?= json_encode([
    'saved' => __('journal.saved'),
    'comment_for' => __('journal.comment_for'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
