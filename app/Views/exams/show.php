<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $exam */
/** @var list<array<string, mixed>> $rows */
/** @var list<array<string, mixed>> $retakes */
/** @var array<string, mixed>|null $scheme */
/** @var bool $canGrade */
/** @var bool $canManage */
/** @var array<int, string> $teachers */
use App\Services\JournalService;

$id = (int) $exam['id'];
$closed = $exam['status'] === 'closed';
$editable = $canGrade && !$closed;
$n = static fn ($v): string => JournalService::normalize($v) ?? '';
$statusClass = ['planned' => 'app-badge-primary', 'open' => 'app-badge-warning', 'closed' => 'app-badge-success'];
$statusForm = static fn (string $action, string $label, string $class, ?string $confirm = null): string => '<form method="post" action="' . e(url('/exams/' . $id . '/status')) . '"'
    . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><button type="submit" class="btn ' . e($class) . '">' . e($label) . '</button></form>';
$actions = '<a href="' . e(url('/exams/' . $id . '/print')) . '" class="btn btn-light" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('calendar.pdf')) . '</a>';
if ($canManage && !$closed) {
    $actions .= '<a href="' . e(url('/exams/' . $id . '/edit')) . '" class="btn btn-light"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>';
}
if ($canManage && $exam['status'] === 'planned' && (int) $exam['graded_count'] === 0) {
    $actions .= $statusForm('delete', __('common.delete'), 'btn-outline-danger', __('exams.delete_confirm'));
}
if ($canGrade && !$closed) {
    $actions .= $statusForm('close', __('exams.close'), 'btn-success', __('exams.close_confirm'));
}
if ($canManage && $closed) {
    $actions .= '<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#examReopen">' . e(__('exams.reopen')) . '</button>';
}
$retakesByStudent = [];
foreach ($retakes as $r) {
    $retakesByStudent[(int) $r['student_id']][] = $r;
}
$openForm = (string) old('_form', '');
$hasErrors = errors() !== [];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('exams.statement') . ' ' . $exam['statement_number'],
    'subtitle' => localized(['name_ru' => $exam['subject_name_ru'], 'name_kk' => $exam['subject_name_kk']]) . ' · ' . $exam['group_code'] . ' · ' . localized(['name_ru' => $exam['type_name_ru'], 'name_kk' => $exam['type_name_kk']]),
    'back' => '/exams',
    'actions' => $actions,
]) ?>

<div class="card mb-3">
    <div class="card-body row g-3 small">
        <div class="col-6 col-md-2"><div class="text-muted"><?= e(__('common.status')) ?></div><span class="badge <?= e($statusClass[$exam['status']] ?? '') ?>"><?= e(__('exams.statuses.' . $exam['status'])) ?></span></div>
        <div class="col-6 col-md-2"><div class="text-muted"><?= e(__('exams.date')) ?></div><?= e(format_date($exam['exam_date'])) ?><?= $exam['start_time'] !== null ? ', ' . e(substr((string) $exam['start_time'], 0, 5)) : '' ?></div>
        <div class="col-6 col-md-2"><div class="text-muted"><?= e(__('exams.form')) ?></div><?= e(__('exams.forms.' . $exam['exam_form'])) ?></div>
        <div class="col-6 col-md-2"><div class="text-muted"><?= e(__('schedule.classroom')) ?></div><?= e($exam['classroom_number'] !== null ? trim(($exam['classroom_building'] ? $exam['classroom_building'] . '-' : '') . $exam['classroom_number']) : '—') ?></div>
        <div class="col-12 col-md-4"><div class="text-muted"><?= e(__('exams.examiner')) ?></div><?= e(full_name(['last_name' => $exam['examiner_last_name'], 'first_name' => $exam['examiner_first_name'], 'middle_name' => $exam['examiner_middle_name']])) ?></div>
        <?php if ($scheme !== null): ?><div class="col-12 text-muted"><?= e(__('grading.scale')) ?> <?= e($n($scheme['grade_min'])) ?>–<?= e($n($scheme['grade_max'])) ?><?= $scheme['pass_threshold'] !== null ? ' · ' . e(__('grading.threshold')) . ' ' . e($n($scheme['pass_threshold'])) : '' ?> · <?= e(__('exams.sync_hint')) ?></div><?php endif; ?>
    </div>
</div>

