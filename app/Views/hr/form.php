<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $employee */
/** @var array<string, mixed>|null $user  existing account the file is opened for */
/** @var list<array<string, mixed>> $users accounts without a personnel file */
/** @var string $nextNumber */
/** @var array<int, string> $departments */
$isNew = $employee === null;
$e = $employee ?? [];
$opt = static function (array $keys, string $prefix): array {
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __($prefix . $k);
    }

    return $out;
};
$back = $isNew ? '/hr/employees' : '/hr/employees/' . $e['id'];
$person = $user ?? $employee;
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('hr.employee_create') : full_name($e),
    'subtitle' => $isNew ? ($user !== null ? full_name($user) : __('hr.create_hint')) : __('hr.employee_edit'),
    'back' => $back,
]) ?>

<?php if ($isNew && $user === null && $users !== []): ?>
    <div class="card mb-3">
        <form method="get" action="<?= e(url('/hr/employees/create')) ?>" class="card-body row g-2 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label fw-semibold" for="user"><?= e(__('hr.from_user')) ?></label>
                <select class="form-select" id="user" name="user">
                    <?php foreach ($users as $u): ?><option value="<?= e($u['id']) ?>"><?= e(full_name($u)) ?> (<?= e($u['login']) ?>)</option><?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(__('hr.from_user_hint')) ?></div>
            </div>
            <div class="col-12 col-md-4"><button type="submit" class="btn btn-outline-primary w-100"><?= e(__('hr.choose')) ?></button></div>
        </form>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/hr/employees' : '/hr/employees/' . $e['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <?php if ($user !== null): ?><input type="hidden" name="user_id" value="<?= e($user['id']) ?>"><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('users.section_personal')) ?></div>
            <div class="card-body row">
                <?php if ($user === null): ?>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'last_name', 'label' => __('fields.last_name'), 'value' => $person['last_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'first_name', 'label' => __('fields.first_name'), 'value' => $person['first_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'middle_name', 'label' => __('fields.middle_name'), 'value' => $person['middle_name'] ?? '', 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $person['email'] ?? '', 'attrs' => ['maxlength' => 190]]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $person['phone'] ?? '', 'attrs' => ['maxlength' => 20, 'placeholder' => '+7 700 000 00 00']]) ?></div>
                <?php endif; ?>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'iin', 'label' => __('fields.iin'), 'value' => $e['iin'] ?? '', 'attrs' => ['maxlength' => 12, 'inputmode' => 'numeric']]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'birth_date', 'label' => __('fields.birth_date'), 'type' => 'date', 'value' => $e['birth_date'] ?? '']) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'gender', 'label' => __('fields.gender'), 'type' => 'select', 'value' => $e['gender'] ?? '', 'options' => ['' => '—'] + $opt(['male', 'female'], 'students.genders.')]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'address', 'label' => __('fields.address'), 'value' => $e['address'] ?? '', 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'education', 'label' => __('fields.education'), 'value' => $e['education'] ?? '', 'help' => __('hr.education_hint'), 'attrs' => ['maxlength' => 500]]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('teachers.section_job')) ?></div>
            <div class="card-body row">
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'personnel_number', 'label' => __('fields.personnel_number'), 'value' => $e['personnel_number'] ?? $nextNumber, 'required' => true, 'attrs' => ['maxlength' => 30]]) ?></div>
                <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'department_id', 'label' => __('fields.department_id'), 'type' => 'select', 'value' => $e['department_id'] ?? '', 'options' => ['' => '—'] + $departments]) ?></div>
                <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'position', 'label' => __('fields.position'), 'value' => $e['position'] ?? '', 'attrs' => ['maxlength' => 150]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'rate', 'label' => __('fields.rate'), 'value' => $e['rate'] ?? '1.00', 'required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'staff_category', 'label' => __('fields.staff_category'), 'type' => 'select', 'value' => $e['staff_category'] ?? 'teaching', 'options' => $opt(App\Services\HrService::CATEGORIES, 'hr.staff_categories.'), 'required' => true]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'employment_type', 'label' => __('fields.employment_type'), 'type' => 'select', 'value' => $e['employment_type'] ?? 'main', 'options' => $opt(App\Services\HrService::EMPLOYMENT, 'hr.employment_types.'), 'required' => true]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'hire_date', 'label' => __('fields.hire_date'), 'type' => 'date', 'value' => $e['hire_date'] ?? '']) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'prior_experience_months', 'label' => __('fields.prior_experience_months'), 'type' => 'number', 'value' => $e['prior_experience_months'] ?? '0', 'help' => __('hr.prior_experience_hint'), 'attrs' => ['min' => 0, 'max' => 900]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'annual_leave_days', 'label' => __('fields.annual_leave_days'), 'type' => 'number', 'value' => $e['annual_leave_days'] ?? '', 'help' => __('hr.annual_leave_hint'), 'attrs' => ['min' => 1, 'max' => 90]]) ?></div>
                <?php if (!$isNew): ?><p class="small text-muted mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('hr.job_via_orders')) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('hr.qualification_category')) ?></div>
            <div class="card-body">
                <?= $this->partial('partials/field', ['name' => 'qualification_category', 'label' => __('fields.qualification_category'), 'type' => 'select', 'value' => $e['qualification_category'] ?? '', 'options' => ['' => '—'] + $opt(App\Controllers\HrController::categories(), 'hr.categories.')]) ?>
                <?= $this->partial('partials/field', ['name' => 'category_assigned_on', 'label' => __('fields.category_assigned_on'), 'type' => 'date', 'value' => $e['category_assigned_on'] ?? '']) ?>
                <?= $this->partial('partials/field', ['name' => 'category_valid_until', 'label' => __('fields.category_valid_until'), 'type' => 'date', 'value' => $e['category_valid_until'] ?? '']) ?>
                <?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'value' => $e['note'] ?? '', 'attrs' => ['maxlength' => 500]]) ?>
            </div>
        </div>
        <?php if ($isNew && $user === null): ?><p class="small text-muted"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= e(__('hr.no_access_note')) ?></p><?php endif; ?>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
            <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
