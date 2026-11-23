<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $plan */
/** @var array<int, list<array<string, mixed>>> $bySemester */
/** @var list<array<string, mixed>> $groups */
/** @var array<int, string> $subjects */
/** @var bool $canEdit */
/** @var bool $canManage */
/** @var list<string> $controls */
$statusClass = ['draft' => 'app-badge-warning', 'approved' => 'app-badge-success', 'archived' => 'app-badge-muted'];
$id = (int) $plan['id'];
$status = $plan['status'];
$statusButton = static function (string $action, string $label, string $class, ?string $confirm = null) use ($id): string {
    return '<form method="post" action="' . e(url('/curricula/' . $id . '/status')) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button type="submit" class="btn ' . e($class) . '">' . e($label) . '</button></form>';
};
$actions = '';
if ($canManage) {
    $actions .= '<a href="' . e(url('/curricula/' . $id . '/print')) . '" class="btn btn-light" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('calendar.pdf')) . '</a>';
    $actions .= '<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#copyModal"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . e(__('curricula.copy')) . '</button>';
    if ($status === 'draft') {
        $actions .= '<a href="' . e(url('/curricula/' . $id . '/edit')) . '" class="btn btn-light"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>';
        $actions .= $statusButton('approve', __('curricula.approve'), 'btn-primary', __('curricula.approve_confirm'));
    } elseif ($status === 'approved') {
        $actions .= $statusButton('reopen', __('curricula.reopen'), 'btn-light', __('curricula.reopen_confirm'));
        $actions .= $statusButton('archive', __('curricula.archive'), 'btn-light', __('curricula.archive_confirm'));
    } else {
        $actions .= $statusButton('reopen', __('years.restore'), 'btn-light');
    }
} else {
    $actions .= '<a href="' . e(url('/curricula/' . $id . '/print')) . '" class="btn btn-light" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('calendar.pdf')) . '</a>';
}
$grand = ['total' => 0, 'theory' => 0, 'practice' => 0, 'lab' => 0, 'credits' => 0.0, 'exam' => 0];
$hasErrors = errors() !== [];
$openForm = (string) old('_form', '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => $plan['title'],
    'subtitle' => $plan['specialty_code'] . ' — ' . localized(['name_ru' => $plan['specialty_name_ru'], 'name_kk' => $plan['specialty_name_kk']]),
    'back' => '/curricula',
    'actions' => $actions,
]) ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body row small g-3">
                <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('common.status')) ?></div><span class="badge <?= e($statusClass[$status] ?? '') ?>"><?= e(__('curricula.statuses.' . $status)) ?></span></div>
                <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('curricula.intake')) ?></div><strong><?= e($plan['admission_year']) ?></strong></div>
                <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('fields.study_form')) ?></div><?= e(__('groups.forms.' . $plan['study_form'])) ?></div>
                <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('curricula.base')) ?></div><?= e(__('curricula.bases.' . $plan['base_education'])) ?></div>
                <div class="col-12 col-md-6"><div class="text-muted"><?= e(__('fields.qualification_id')) ?></div><?= e($plan['qualification_code'] !== null ? $plan['qualification_code'] . ' — ' . localized(['name_ru' => $plan['qualification_name_ru'], 'name_kk' => $plan['qualification_name_kk']]) : '—') ?></div>
                <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('curricula.semesters_count')) ?></div><?= e($plan['semesters_count']) ?></div>
                <?php if ($plan['approved_at'] !== null): ?>
                    <div class="col-6 col-md-3"><div class="text-muted"><?= e(__('curricula.approved_at')) ?></div><?= e(format_date($plan['approved_at'])) ?><?= $plan['approver_last_name'] !== null ? '<div class="text-muted">' . e(full_name(['last_name' => $plan['approver_last_name'], 'first_name' => $plan['approver_first_name'], 'middle_name' => $plan['approver_middle_name']], true)) . '</div>' : '' ?></div>
                <?php endif; ?>
                <?php if ($plan['note']): ?><div class="col-12 text-muted"><?= e($plan['note']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= e(__('nav.groups')) ?></div>
            <div class="card-body small">
                <?php if ($groups === []): ?><span class="text-muted"><?= e(__('curricula.no_groups')) ?></span><?php endif; ?>
                <?php foreach ($groups as $g): ?>
                    <a href="<?= e(url('/groups/' . $g['id'])) ?>" class="badge app-badge-primary text-decoration-none me-1 mb-1"><?= e($g['code']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($status !== 'draft'): ?>
    <div class="alert alert-light border small"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('curricula.readonly_note')) ?></div>
<?php endif; ?>

<?php if ($bySemester === []): ?>
    <div class="card mb-3"><div class="app-empty"><i class="bi bi-journal-plus" aria-hidden="true"></i><p class="mb-0"><?= e(__('curricula.no_items')) ?></p></div></div>
<?php endif; ?>

<?php foreach ($bySemester as $semester => $items): ?>
    <?php $sum = ['total' => 0, 'theory' => 0, 'practice' => 0, 'lab' => 0, 'credits' => 0.0, 'exam' => 0]; ?>
    <div class="card mb-3" id="semester-<?= e($semester) ?>">
        <div class="card-header fw-semibold"><?= e(__('dashboard.semester', ['number' => $semester])) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 app-table app-plan">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('curricula.module')) ?></th>
                    <th scope="col"><?= e(__('curricula.subject')) ?></th>
                    <th scope="col" class="text-end"><?= e(__('curricula.hours_total_short')) ?></th>
                    <th scope="col" class="text-end d-none d-md-table-cell"><?= e(__('curricula.hours_theory_short')) ?></th>
                    <th scope="col" class="text-end d-none d-md-table-cell"><?= e(__('curricula.hours_practice_short')) ?></th>
                    <th scope="col" class="text-end d-none d-md-table-cell"><?= e(__('curricula.hours_lab_short')) ?></th>
                    <th scope="col" class="text-end d-none d-lg-table-cell"><?= e(__('curricula.credits')) ?></th>
                    <th scope="col"><?= e(__('curricula.control')) ?></th>
                    <?php if ($canEdit): ?><th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $sum['total'] += (int) $item['hours_total'];
                    $sum['theory'] += (int) $item['hours_theory'];
                    $sum['practice'] += (int) $item['hours_practice'];
                    $sum['lab'] += (int) $item['hours_lab'];
                    $sum['credits'] += (float) ($item['credits'] ?? 0);
                    $sum['exam'] += $item['control_form'] === 'exam' ? 1 : 0;
                    ?>
                    <tr>
                        <td class="small text-muted text-nowrap"><?= e($item['module_code'] ?? '—') ?></td>
                        <td><span class="small text-muted"><?= e($item['subject_code']) ?></span> <?= e(localized($item)) ?></td>
                        <td class="text-end fw-semibold"><?= e($item['hours_total']) ?></td>
                        <td class="text-end d-none d-md-table-cell"><?= e($item['hours_theory']) ?></td>
                        <td class="text-end d-none d-md-table-cell"><?= e($item['hours_practice']) ?></td>
                        <td class="text-end d-none d-md-table-cell"><?= e($item['hours_lab']) ?></td>
                        <td class="text-end d-none d-lg-table-cell"><?= e($item['credits'] ?? '—') ?></td>
                        <td class="small"><?= e(__('curricula.controls.' . $item['control_form'])) ?></td>
                        <?php if ($canEdit): ?>
                            <td class="text-end text-nowrap">
                                <a href="<?= e(url('/curricula/' . $id . '/items/' . $item['id'] . '/edit')) ?>" class="btn btn-sm btn-light" aria-label="<?= e(__('common.edit')) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                <form method="post" action="<?= e(url('/curricula/' . $id . '/items/' . $item['id'])) ?>" class="d-inline" data-confirm="<?= e(__('curricula.item_delete_confirm')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button type="submit" class="btn btn-sm btn-light text-danger" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($sum as $k => $v) { $grand[$k] += $v; } ?>
                </tbody>
                <tfoot>
                <tr class="fw-semibold">
                    <td colspan="2"><?= e(__('curricula.semester_total')) ?> · <?= e(__('curricula.exams_count', ['count' => $sum['exam']])) ?></td>
                    <td class="text-end"><?= e($sum['total']) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= e($sum['theory']) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= e($sum['practice']) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= e($sum['lab']) ?></td>
                    <td class="text-end d-none d-lg-table-cell"><?= e($sum['credits'] > 0 ? rtrim(rtrim(number_format($sum['credits'], 2, '.', ''), '0'), '.') : '—') ?></td>
                    <td colspan="<?= $canEdit ? 2 : 1 ?>"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($bySemester !== []): ?>
    <div class="card mb-3 border-primary-subtle">
        <div class="card-body d-flex flex-wrap gap-4 small">
            <div><span class="text-muted"><?= e(__('curricula.plan_total')) ?>:</span> <strong><?= e($grand['total']) ?></strong> <?= e(__('curricula.hours_unit')) ?></div>
            <div><span class="text-muted"><?= e(__('curricula.hours_theory')) ?>:</span> <?= e($grand['theory']) ?></div>
            <div><span class="text-muted"><?= e(__('curricula.hours_practice')) ?>:</span> <?= e($grand['practice']) ?></div>
            <div><span class="text-muted"><?= e(__('curricula.hours_lab')) ?>:</span> <?= e($grand['lab']) ?></div>
            <div><span class="text-muted"><?= e(__('curricula.exams')) ?>:</span> <?= e($grand['exam']) ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <form method="post" action="<?= e(url('/curricula/' . $id . '/items')) ?>" novalidate class="card mb-3" id="addItem">
        <?= csrf_field() ?>
        <input type="hidden" name="_form" value="item">
        <div class="card-header fw-semibold"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i><?= e(__('curricula.add_item')) ?></div>
        <div class="card-body">
            <?= $this->partial('curricula/_item_fields', ['item' => [], 'subjects' => $subjects, 'controls' => $controls, 'semesters' => (int) $plan['semesters_count']]) ?>
        </div>
        <div class="card-footer bg-transparent"><button type="submit" class="btn btn-primary"><?= e(__('curricula.add_item')) ?></button></div>
    </form>
<?php endif; ?>

<?php if ($status === 'draft' && $canManage && (int) $plan['groups_count'] === 0): ?>
    <?= $statusButton('delete', __('curricula.delete'), 'btn-outline-danger btn-sm', __('curricula.delete_confirm')) ?>
<?php endif; ?>

<?php if ($canManage): ?>
    <div class="modal fade" id="copyModal" tabindex="-1" aria-labelledby="copyTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'copy' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/curricula/' . $id . '/copy')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_form" value="copy">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="copyTitle"><?= e(__('curricula.copy')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted"><?= e(__('curricula.copy_hint')) ?></p>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $openForm === 'copy', 'name' => 'admission_year', 'label' => __('curricula.intake'), 'type' => 'number', 'required' => true, 'value' => (int) $plan['admission_year'] + 1, 'attrs' => ['min' => 2000, 'max' => 2100]]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $openForm === 'copy', 'name' => 'title', 'label' => __('curricula.plan_title'), 'required' => true, 'value' => preg_replace('/\b' . $plan['admission_year'] . '\b/', (string) ((int) $plan['admission_year'] + 1), (string) $plan['title']), 'attrs' => ['maxlength' => 255]]) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= e(__('curricula.copy')) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
