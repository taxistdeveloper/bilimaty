<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $teacher */
/** @var array<int, string> $departments */
$isNew = $teacher === null;
$languages = ['kk' => 'Қазақша', 'ru' => 'Русский'];
$statuses = [];
foreach (['active', 'on_leave', 'dismissed'] as $status) {
    $statuses[$status] = __('teachers.statuses.' . $status);
}
$back = $isNew ? '/teachers' : '/teachers/' . $teacher['id'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('teachers.create') : full_name($teacher),
    'subtitle' => $isNew ? null : __('teachers.edit'),
    'back' => $back,
]) ?>

<form method="post" action="<?= e(url($isNew ? '/teachers' : '/teachers/' . $teacher['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('users.section_personal')) ?></div>
            <div class="card-body row">
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'last_name', 'label' => __('fields.last_name'), 'value' => $teacher['last_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'first_name', 'label' => __('fields.first_name'), 'value' => $teacher['first_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'middle_name', 'label' => __('fields.middle_name'), 'value' => $teacher['middle_name'] ?? '', 'attrs' => ['maxlength' => 100]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $teacher['email'] ?? '', 'attrs' => ['maxlength' => 190]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $teacher['phone'] ?? '', 'attrs' => ['maxlength' => 20, 'placeholder' => '+7 700 000 00 00']]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('teachers.section_job')) ?></div>
            <div class="card-body row">
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'department_id', 'label' => __('fields.department_id'), 'type' => 'select', 'value' => $teacher['department_id'] ?? '', 'options' => ['' => '—'] + $departments]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'position', 'label' => __('fields.position'), 'value' => $teacher['position'] ?? '', 'attrs' => ['maxlength' => 150]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'academic_degree', 'label' => __('fields.academic_degree'), 'value' => $teacher['academic_degree'] ?? '', 'attrs' => ['maxlength' => 150]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'employee_number', 'label' => __('fields.employee_number'), 'value' => $teacher['employee_number'] ?? '', 'attrs' => ['maxlength' => 30]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'hire_date', 'label' => __('fields.hire_date'), 'type' => 'date', 'value' => $teacher['hire_date'] ?? '']) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'status', 'label' => __('common.status'), 'type' => 'select', 'value' => $teacher['status'] ?? 'active', 'options' => $statuses, 'required' => true, 'help' => __('teachers.dismissed_hint')]) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('users.section_account')) ?></div>
            <div class="card-body">
                <?= $this->partial('partials/field', ['name' => 'login', 'label' => __('fields.login'), 'value' => $teacher['login'] ?? '', 'help' => $isNew ? __('teachers.login_auto') : __('users.login_hint'), 'attrs' => ['maxlength' => 64, 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
                <?= $this->partial('partials/field', ['name' => 'locale', 'label' => __('fields.locale'), 'type' => 'select', 'value' => $teacher['locale'] ?? 'kk', 'options' => $languages, 'required' => true]) ?>
                <?php if ($isNew): ?><p class="small text-muted mb-0"><i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('teachers.password_note')) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
            <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
