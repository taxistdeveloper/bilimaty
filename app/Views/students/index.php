<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array<int, string> $groups */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array{login: string, password: string}|null $tempPassword */
$statusClass = ['active' => 'app-badge-success', 'academic_leave' => 'app-badge-warning', 'expelled' => 'app-badge-danger', 'graduated' => 'app-badge-primary', 'transferred' => 'app-badge-muted'];
$createUrl = url('/students/create') . ($filters['group'] ? '?group=' . (int) $filters['group'] : '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('students.title'),
    'actions' => can('students.manage') ? '<a href="' . e(url('/import/students')) . '" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . e(__('import.button')) . '</a>'
        . '<a href="' . e($createUrl) . '" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>' . e(__('students.create')) . '</a>' : '',
]) ?>
<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/students')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-xl-4">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(can('students.manage') ? __('students.search_placeholder_iin') : __('students.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="group"><?= e(__('fields.group_id')) ?></label>
            <select class="form-select" id="group" name="group">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($groups as $id => $code): ?>
                    <option value="<?= e($id) ?>"<?= $filters['group'] === $id ? ' selected' : '' ?>><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="course"><?= e(__('fields.course')) ?></label>
            <select class="form-select" id="course" name="course">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php for ($c = 1; $c <= 4; $c++): ?>
                    <option value="<?= e($c) ?>"<?= $filters['course'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (array_keys($statusClass) as $status): ?>
                    <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(__('students.statuses.' . $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/students')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-people" aria-hidden="true"></i><p class="mb-0"><?= e(__('students.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('users.name')) ?></th>
                    <th scope="col"><?= e(__('fields.group_id')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('fields.specialty_id')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('fields.funding')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/students/' . $row['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(full_name($row)) ?></a>
                            <div class="small text-muted"><?= e($row['student_number']) ?></div>
                        </td>
                        <td>
                            <?php if ($row['group_code'] !== null): ?>
                                <a href="<?= e(url('/groups/' . $row['current_group_id'])) ?>" class="text-decoration-none"><?= e($row['group_code']) ?></a>
                                <div class="small text-muted"><?= e(__('groups.course_n', ['n' => $row['course']])) ?></div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small d-none d-lg-table-cell"><?= e($row['specialty_code'] ?? '—') ?></td>
                        <td class="small d-none d-md-table-cell"><?= e($row['funding'] ? __('students.funding.' . $row['funding']) : '—') ?></td>
                        <td><span class="badge <?= e($statusClass[$row['status']] ?? '') ?>"><?= e(__('students.statuses.' . $row['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/students']) ?>
</div>
