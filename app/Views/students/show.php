<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $student */
/** @var list<array<string, mixed>> $history */
/** @var bool $canManage */
/** @var array<int, string> $groups */
/** @var list<string> $statuses */
/** @var list<array<string, mixed>> $guardians */
/** @var array{login: string, password: string}|null $tempPassword */
$statusClass = ['active' => 'app-badge-success', 'academic_leave' => 'app-badge-warning', 'expelled' => 'app-badge-danger', 'graduated' => 'app-badge-primary', 'transferred' => 'app-badge-muted'];
$isActive = $student['status'] === 'active';
// IIN is shown in full only to users who manage students; others see a mask.
$iin = $student['iin'] !== null ? ($canManage ? $student['iin'] : substr((string) $student['iin'], 0, 2) . '••••••••' . substr((string) $student['iin'], -2)) : null;
$rows = [
    'fields.student_number' => $student['student_number'],
    'fields.iin' => $iin,
    'fields.birth_date' => $student['birth_date'] ? format_date($student['birth_date']) : null,
    'fields.gender' => $student['gender'] ? __('students.genders.' . $student['gender']) : null,
    'fields.enrollment_date' => $student['enrollment_date'] ? format_date($student['enrollment_date']) : null,
    'fields.funding' => $student['funding'] ? __('students.funding.' . $student['funding']) : null,
    'fields.login' => $student['login'],
    'fields.email' => $student['email'],
    'fields.phone' => $student['phone'],
];
$openForm = (string) old('_form', '');
$hasErrors = errors() !== [];
$targetStatuses = $isActive ? array_values(array_diff($statuses, ['active'])) : ['active'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => full_name($student),
    'subtitle' => $student['group_code'] !== null ? __('groups.one') . ' ' . $student['group_code'] . ' · ' . __('groups.course_n', ['n' => $student['course']]) : null,
    'back' => '/students',
    'actions' => $canManage
        ? '<a href="' . e(url('/students/' . $student['id'] . '/edit')) . '" class="btn btn-light"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>'
          . ($isActive ? '<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . e(__('students.transfer')) . '</button>' : '')
          . '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-flag me-1" aria-hidden="true"></i>' . e($isActive ? __('students.change_status') : __('students.restore')) . '</button>'
        : '',
]) ?>

