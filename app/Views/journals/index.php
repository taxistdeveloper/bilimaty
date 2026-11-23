<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var bool $mine */
/** @var list<array<string, mixed>> $journals */
/** @var array<string, array<int, string>> $options */
/** @var bool $canAdminister */
$statusClass = ['open' => 'app-badge-primary', 'submitted' => 'app-badge-warning', 'approved' => 'app-badge-success'];
$select = static function (string $name, string $label, array $items, mixed $current, bool $all = true): string {
    $html = '<label class="form-label small text-muted mb-1" for="jf_' . e($name) . '">' . e($label) . '</label><select class="form-select" id="jf_' . e($name) . '" name="' . e($name) . '">';
    if ($all) {
        $html .= '<option value="">' . e(__('common.all')) . '</option>';
    }
    foreach ($items as $v => $t) {
        $html .= '<option value="' . e($v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($t) . '</option>';
    }

    return $html . '</select>';
};
$hasErrors = errors() !== [];
$openForm = (string) old('_form', '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('nav.journal'),
    'subtitle' => __('journal.subtitle'),
    'actions' => $canAdminister
        ? '<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="bi bi-magic me-1" aria-hidden="true"></i>' . e(__('journal.generate')) . '</button>'
          . '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('journal.create')) . '</button>'
        : '',
]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/journal')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3"><?= $select('semester', __('fields.semester'), $options['semesters'], $filters['semester'], false) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $select('group', __('fields.group_id'), $options['groups'], $filters['group']) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $select('teacher', __('teachers.one'), $options['teachers'], $filters['teacher']) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $select('status', __('common.status'), ['open' => __('journal.statuses.open'), 'submitted' => __('journal.statuses.submitted'), 'approved' => __('journal.statuses.approved')], $filters['status']) ?></div>
        <div class="col-6 col-md-4 col-xl-1 d-flex align-items-center pb-2">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="jf_mine" name="mine" value="1"<?= $mine ? ' checked' : '' ?>>
                <label class="form-check-label small" for="jf_mine"><?= e(__('journal.only_mine')) ?></label>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/journal')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>
    <?php if ($journals === []): ?>
        <div class="app-empty"><i class="bi bi-journal-bookmark" aria-hidden="true"></i><p class="mb-0"><?= e(__('journal.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('curricula.subject')) ?></th>
                    <th scope="col"><?= e(__('fields.group_id')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('teachers.one')) ?></th>
                    <th scope="col" class="text-center d-none d-lg-table-cell"><?= e(__('journal.lessons')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('journal.scheme')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($journals as $j): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/journal/' . $j['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']])) ?></a>
                            <div class="small text-muted"><?= e($j['subject_code']) ?> · <?= e(str_replace('-', '–', (string) $j['year_title'])) ?>, <?= e(__('dashboard.semester', ['number' => $j['semester_number']])) ?></div>
                        </td>
                        <td><?= e($j['group_code']) ?></td>
                        <td class="small d-none d-md-table-cell"><?= e(full_name(['last_name' => $j['teacher_last_name'], 'first_name' => $j['teacher_first_name'], 'middle_name' => $j['teacher_middle_name']], true)) ?></td>
                        <td class="text-center d-none d-lg-table-cell"><?= e($j['lessons_count']) ?></td>
                        <td class="small d-none d-lg-table-cell"><?= $j['scheme_title'] !== null ? e($j['scheme_title'] . ' v' . $j['scheme_version']) : '<span class="text-warning-emphasis">' . e(__('journal.no_scheme_short')) . '</span>' ?></td>
                        <td><span class="badge <?= e($statusClass[$j['status']] ?? '') ?>"><?= e(__('journal.statuses.' . $j['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($canAdminister): ?>
    <div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'create' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/journal')) ?>" novalidate>
                <?= csrf_field() ?><input type="hidden" name="_form" value="create">
                <div class="modal-header"><h2 class="modal-title h5" id="createTitle"><?= e(__('journal.create')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body">
                    <?php $own = $openForm === 'create'; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $own, 'name' => 'semester_id', 'label' => __('fields.semester'), 'type' => 'select', 'required' => true, 'value' => $filters['semester'] ?? '', 'options' => $options['semesters']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $own, 'name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'required' => true, 'options' => ['' => __('common.choose')] + $options['groups']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $own, 'name' => 'subject_id', 'label' => __('curricula.subject'), 'type' => 'select', 'required' => true, 'options' => ['' => __('common.choose')] + $options['subjects']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $own, 'name' => 'teacher_id', 'label' => __('journal.responsible'), 'type' => 'select', 'required' => true, 'options' => ['' => __('common.choose')] + $options['teachers']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'c_', 'ownsInput' => $own, 'name' => 'grading_scheme_id', 'label' => __('journal.scheme'), 'type' => 'select', 'options' => ['' => '—'] + $options['schemes'], 'help' => __('journal.scheme_hint')]) ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('common.create')) ?></button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="generateModal" tabindex="-1" aria-labelledby="generateTitle" aria-hidden="true"<?= $hasErrors && $openForm === 'generate' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/journal/generate')) ?>" novalidate>
                <?= csrf_field() ?><input type="hidden" name="_form" value="generate">
                <div class="modal-header"><h2 class="modal-title h5" id="generateTitle"><?= e(__('journal.generate')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body">
                    <p class="small text-muted"><?= e(__('journal.generate_hint')) ?></p>
                    <?php $own = $openForm === 'generate'; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'g_', 'ownsInput' => $own, 'name' => 'semester_id', 'label' => __('fields.semester'), 'type' => 'select', 'required' => true, 'value' => $filters['semester'] ?? '', 'options' => $options['semesters']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'g_', 'ownsInput' => $own, 'name' => 'grading_scheme_id', 'label' => __('journal.scheme'), 'type' => 'select', 'options' => ['' => '—'] + $options['schemes']]) ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('journal.generate')) ?></button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
