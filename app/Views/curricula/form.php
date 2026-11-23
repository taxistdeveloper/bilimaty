<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed> $values */
/** @var array<int, string> $specialties */
/** @var list<array{id: int, specialty_id: int, label: string}> $qualifications */
/** @var list<string> $forms */
/** @var list<string> $bases */
$isNew = $plan === null;
$back = $isNew ? '/curricula' : '/curricula/' . $plan['id'];
$formOptions = [];
foreach ($forms as $f) {
    $formOptions[$f] = __('groups.forms.' . $f);
}
$baseOptions = [];
foreach ($bases as $b) {
    $baseOptions[$b] = __('curricula.bases.' . $b);
}
$qualificationValue = (string) old('qualification_id', $values['qualification_id'] ?? '');
$qualificationError = error('qualification_id');
?>
<?= $this->partial('partials/page-header', ['heading' => $isNew ? __('curricula.create') : __('curricula.edit'), 'back' => $back]) ?>

<form method="post" action="<?= e(url($isNew ? '/curricula' : '/curricula/' . $plan['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-body row">
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'title', 'label' => __('curricula.plan_title'), 'required' => true, 'value' => $values['title'] ?? '', 'help' => __('curricula.title_hint'), 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'specialty_id', 'label' => __('fields.specialty_id'), 'type' => 'select', 'required' => true, 'value' => $values['specialty_id'] ?? '', 'options' => ['' => __('common.choose')] + $specialties, 'attrs' => ['data-specialty-select' => '#f_qualification_id']]) ?></div>
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
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'admission_year', 'label' => __('curricula.intake'), 'type' => 'number', 'required' => true, 'value' => $values['admission_year'] ?? '', 'attrs' => ['min' => 2000, 'max' => 2100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'study_form', 'label' => __('fields.study_form'), 'type' => 'select', 'required' => true, 'value' => $values['study_form'] ?? 'full_time', 'options' => $formOptions]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'semesters_count', 'label' => __('curricula.semesters_count'), 'type' => 'number', 'required' => true, 'value' => $values['semesters_count'] ?? 8, 'attrs' => ['min' => 1, 'max' => 10]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'base_education', 'label' => __('curricula.base'), 'type' => 'select', 'required' => true, 'value' => $values['base_education'] ?? 'basic_secondary', 'options' => $baseOptions]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.comment'), 'value' => $values['note'] ?? '', 'attrs' => ['maxlength' => 500]]) ?></div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </div>
    </div>
</form>
