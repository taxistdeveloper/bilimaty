<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array<int, string> $departments */
/** @var array<int, string> $specialties */
/** @var array<int, string> $years */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
$statusClass = ['active' => 'app-badge-success', 'graduated' => 'app-badge-primary', 'archived' => 'app-badge-muted', 'disbanded' => 'app-badge-danger'];
$select = static function (string $name, string $label, array $options, mixed $current): string {
    $html = '<label class="form-label small text-muted mb-1" for="f_' . e($name) . '">' . e($label) . '</label>'
        . '<select class="form-select" id="f_' . e($name) . '" name="' . e($name) . '"><option value="">' . e(__('common.all')) . '</option>';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . e($value) . '"' . ((string) $current === (string) $value ? ' selected' : '') . '>' . e($text) . '</option>';
    }

    return $html . '</select>';
};
$courses = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
$statuses = [];
foreach (array_keys($statusClass) as $status) {
    $statuses[$status] = __('groups.statuses.' . $status);
}
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('groups.title'),
    'actions' => can('groups.manage') ? '<a href="' . e(url('/groups/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('groups.create')) . '</a>' : '',
]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/groups')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('groups.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-6 col-xl-3"><?= $select('department', __('fields.department_id'), $departments, $filters['department']) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $select('specialty', __('fields.specialty_id'), $specialties, $filters['specialty']) ?></div>
        <div class="col-4 col-md-2 col-xl-1"><?= $select('course', __('fields.course'), $courses, $filters['course']) ?></div>
        <div class="col-8 col-md-3 col-xl-1"><?= $select('status', __('common.status'), $statuses, $filters['status']) ?></div>
        <div class="col-12 col-md-3 col-xl-2 d-flex gap-2">
            <input type="hidden" name="year" value="<?= e($filters['year'] ?? '') ?>">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/groups')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-collection" aria-hidden="true"></i><p class="mb-0"><?= e(__('groups.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('groups.one')) ?></th>
                    <th scope="col"><?= e(__('fields.specialty_id')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('fields.course')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.curator_teacher_id')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('groups.students')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/groups/' . $row['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($row['code']) ?></a>
                            <div class="small text-muted"><?= e($row['name']) ?> · <?= e(__('groups.languages.' . $row['study_language'])) ?></div>
                        </td>
                        <td class="small">
                            <div><?= e($row['specialty_code']) ?></div>
                            <div class="text-muted"><?= e(localized(['name_ru' => $row['specialty_name_ru'], 'name_kk' => $row['specialty_name_kk']])) ?></div>
                        </td>
                        <td class="text-center"><?= e($row['course']) ?></td>
                        <td class="small d-none d-lg-table-cell">
                            <?= e($row['curator_last_name'] !== null ? full_name(['last_name' => $row['curator_last_name'], 'first_name' => $row['curator_first_name'], 'middle_name' => $row['curator_middle_name']], true) : '—') ?>
                        </td>
                        <td class="text-center"><?= e($row['students_count']) ?></td>
                        <td><span class="badge <?= e($statusClass[$row['status']] ?? '') ?>"><?= e(__('groups.statuses.' . $row['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/groups']) ?>
</div>
