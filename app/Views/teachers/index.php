<?php
/** @var App\Core\View $this */
/** @var array{q: string, department: ?int, status: string} $filters */
/** @var array<int, string> $departments */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array{login: string, password: string}|null $tempPassword */
$canManage = can('teachers.manage');
$statusClass = ['active' => 'app-badge-success', 'on_leave' => 'app-badge-warning', 'dismissed' => 'app-badge-danger'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('teachers.title'),
    'actions' => $canManage ? '<a href="' . e(url('/import/teachers')) . '" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . e(__('import.button')) . '</a>'
        . '<a href="' . e(url('/teachers/create')) . '" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>' . e(__('teachers.create')) . '</a>' : '',
]) ?>
<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/teachers')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-xl-5">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('teachers.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-3">
            <label class="form-label small text-muted mb-1" for="department"><?= e(__('fields.department_id')) ?></label>
            <select class="form-select" id="department" name="department">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $id => $label): ?>
                    <option value="<?= e($id) ?>"<?= $filters['department'] === $id ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (['active', 'on_leave', 'dismissed'] as $status): ?>
                    <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(__('teachers.statuses.' . $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/teachers')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-person-badge" aria-hidden="true"></i><p class="mb-0"><?= e(__('teachers.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('users.name')) ?></th>
                    <th scope="col"><?= e(__('fields.department_id')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.position')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('teachers.curated_groups')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/teachers/' . $row['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(full_name($row)) ?></a>
                            <div class="small text-muted"><?= e($row['login']) ?><?= $row['employee_number'] ? ' · №' . e($row['employee_number']) : '' ?></div>
                        </td>
                        <td class="small"><?= e($row['department_name_ru'] !== null ? localized(['name_ru' => $row['department_name_ru'], 'name_kk' => $row['department_name_kk']]) : '—') ?></td>
                        <td class="small d-none d-lg-table-cell"><?= e($row['position'] ?: '—') ?></td>
                        <td class="small d-none d-md-table-cell"><?= e($row['curated_groups'] ?: '—') ?></td>
                        <td><span class="badge <?= e($statusClass[$row['status']] ?? '') ?>"><?= e(__('teachers.statuses.' . $row['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/teachers']) ?>
</div>
