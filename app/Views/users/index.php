<?php
/** @var App\Core\View $this */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array{q: string, role: ?int, status: string} $filters */
/** @var list<array<string, mixed>> $roles */
/** @var array{login: string, password: string}|null $tempPassword */
$roleNames = [];
foreach ($roles as $role) {
    $roleNames[$role['slug']] = localized($role);
}
$canManage = can('users.manage');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('users.title'),
    'actions' => $canManage
        ? '<a href="' . e(url('/users/create')) . '" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>' . e(__('users.create')) . '</a>'
        : '',
]) ?>

<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/users')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-xl-5">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('users.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-3">
            <label class="form-label small text-muted mb-1" for="role"><?= e(__('fields.roles')) ?></label>
            <select class="form-select" id="role" name="role">
                <option value=""><?= e(__('users.all_roles')) ?></option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['id']) ?>"<?= $filters['role'] === (int) $role['id'] ? ' selected' : '' ?>><?= e(localized($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= e(__('users.all_statuses')) ?></option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>><?= e(__('common.active')) ?></option>
                <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>><?= e(__('common.inactive')) ?></option>
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/users')) ?>" class="btn btn-light" title="<?= e(__('common.reset')) ?>" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-people" aria-hidden="true"></i><p class="mb-0"><?= e(__('users.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('users.name')) ?></th>
                    <th scope="col"><?= e(__('fields.roles')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('users.last_login')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                    <?php if ($canManage): ?><th scope="col" class="text-end"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <?php $slugs = $row['role_slugs'] ? explode(',', (string) $row['role_slugs']) : []; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e(full_name($row)) ?></div>
                            <div class="small text-muted"><?= e($row['login']) ?><?= $row['email'] ? ' · ' . e($row['email']) : '' ?></div>
                        </td>
                        <td>
                            <?php if ($slugs === []): ?>
                                <span class="small text-muted"><?= e(__('users.no_roles')) ?></span>
                            <?php endif; ?>
                            <?php foreach ($slugs as $slug): ?>
                                <span class="badge rounded-pill app-role-badge mb-1"><?= e($roleNames[$slug] ?? $slug) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="small text-muted d-none d-lg-table-cell text-nowrap">
                            <?= e($row['last_login_at'] ? format_date($row['last_login_at'], 'd.m.Y H:i') : __('users.never')) ?>
                        </td>
                        <td>
                            <?php if ((int) $row['is_active'] === 1): ?>
                                <span class="badge text-bg-success-subtle app-badge-success"><?= e(__('common.active')) ?></span>
                            <?php else: ?>
                                <span class="badge app-badge-danger"><?= e(__('common.inactive')) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $row['must_change_password'] === 1): ?>
                                <div class="small text-warning-emphasis mt-1"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('users.must_change_badge')) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if ($canManage): ?>
                            <td class="text-end">
                                <a href="<?= e(url('/users/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-light" aria-label="<?= e(__('common.edit') . ': ' . full_name($row)) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/users']) ?>
</div>
