<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $group */
/** @var array<string, mixed> $values */
/** @var array<int, string> $specialties */
/** @var list<array{id: int, specialty_id: int, label: string}> $qualifications */
/** @var array<int, string> $years */
/** @var array<int, string> $teachers */
/** @var list<string> $statuses */
/** @var list<string> $forms */
/** @var array<int, array<string, mixed>> $curricula */
$isNew = $group === null;
$back = $isNew ? '/groups' : '/groups/' . $group['id'];
$specialtyValue = (string) old('specialty_id', $values['specialty_id'] ?? '');
$qualificationValue = (string) old('qualification_id', $values['qualification_id'] ?? '');
$qualificationError = error('qualification_id');
$statusOptions = [];
foreach ($statuses as $status) {
    $statusOptions[$status] = __('groups.statuses.' . $status);
}
$formOptions = [];
foreach ($forms as $form) {
    $formOptions[$form] = __('groups.forms.' . $form);
}
$courses = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('groups.create') : __('groups.one') . ' ' . $group['code'],
    'subtitle' => $isNew ? null : __('groups.edit'),
    'back' => $back,
]) ?>

<form method="post" action="<?= e(url($isNew ? '/groups' : '/groups/' . $group['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-body row">
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'code', 'label' => __('fields.group_code'), 'value' => $values['code'] ?? '', 'required' => true, 'help' => __('groups.code_hint'), 'attrs' => ['maxlength' => 30]]) ?></div>
                <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'name', 'label' => __('fields.group_name'), 'value' => $values['name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'specialty_id', 'label' => __('fields.specialty_id'), 'type' => 'select', 'value' => $values['specialty_id'] ?? '', 'required' => true, 'options' => ['' => __('common.choose')] + $specialties, 'attrs' => ['data-specialty-select' => '#f_qualification_id, #f_curriculum_id']]) ?></div>
                <div class="col-md-6 mb-3">
                    <label for="f_qualification_id" class="form-label fw-semibold"><?= e(__('fields.qualification_id')) ?></label>
                    <select id="f_qualification_id" name="qualification_id" class="form-select<?= $qualificationError ? ' is-invalid' : '' ?>">
                        <option value="">—</option>
                        <?php foreach ($qualifications as $q): ?>
                            <option value="<?= e($q['id']) ?>" data-specialty="<?= e($q['specialty_id']) ?>"<?= $qualificationValue === (string) $q['id'] ? ' selected' : '' ?>><?= e($q['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($qualificationError): ?><div class="invalid-feedback d-block"><?= e($qualificationError) ?></div><?php endif; ?>
                </div>
                <?php $curriculumValue = (string) old('curriculum_id', $values['curriculum_id'] ?? ''); $curriculumError = error('curriculum_id'); ?>
                <div class="col-12 mb-3">
                    <label for="f_curriculum_id" class="form-label fw-semibold"><?= e(__('groups.curriculum')) ?></label>
                    <select id="f_curriculum_id" name="curriculum_id" class="form-select<?= $curriculumError ? ' is-invalid' : '' ?>">
                        <option value="">—</option>
                        <?php foreach ($curricula as $plan): ?>
                            <option value="<?= e($plan['id']) ?>" data-specialty="<?= e($plan['specialty_id']) ?>"<?= $curriculumValue === (string) $plan['id'] ? ' selected' : '' ?>><?= e($plan['title'] . ' · ' . $plan['admission_year'] . ' · ' . __('curricula.statuses.' . $plan['status'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($curriculumError): ?><div class="invalid-feedback d-block"><?= e($curriculumError) ?></div><?php endif; ?>
                </div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'course', 'label' => __('fields.course'), 'type' => 'select', 'value' => $values['course'] ?? 1, 'required' => true, 'options' => $courses]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'admission_year', 'label' => __('fields.admission_year'), 'type' => 'number', 'value' => $values['admission_year'] ?? '', 'required' => true, 'attrs' => ['min' => 2000, 'max' => 2100]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'academic_year_id', 'label' => __('fields.academic_year_id'), 'type' => 'select', 'value' => $values['academic_year_id'] ?? '', 'required' => true, 'options' => ['' => __('common.choose')] + $years]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'study_form', 'label' => __('fields.study_form'), 'type' => 'select', 'value' => $values['study_form'] ?? 'full_time', 'required' => true, 'options' => $formOptions]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'study_language', 'label' => __('fields.study_language'), 'type' => 'select', 'value' => $values['study_language'] ?? 'kk', 'required' => true, 'options' => ['kk' => __('groups.languages.kk'), 'ru' => __('groups.languages.ru')]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'status', 'label' => __('common.status'), 'type' => 'select', 'value' => $values['status'] ?? 'active', 'required' => true, 'options' => $statusOptions]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'curator_teacher_id', 'label' => __('fields.curator_teacher_id'), 'type' => 'select', 'value' => $values['curator_teacher_id'] ?? '', 'options' => ['' => '—'] + $teachers, 'help' => __('groups.curator_hint')]) ?></div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </div>
    </div>
</form>
