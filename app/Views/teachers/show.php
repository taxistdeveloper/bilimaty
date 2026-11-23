<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $teacher */
/** @var list<array<string, mixed>> $groups */
/** @var list<array<string, mixed>> $departments */
/** @var bool $canManage */
$statusClass = ['active' => 'app-badge-success', 'on_leave' => 'app-badge-warning', 'dismissed' => 'app-badge-danger'];
$rows = [
    'fields.department_id' => $teacher['department_name_ru'] !== null ? localized(['name_ru' => $teacher['department_name_ru'], 'name_kk' => $teacher['department_name_kk']]) : null,
    'fields.position' => $teacher['position'],
    'fields.academic_degree' => $teacher['academic_degree'],
    'fields.employee_number' => $teacher['employee_number'],
    'fields.hire_date' => $teacher['hire_date'] ? format_date($teacher['hire_date']) : null,
    'fields.login' => $teacher['login'],
    'fields.email' => $teacher['email'],
    'fields.phone' => $teacher['phone'],
];
?>
<?= $this->partial('partials/page-header', [
    'heading' => full_name($teacher),
    'subtitle' => $teacher['position'] ?: __('teachers.one'),
    'back' => '/teachers',
    'actions' => $canManage ? '<a href="' . e(url('/teachers/' . $teacher['id'] . '/edit')) . '" class="btn btn-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>' : '',
]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('teachers.profile')) ?></span>
                <span class="badge <?= e($statusClass[$teacher['status']] ?? '') ?>"><?= e(__('teachers.statuses.' . $teacher['status'])) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($rows as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-muted"><?= e(__($label)) ?></span>
                        <span class="text-end"><?= e($value !== null && $value !== '' ? $value : '—') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <?php if ($departments !== []): ?>
            <div class="alert alert-light border d-flex gap-2 align-items-center">
                <i class="bi bi-diagram-3 text-primary" aria-hidden="true"></i>
                <span><?= e(__('teachers.heads')) ?>: <strong><?= e(implode(', ', array_map('localized', $departments))) ?></strong></span>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('teachers.curated_groups')) ?></div>
            <?php if ($groups === []): ?>
                <div class="card-body text-muted small"><?= e(__('teachers.no_groups')) ?></div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($groups as $group): ?>
                        <a href="<?= e(url('/groups/' . $group['id'])) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><strong><?= e($group['code']) ?></strong> <span class="text-muted small">· <?= e(__('groups.course_n', ['n' => $group['course']])) ?></span></span>
                            <span class="badge text-bg-light"><?= e(__('groups.students_count', ['count' => $group['students_count']])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