<?php if ($hasErrors && !in_array($openForm, ['retake', 'reopen'], true)): ?>
    <div class="alert alert-danger small"><?php foreach (errors() as $messages): foreach ($messages as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/exams/' . $id . '/statement')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col">№</th>
                    <th scope="col"><?= e(__('journal.student')) ?></th>
                    <th scope="col"><?= e(__('exams.admission')) ?></th>
                    <th scope="col"><?= e(__('exams.attendance')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('exams.value')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.note')) ?></th>
                    <?php if ($closed && $canManage): ?><th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <?php
                    $sid = (int) $r['id'];
                    $disabled = !$editable || !$r['member_on_exam'];
                    $low = $scheme !== null && $scheme['pass_threshold'] !== null && $r['value'] !== null && (float) $r['value'] < (float) $scheme['pass_threshold'];
                    $needsRetake = $closed && ($low || ($r['admission'] === 'admitted' && $r['attendance'] !== 'present' && $r['member_on_exam']));
                    ?>
                    <tr<?= $r['member_on_exam'] ? '' : ' class="text-muted"' ?>>
                        <td class="small text-muted"><?= e($i + 1) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e(full_name($r, true)) ?></div>
                            <div class="small text-muted"><?= e($r['student_number']) ?><?= $r['member_on_exam'] ? '' : ' · ' . e(__('exams.not_in_group')) ?></div>
                            <?php foreach ($retakesByStudent[$sid] ?? [] as $rt): ?>
                                <div class="small"><span class="badge app-badge-warning"><?= e(__('exams.retake_n', ['n' => $rt['attempt_no']])) ?></span> <?= e(format_date($rt['retake_date'])) ?> · <?= e(__('exams.retake_statuses.' . $rt['status'])) ?><?= $rt['status'] === 'completed' ? ': ' . e($n($rt['old_value']) ?: '—') . ' → ' . e($n($rt['new_value']) ?: __('exams.attendance_values.absent')) : '' ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="rows[<?= e($sid) ?>][admission]"<?= $disabled ? ' disabled' : '' ?> aria-label="<?= e(__('exams.admission')) ?>">
                                <option value="admitted"<?= $r['admission'] === 'admitted' ? ' selected' : '' ?>><?= e(__('exams.admission_values.admitted')) ?></option>
                                <option value="not_admitted"<?= $r['admission'] === 'not_admitted' ? ' selected' : '' ?>><?= e(__('exams.admission_values.not_admitted')) ?></option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="rows[<?= e($sid) ?>][attendance]"<?= $disabled ? ' disabled' : '' ?> aria-label="<?= e(__('exams.attendance')) ?>">
                                <option value="">—</option>
                                <?php foreach (['present', 'absent', 'excused'] as $a): ?><option value="<?= e($a) ?>"<?= $r['attendance'] === $a ? ' selected' : '' ?>><?= e(__('exams.attendance_values.' . $a)) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-center" style="width: 7rem">
                            <input type="text" class="form-control form-control-sm text-center fw-semibold<?= $low ? ' text-danger' : '' ?>" name="rows[<?= e($sid) ?>][value]" value="<?= e($n($r['value'])) ?>" inputmode="decimal" maxlength="6"<?= $disabled ? ' disabled' : '' ?> aria-label="<?= e(__('exams.value')) ?>">
                        </td>
                        <td class="d-none d-lg-table-cell"><input type="text" class="form-control form-control-sm" name="rows[<?= e($sid) ?>][note]" value="<?= e($r['note'] ?? '') ?>" maxlength="255"<?= $disabled ? ' disabled' : '' ?> aria-label="<?= e(__('fields.note')) ?>"></td>
                        <?php if ($closed && $canManage): ?>
                            <td class="text-end">
                                <?php if ($needsRetake && !in_array('scheduled', array_column($retakesByStudent[$sid] ?? [], 'status'), true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#retakeModal" data-retake-student="<?= e($sid) ?>" data-retake-name="<?= e(full_name($r, true)) ?>"><?= e(__('exams.schedule_retake')) ?></button>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($editable): ?>
            <div class="card-footer bg-transparent d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('common.save')) ?></button>
                <span class="small text-muted"><?= e(__('exams.statement_hint')) ?></span>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php $scheduled = array_values(array_filter($retakes, static fn (array $r): bool => $r['status'] === 'scheduled')); ?>
<div class="card mb-3" id="retakes">
    <div class="card-header fw-semibold"><?= e(__('exams.retakes')) ?></div>
    <?php if ($retakes === []): ?>
        <div class="card-body small text-muted"><?= e(__('exams.no_retakes')) ?></div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($retakes as $rt): ?>
                <div class="list-group-item d-flex flex-wrap gap-3 align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e(full_name($rt, true)) ?> · <?= e(__('exams.retake_n', ['n' => $rt['attempt_no']])) ?></div>
                        <div class="small text-muted"><?= e(format_date($rt['retake_date'])) ?> · <?= e(full_name(['last_name' => $rt['examiner_last_name'], 'first_name' => $rt['examiner_first_name'], 'middle_name' => $rt['examiner_middle_name']], true)) ?> · <?= e($rt['reason']) ?><?= $rt['order_number'] ? ' · № ' . e($rt['order_number']) : '' ?></div>
                    </div>
                    <span class="badge app-badge-muted"><?= e(__('exams.retake_statuses.' . $rt['status'])) ?></span>
                    <?php if ($rt['status'] === 'scheduled' && ($canManage || (int) $rt['examiner_teacher_id'] === (int) (App\Services\AccessScope::teacherId() ?? 0))): ?>
                        <form method="post" action="<?= e(url('/exams/' . $id . '/retakes/' . $rt['id'])) ?>" class="d-flex gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="text" class="form-control form-control-sm" name="value" style="width: 5.5rem" inputmode="decimal" maxlength="6" placeholder="<?= e(__('exams.value')) ?>" aria-label="<?= e(__('exams.value')) ?>">
                            <div class="form-check small mb-0"><input class="form-check-input" type="checkbox" name="absent" value="1" id="abs<?= e($rt['id']) ?>"><label class="form-check-label" for="abs<?= e($rt['id']) ?>"><?= e(__('exams.attendance_values.absent')) ?></label></div>
                            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('common.save')) ?></button>
                        </form>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= e(url('/exams/' . $id . '/retakes/' . $rt['id'])) ?>" data-confirm="<?= e(__('exams.retake_cancel_confirm')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn-sm btn-light" aria-label="<?= e(__('common.cancel')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($canManage && $closed): ?>
    <div class="modal fade" id="retakeModal" tabindex="-1" aria-labelledby="retakeTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'retake' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/exams/' . $id . '/retakes')) ?>" novalidate>
                <?= csrf_field() ?><input type="hidden" name="_form" value="retake">
                <input type="hidden" name="student_id" value="<?= e($openForm === 'retake' ? old('student_id') : '') ?>" data-retake-student-input>
                <div class="modal-header"><h2 class="modal-title h5" id="retakeTitle"><?= e(__('exams.schedule_retake')) ?>: <span data-retake-name-output></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body">
                    <?php $own = $openForm === 'retake'; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'rt_', 'ownsInput' => $own, 'name' => 'retake_date', 'label' => __('fields.date'), 'type' => 'date', 'required' => true, 'value' => date('Y-m-d')]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'rt_', 'ownsInput' => $own, 'name' => 'examiner_teacher_id', 'label' => __('exams.examiner'), 'type' => 'select', 'required' => true, 'value' => $exam['examiner_teacher_id'], 'options' => $teachers]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'rt_', 'ownsInput' => $own, 'name' => 'reason', 'label' => __('schedule.reason'), 'required' => true, 'attrs' => ['maxlength' => 255]]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'rt_', 'ownsInput' => $own, 'name' => 'order_number', 'label' => __('fields.order_number'), 'attrs' => ['maxlength' => 50]]) ?>
                    <?php if ($own && error('student_id')): ?><div class="alert alert-danger small"><?= e(error('student_id')) ?></div><?php endif; ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('exams.schedule_retake')) ?></button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="examReopen" tabindex="-1" aria-labelledby="examReopenTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'reopen' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/exams/' . $id . '/status')) ?>" novalidate>
                <?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="_form" value="reopen">
                <div class="modal-header"><h2 class="modal-title h5" id="examReopenTitle"><?= e(__('exams.reopen')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body"><p class="small text-muted"><?= e(__('exams.reopen_hint')) ?></p><?= $this->partial('partials/field', ['idPrefix' => 'xr_', 'ownsInput' => $openForm === 'reopen', 'name' => 'reason', 'label' => __('schedule.reason'), 'required' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('exams.reopen')) ?></button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
