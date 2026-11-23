<?php
/**
 * Modals of the timetable page.
 *   #entryModal       create / edit a regular lesson (JSON API via Fetch)
 *   #occurrenceModal  lesson details; for managers: change this one occurrence
 *
 * @var App\Core\View $this
 * @var array<string, mixed>|null $semester
 * @var string $mode
 * @var int|null $entityId
 * @var array<string, array<int, string>> $options
 * @var list<array<string, mixed>> $periods
 * @var list<int> $weekdays
 * @var bool $canManage
 */
$select = static function (string $name, string $label, array $items, bool $required, string $empty = '—'): string {
    $html = '<label class="form-label fw-semibold small" for="se_' . e($name) . '">' . e($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . '<select class="form-select" id="se_' . e($name) . '" name="' . e($name) . '"' . ($required ? ' required' : '') . '>'
        . '<option value="">' . e($empty) . '</option>';
    foreach ($items as $value => $text) {
        $html .= '<option value="' . e($value) . '">' . e($text) . '</option>';
    }

    return $html . '</select><div class="invalid-feedback" data-error-for="' . e($name) . '"></div>';
};
$periodOptions = [];
foreach ($periods as $p) {
    $periodOptions[(int) $p['id']] = $p['number'] . ' (' . substr((string) $p['start_time'], 0, 5) . '–' . substr((string) $p['end_time'], 0, 5) . ')';
}
$weekdayOptions = [];
foreach ($weekdays as $wd) {
    $weekdayOptions[$wd] = __('schedule.weekdays_full.' . $wd);
}
$parities = [];
foreach (App\Services\ScheduleService::PARITIES as $p) {
    $parities[$p] = __('schedule.parities.' . $p);
}
$types = [];
foreach (App\Services\ScheduleService::TYPES as $t) {
    $types[$t] = __('schedule.types.' . $t);
}
?>
<?php if ($canManage && $semester !== null): ?>
<div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryModalTitle" aria-hidden="true"
     data-semester="<?= e($semester['id']) ?>" data-mode="<?= e($mode) ?>" data-entity="<?= e($entityId ?? '') ?>">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" novalidate id="entryForm">
            <div class="modal-header">
                <h2 class="modal-title h5" id="entryModalTitle" data-title-new="<?= e(__('schedule.add')) ?>" data-title-edit="<?= e(__('schedule.edit')) ?>"><?= e(__('schedule.add')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" role="alert" data-conflicts>
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= e(__('schedule.conflicts_found')) ?></div>
                    <ul class="mb-0 small"></ul>
                </div>
                <div class="row g-3">
                    <div class="col-md-8"><?= $select('group_id', __('fields.group_id'), $options['groups'], true, __('common.choose')) ?></div>
                    <div class="col-md-4"><?= $select('subgroup', __('schedule.subgroup'), [1 => '1', 2 => '2', 3 => '3', 4 => '4'], false, __('schedule.whole_group')) ?></div>
                    <div class="col-12"><?= $select('subject_id', __('curricula.subject'), $options['subjects'], true, __('common.choose')) ?></div>
                    <div class="col-md-6"><?= $select('teacher_id', __('teachers.one'), $options['teachers'], true, __('common.choose')) ?></div>
                    <div class="col-md-6"><?= $select('classroom_id', __('schedule.classroom'), $options['classrooms'], false) ?></div>
                    <div class="col-md-4"><?= $select('weekday', __('schedule.weekday'), $weekdayOptions, true, __('common.choose')) ?></div>
                    <div class="col-md-4"><?= $select('lesson_period_id', __('schedule.period'), $periodOptions, true, __('common.choose')) ?></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="se_week_parity"><?= e(__('schedule.parity')) ?></label>
                        <select class="form-select" id="se_week_parity" name="week_parity">
                            <?php foreach ($parities as $v => $l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" data-error-for="week_parity"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small" for="se_lesson_type"><?= e(__('schedule.lesson_type')) ?></label>
                        <select class="form-select" id="se_lesson_type" name="lesson_type">
                            <?php foreach ($types as $v => $l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" data-error-for="lesson_type"></div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small" for="se_note"><?= e(__('fields.note')) ?></label>
                        <input type="text" class="form-control" id="se_note" name="note" maxlength="255">
                        <div class="invalid-feedback" data-error-for="note"></div>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('schedule.parity_hint')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto d-none" data-entry-delete
                        data-confirm-text="<?= e(__('schedule.delete_confirm')) ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="occurrenceModal" tabindex="-1" aria-labelledby="occurrenceTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-0" id="occurrenceTitle" data-o="subject"></h2>
                    <div class="small text-muted"><span data-o="date"></span> · <span data-o="time"></span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <dl class="row small mb-0">
                    <dt class="col-4 text-muted fw-normal"><?= e(__('fields.group_id')) ?></dt><dd class="col-8" data-o="group"></dd>
                    <dt class="col-4 text-muted fw-normal"><?= e(__('teachers.one')) ?></dt><dd class="col-8" data-o="teacher"></dd>
                    <dt class="col-4 text-muted fw-normal"><?= e(__('schedule.classroom')) ?></dt><dd class="col-8" data-o="classroom"></dd>
                    <dt class="col-4 text-muted fw-normal"><?= e(__('schedule.lesson_type')) ?></dt><dd class="col-8" data-o="type"></dd>
                    <dt class="col-4 text-muted fw-normal"><?= e(__('common.status')) ?></dt><dd class="col-8"><span data-o="status_label"></span> <span class="text-muted" data-o="reason"></span></dd>
                </dl>

                <?php if ($canManage): ?>
                    <hr>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-light" data-entry-edit><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__('schedule.edit_regular')) ?></button>
                        <form method="post" class="d-none" data-change-revert data-confirm="<?= e(__('schedule.revert_confirm')) ?>">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= e(__('schedule.revert')) ?></button>
                        </form>
                    </div>
                    <form method="post" novalidate data-change-form data-action-template="<?= e(url('/schedule/entries/__ID__/changes')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="lesson_date" value="">
                        <h3 class="h6 fw-bold"><?= e(__('schedule.change_this')) ?></h3>
                        <div class="btn-group w-100 mb-3" role="group">
                            <?php foreach (['modify', 'cancel', 'reschedule'] as $i => $t): ?>
                                <input type="radio" class="btn-check" name="change_type" id="ct_<?= e($t) ?>" value="<?= e($t) ?>"<?= $i === 0 ? ' checked' : '' ?> data-change-type>
                                <label class="btn btn-outline-primary btn-sm" for="ct_<?= e($t) ?>"><?= e(__('schedule.change_types.' . $t)) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="row g-2" data-change-fields="modify reschedule">
                            <div class="col-md-6">
                                <label class="form-label small" for="ch_teacher"><?= e(__('schedule.new_teacher')) ?></label>
                                <select class="form-select form-select-sm" id="ch_teacher" name="new_teacher_id">
                                    <option value=""><?= e(__('schedule.keep')) ?></option>
                                    <?php foreach ($options['teachers'] as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="ch_classroom"><?= e(__('schedule.new_classroom')) ?></label>
                                <select class="form-select form-select-sm" id="ch_classroom" name="new_classroom_id">
                                    <option value=""><?= e(__('schedule.keep')) ?></option>
                                    <?php foreach ($options['classrooms'] as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mt-0 d-none" data-change-fields="reschedule">
                            <div class="col-md-6">
                                <label class="form-label small" for="ch_date"><?= e(__('schedule.new_date')) ?></label>
                                <input type="date" class="form-control form-control-sm" id="ch_date" name="new_date" min="<?= e($semester['start_date'] ?? '') ?>" max="<?= e($semester['end_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="ch_period"><?= e(__('schedule.new_period')) ?></label>
                                <select class="form-select form-select-sm" id="ch_period" name="new_lesson_period_id">
                                    <option value=""><?= e(__('common.choose')) ?></option>
                                    <?php foreach ($periodOptions as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small" for="ch_reason"><?= e(__('schedule.reason')) ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="ch_reason" name="reason" maxlength="255" required placeholder="<?= e(__('schedule.reason_placeholder')) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mt-3"><?= e(__('common.save')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="scheduleI18n"><?= json_encode([
    'saved' => __('schedule.saved'),
    'deleted' => __('schedule.deleted'),
    'revertAction' => url('/schedule/changes/__ID__'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
