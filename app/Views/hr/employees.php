<?php
/** @var App\Core\View $this */
/** @var array{q: string, department: ?int, category: string, status: string} $filters */
/** @var array<int, string> $departments */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
$canManage = can('hr.manage');
$statusClass = ['active' => 'app-badge-success', 'on_leave' => 'app-badge-warning', 'dismissed' => 'app-badge-danger'];
$csv = url('/hr/employees?' . http_build_query(array_filter($filters + ['format' => 'csv'], static fn ($v): bool => $v !== null && $v !== '')));
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('hr.employees'),
    'subtitle' => __('hr.employees_count', ['count' => $result['total']]),
    'actions' => '<a href="' . e($csv) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>'
        . ($canManage ? '<a href="' . e(url('/hr/employees/create')) . '" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>' . e(__('hr.employee_create')) . '</a>' : ''),
]) ?>
<?= $this->partial('hr/_nav', ['active' => 'employees']) ?>

<div class="card">
    <form method="get" action="<?= e(url('/hr/employees')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-xl-4">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('hr.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-3">
            <label class="form-label small text-muted mb-1" for="department"><?= e(__('fields.department_id')) ?></label>
            <select class="form-select" id="department" name="department">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $id => $label): ?>
                    <option value="<?= e($id) ?>"<?= $filters['department'] === $id ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="category"><?= e(__('fields.staff_category')) ?></label>
            <select class="form-select" id="category" name="category">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (App\Services\HrService::CATEGORIES as $c): ?>
                    <option value="<?= e($c) ?>"<?= $filters['category'] === $c ? ' selected' : '' ?>><?= e(__('hr.staff_categories.' . $c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-1">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['working', 'dismissed', 'all'] as $s): ?>
                    <option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(__('hr.filter_status.' . $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/hr/employees')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-person-vcard" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('users.name')) ?></th>
                    <th scope="col"><?= e(__('fields.department_id')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.position')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell text-end"><?= e(__('fields.rate')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.hire_date')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/hr/employees/' . $row['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(full_name($row)) ?></a>
                            <div class="small text-muted">№ <?= e($row['personnel_number']) ?> · <?= e(__('hr.staff_categories.' . $row['staff_category'])) ?><?= $row['employment_type'] !== 'main' ? ' · ' . e(__('hr.employment_types.' . $row['employment_type'])) : '' ?></div>
                        </td>
                        <td class="small"><?= e($row['department_name_ru'] !== null ? localized(['name_ru' => $row['department_name_ru'], 'name_kk' => $row['department_name_kk']]) : '—') ?></td>
                        <td class="small d-none d-lg-table-cell"><?= e($row['position'] ?: '—') ?></td>
                        <td class="small d-none d-md-table-cell text-end"><?= e($row['rate']) ?></td>
                        <td class="small d-none d-lg-table-cell"><?= e(format_date($row['hire_date']) ?: '—') ?></td>
                        <td><span class="badge <?= e($statusClass[$row['status']] ?? '') ?>"><?= e(__('hr.statuses.' . $row['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/hr/employees']) ?>
</div>