<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('students.card')) ?></span>
                <span class="badge <?= e($statusClass[$student['status']] ?? '') ?>"><?= e(__('students.statuses.' . $student['status'])) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($rows as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-muted"><?= e(__($label)) ?></span>
                        <span class="text-end<?= $label === 'fields.iin' ? ' font-monospace' : '' ?>"><?= e($value !== null && $value !== '' ? $value : '—') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($student['group_code'] !== null): ?>
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('students.section_study')) ?></div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted"><?= e(__('fields.group_id')) ?></span><a href="<?= e(url('/groups/' . $student['current_group_id'])) ?>"><?= e($student['group_code']) ?></a></li>
                    <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted"><?= e(__('fields.department_id')) ?></span><span class="text-end"><?= e(localized(['name_ru' => $student['department_name_ru'], 'name_kk' => $student['department_name_kk']])) ?></span></li>
                    <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted"><?= e(__('fields.specialty_id')) ?></span><span class="text-end"><?= e($student['specialty_code'] . ' — ' . localized(['name_ru' => $student['specialty_name_ru'], 'name_kk' => $student['specialty_name_kk']])) ?></span></li>
                    <?php if ($student['qualification_name_ru'] !== null): ?>
                        <li class="list-group-item d-flex justify-content-between gap-3"><span class="text-muted"><?= e(__('fields.qualification_id')) ?></span><span class="text-end"><?= e(localized(['name_ru' => $student['qualification_name_ru'], 'name_kk' => $student['qualification_name_kk']])) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?= $this->partial('students/_guardians', ['student' => $student, 'guardians' => $guardians, 'canManage' => $canManage]) ?>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('students.history')) ?></div>
            <?php if ($history === []): ?>
                <div class="card-body small text-muted"><?= e(__('students.no_history')) ?></div>
            <?php else: ?>
                <ol class="list-unstyled app-timeline mb-0">
                    <?php foreach ($history as $item): ?>
                        <li class="app-timeline__item<?= $item['end_date'] === null ? ' is-current' : '' ?>">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <a href="<?= e(url('/groups/' . $item['group_id'])) ?>" class="fw-semibold text-decoration-none"><?= e($item['group_code']) ?></a>
                                    <span class="badge text-bg-light ms-1"><?= e(__('students.reasons.' . $item['reason'])) ?></span>
                                    <?php if ($item['end_reason'] !== null): ?>
                                        <i class="bi bi-arrow-right small text-muted mx-1" aria-hidden="true"></i><span class="badge text-bg-light"><?= e(__('students.end_reasons.' . $item['end_reason'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted text-nowrap">
                                    <?= e(format_date($item['start_date'])) ?> — <?= e($item['end_date'] !== null ? format_date($item['end_date']) : __('students.now')) ?>
                                </div>
                            </div>
                            <?php if ($item['order_number'] || $item['comment']): ?>
                                <div class="small mt-1">
                                    <?php if ($item['order_number']): ?><span class="text-muted"><?= e(__('fields.order_number')) ?>:</span> <?= e($item['order_number']) ?><?php endif; ?>
                                    <?php if ($item['comment']): ?><div class="text-muted"><?= e($item['comment']) ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($item['author_last_name'] !== null): ?>
                                <div class="small text-muted mt-1"><i class="bi bi-person me-1" aria-hidden="true"></i><?= e(full_name(['last_name' => $item['author_last_name'], 'first_name' => $item['author_first_name'], 'middle_name' => $item['author_middle_name']], true)) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
    <?php if ($isActive): ?>
        <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'transfer' ? ' data-show-on-load' : '' ?>>
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/students/' . $student['id'] . '/transfer')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_form" value="transfer">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="transferTitle"><?= e(__('students.transfer')) ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
                    </div>
                    <div class="modal-body">
                        <?= $this->partial('partials/field', ['idPrefix' => 't_', 'ownsInput' => $openForm === 'transfer', 'name' => 'group_id', 'label' => __('students.new_group'), 'type' => 'select', 'required' => true, 'value' => '', 'options' => ['' => __('common.choose')] + array_diff_key($groups, [(int) $student['current_group_id'] => true])]) ?>
                        <div class="row">
                            <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 't_', 'ownsInput' => $openForm === 'transfer', 'name' => 'date', 'label' => __('fields.date'), 'type' => 'date', 'required' => true, 'value' => date('Y-m-d')]) ?></div>
                            <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 't_', 'ownsInput' => $openForm === 'transfer', 'name' => 'reason', 'label' => __('fields.reason'), 'type' => 'select', 'required' => true, 'value' => 'transfer', 'options' => ['transfer' => __('students.reasons.transfer'), 'promotion' => __('students.reasons.promotion')]]) ?></div>
                        </div>
                        <?= $this->partial('partials/field', ['idPrefix' => 't_', 'ownsInput' => $openForm === 'transfer', 'name' => 'order_number', 'label' => __('fields.order_number'), 'attrs' => ['maxlength' => 50]]) ?>
                        <?= $this->partial('partials/field', ['idPrefix' => 't_', 'ownsInput' => $openForm === 'transfer', 'name' => 'comment', 'label' => __('fields.comment'), 'attrs' => ['maxlength' => 500]]) ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                        <button type="submit" class="btn btn-primary"><?= e(__('students.transfer')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'status' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/students/' . $student['id'] . '/status')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_form" value="status">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="statusTitle"><?= e($isActive ? __('students.change_status') : __('students.restore')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <?php $statusOptions = []; foreach ($targetStatuses as $s) { $statusOptions[$s] = __('students.statuses.' . $s); } ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $openForm === 'status', 'name' => 'status', 'label' => __('students.new_status'), 'type' => 'select', 'required' => true, 'value' => $targetStatuses[0], 'options' => $statusOptions]) ?>
                    <?php if (!$isActive): ?>
                        <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $openForm === 'status', 'name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'required' => true, 'value' => $student['current_group_id'] ?? '', 'options' => ['' => __('common.choose')] + $groups]) ?>
                    <?php else: ?>
                        <p class="small text-muted"><?= e(__('students.status_hint')) ?></p>
                    <?php endif; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $openForm === 'status', 'name' => 'date', 'label' => __('fields.date'), 'type' => 'date', 'required' => true, 'value' => date('Y-m-d')]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $openForm === 'status', 'name' => 'order_number', 'label' => __('fields.order_number'), 'attrs' => ['maxlength' => 50]]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 's_', 'ownsInput' => $openForm === 'status', 'name' => 'comment', 'label' => __('fields.comment'), 'attrs' => ['maxlength' => 500]]) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
