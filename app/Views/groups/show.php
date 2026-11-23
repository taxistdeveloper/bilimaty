<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $group */
/** @var list<array<string, mixed>> $students */
/** @var bool $canManage */
/** @var int $usage */
$statusClass = ['active' => 'app-badge-success', 'graduated' => 'app-badge-primary', 'archived' => 'app-badge-muted', 'disbanded' => 'app-badge-danger'];
$studentClass = ['active' => 'app-badge-success', 'academic_leave' => 'app-badge-warning', 'expelled' => 'app-badge-danger', 'graduated' => 'app-badge-primary', 'transferred' => 'app-badge-muted'];
$curator = $group['curator_last_name'] !== null
    ? full_name(['last_name' => $group['curator_last_name'], 'first_name' => $group['curator_first_name'], 'middle_name' => $group['curator_middle_name']])
    : null;
$rows = [
    'fields.department_id' => localized(['name_ru' => $group['department_name_ru'], 'name_kk' => $group['department_name_kk']]),
    'fields.specialty_id' => $group['specialty_code'] . ' — ' . localized(['name_ru' => $group['specialty_name_ru'], 'name_kk' => $group['specialty_name_kk']]),
    'fields.qualification_id' => $group['qualification_code'] !== null ? $group['qualification_code'] . ' — ' . localized(['name_ru' => $group['qualification_name_ru'], 'name_kk' => $group['qualification_name_kk']]) : null,
    'groups.curriculum' => $group['curriculum_title'],
    'fields.course' => (string) $group['course'],
    'fields.admission_year' => (string) $group['admission_year'],
    'fields.academic_year_id' => str_replace('-', '–', (string) $group['academic_year_title']),
    'fields.study_form' => __('groups.forms.' . $group['study_form']),
    'fields.study_language' => __('groups.languages.' . $group['study_language']),
    'fields.curator_teacher_id' => $curator,
];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('groups.one') . ' ' . $group['code'],
    'subtitle' => $group['name'],
    'back' => '/groups',
    'actions' => $canManage ? '<a href="' . e(url('/groups/' . $group['id'] . '/edit')) . '" class="btn btn-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>' : '',
]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('groups.info')) ?></span>
                <span class="badge <?= e($statusClass[$group['status']] ?? '') ?>"><?= e(__('groups.statuses.' . $group['status'])) ?></span>
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($rows as $label => $value): ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-muted text-nowrap"><?= e(__($label)) ?></span>
                        <span class="text-end"><?= e($value !== null && $value !== '' ? $value : '—') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($canManage && $usage === 0): ?>
            <form method="post" action="<?= e(url('/groups/' . $group['id'])) ?>" data-confirm="<?= e(__('groups.delete_confirm')) ?>" data-confirm-danger>
                <?= csrf_field() ?>
                <?= method_field('DELETE') ?>
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('groups.students')) ?></span>
                <span class="small text-muted"><?= e(__('groups.students_count', ['count' => $group['students_count']])) ?></span>
            </div>
            <?php if (!can('students.view')): ?>
                <div class="card-body small text-muted"><?= e(__('groups.students_hidden')) ?></div>
            <?php elseif ($students === []): ?>
                <div class="card-body small text-muted"><?= e(__('groups.no_students')) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 app-table">
                        <tbody>
                        <?php foreach ($students as $i => $student): ?>
                            <tr>
                                <td class="text-muted small" style="width: 2.5rem"><?= e($i + 1) ?></td>
                                <td>
                                    <a href="<?= e(url('/students/' . $student['id'])) ?>" class="text-decoration-none fw-semibold"><?= e(full_name($student)) ?></a>
                                    <div class="small text-muted"><?= e($student['student_number']) ?></div>
                                </td>
                                <td class="text-end"><span class="badge <?= e($studentClass[$student['status']] ?? '') ?>"><?= e(__('students.statuses.' . $student['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
