<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $student */
/** @var array<string, mixed> $values */
/** @var array<int, string> $groups */
$isNew = $student === null;
$back = $isNew ? '/students' : '/students/' . $student['id'];
$languages = ['kk' => 'Қазақша', 'ru' => 'Русский'];
$genders = ['' => '—', 'male' => __('students.genders.male'), 'female' => __('students.genders.female')];
$funding = ['' => '—', 'state_grant' => __('students.funding.state_grant'), 'paid' => __('students.funding.paid')];
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('students.create') : full_name($student),
    'subtitle' => $isNew ? __('students.create_hint') : __('students.edit'),
    'back' => $back,
]) ?>

<form method="post" action="<?= e(url($isNew ? '/students' : '/students/' . $student['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('users.section_personal')) ?></div>
            <div class="card-body row">
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'last_name', 'label' => __('fields.last_name'), 'value' => $values['last_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'first_name', 'label' => __('fields.first_name'), 'value' => $values['first_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'middle_name', 'label' => __('fields.middle_name'), 'value' => $values['middle_name'] ?? '', 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'iin', 'label' => __('fields.iin'), 'value' => $values['iin'] ?? '', 'help' => __('students.iin_hint'), 'attrs' => ['maxlength' => 12, 'inputmode' => 'numeric', 'autocomplete' => 'off']]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'birth_date', 'label' => __('fields.birth_date'), 'type' => 'date', 'value' => $values['birth_date'] ?? '']) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'gender', 'label' => __('fields.gender'), 'type' => 'select', 'value' => $values['gender'] ?? '', 'options' => $genders]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $values['email'] ?? '', 'attrs' => ['maxlength' => 190]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $values['phone'] ?? '', 'attrs' => ['maxlength' => 20, 'placeholder' => '+7 700 000 00 00']]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('students.section_study')) ?></div>
            <div class="card-body row">
                <?php if ($isNew): ?>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'value' => $values['group_id'] ?? '', 'required' => true, 'options' => ['' => __('common.choose')] + $groups]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'order_number', 'label' => __('fields.order_number'), 'value' => '', 'help' => __('students.order_hint'), 'attrs' => ['maxlength' => 50]]) ?></div>
                <?php endif; ?>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'student_number', 'label' => __('fields.student_number'), 'value' => $values['student_number'] ?? '', 'help' => $isNew ? __('students.number_auto') : null, 'attrs' => ['maxlength' => 30]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'enrollment_date', 'label' => __('fields.enrollment_date'), 'type' => 'date', 'value' => $values['enrollment_date'] ?? '']) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'funding', 'label' => __('fields.funding'), 'type' => 'select', 'value' => $values['funding'] ?? '', 'options' => $funding]) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('users.section_account')) ?></div>
            <div class="card-body">
                <?= $this->partial('partials/field', ['name' => 'login', 'label' => __('fields.login'), 'value' => $values['login'] ?? '', 'help' => $isNew ? __('teachers.login_auto') : __('users.login_hint'), 'attrs' => ['maxlength' => 64, 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
                <?= $this->partial('partials/field', ['name' => 'locale', 'label' => __('fields.locale'), 'type' => 'select', 'value' => $values['locale'] ?? 'kk', 'options' => $languages, 'required' => true]) ?>
                <?php if ($isNew): ?><p class="small text-muted mb-0"><i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('teachers.password_note')) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('students.enroll') : __('common.save')) ?></button>
            <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
