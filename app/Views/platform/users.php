<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $college */
/** @var array{q: string, role: string} $filters */
/** @var list<array{slug: string, name: string}> $roles */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array{login: string, password: string}|null $tempPassword */
$c = $college;
$roleNames = array_column($roles, 'name', 'slug');
$canEnter = $c['status'] === 'active';
?>
<?= $this->partial('partials/page-header', ['heading' => __('platform.users_title'), 'subtitle' => $c['name'] . ' · ' . __('platform.users_count', ['count' => $result['total']]), 'back' => '/colleges/' . $c['id']]) ?>
<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card">
            <form method="get" action="<?= e(url('/colleges/' . $c['id'] . '/users')) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
                <div class="col-12 col-md-6"><label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('platform.users_search')) ?>"></div>
                <div class="col-8 col-md-4"><label class="form-label small text-muted mb-1" for="role"><?= e(__('fields.roles')) ?></label>
                    <select class="form-select" id="role" name="role"><option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach ($roles as $r): ?><option value="<?= e($r['slug']) ?>"<?= $filters['role'] === $r['slug'] ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-4 col-md-2"><button type="submit" class="btn btn-primary w-100"><?= e(__('common.filter')) ?></button></div>
            </form>
            <?php if ($result['items'] === []): ?>
                <div class="app-empty"><i class="bi bi-people" aria-hidden="true"></i><p class="mb-0"><?= e(__('platform.users_empty')) ?></p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 app-table small">
                        <thead class="table-light"><tr><th scope="col"><?= e(__('users.name')) ?></th><th scope="col"><?= e(__('fields.roles')) ?></th>
                            <th scope="col" class="d-none d-md-table-cell"><?= e(__('platform.last_login')) ?></th><th scope="col" class="text-end"><?= e(__('common.actions')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($result['items'] as $u): $slugs = array_filter(explode(',', (string) $u['roles'])); $isAdmin = array_intersect(App\Platform\CollegeManager::ADMIN_ROLES, $slugs) !== []; ?>
                            <tr class="<?= (int) $u['is_active'] === 1 ? '' : 'text-muted' ?>">
                                <td><span class="fw-semibold"><?= e(full_name($u)) ?></span><div class="text-muted"><?= e($u['login']) ?><?= (int) $u['is_active'] === 1 ? '' : ' · ' . e(__('common.inactive')) ?></div></td>
                                <td><?= e(implode(', ', array_map(static fn (string $s): string => $roleNames[$s] ?? $s, $slugs)) ?: '—') ?></td>
                                <td class="d-none d-md-table-cell text-nowrap"><?= e($u['last_login_at'] ? format_date($u['last_login_at'], 'd.m.Y H:i') : '—') ?></td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        <?php if ($isAdmin && $canEnter && (int) $u['is_active'] === 1): ?>
                                            <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/impersonate')) ?>" target="_blank" data-confirm="<?= e(__('platform.impersonate_confirm')) ?>"><?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><button type="submit" class="btn btn-sm btn-outline-dark" title="<?= e(__('platform.impersonate')) ?>" aria-label="<?= e(__('platform.impersonate')) ?>"><i class="bi bi-incognito" aria-hidden="true"></i></button></form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/users/' . $u['id'] . '/password')) ?>" data-confirm="<?= e(__('platform.admin_password_confirm', ['login' => $u['login']])) ?>"><?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="<?= e(__('platform.user_password')) ?>" aria-label="<?= e(__('platform.user_password')) ?>"><i class="bi bi-key" aria-hidden="true"></i></button></form>
                                        <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/users/' . $u['id'] . '/active')) ?>"<?= (int) $u['is_active'] === 1 ? ' data-confirm="' . e(__('platform.user_block_confirm', ['login' => $u['login']])) . '" data-confirm-danger' : '' ?>><?= csrf_field() ?>
                                            <input type="hidden" name="active" value="<?= (int) $u['is_active'] === 1 ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= (int) $u['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= e(__((int) $u['is_active'] === 1 ? 'platform.block' : 'platform.unblock')) ?></button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/colleges/' . $c['id'] . '/users']) ?>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('platform.add_admin')) ?></div>
            <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/users')) ?>" novalidate class="card-body">
                <?= csrf_field() ?>
                <?= $this->partial('partials/field', ['name' => 'last_name', 'label' => __('fields.last_name'), 'required' => true, 'attrs' => ['maxlength' => 100]]) ?>
                <?= $this->partial('partials/field', ['name' => 'first_name', 'label' => __('fields.first_name'), 'required' => true, 'attrs' => ['maxlength' => 100]]) ?>
                <?= $this->partial('partials/field', ['name' => 'login', 'label' => __('fields.login'), 'help' => __('teachers.login_auto'), 'attrs' => ['maxlength' => 64, 'autocapitalize' => 'none']]) ?>
                <?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'attrs' => ['maxlength' => 190]]) ?>
                <?= $this->partial('partials/field', ['name' => 'role', 'label' => __('fields.roles'), 'type' => 'select', 'value' => 'admin', 'options' => ['admin' => $roleNames['admin'] ?? 'admin', 'super_admin' => $roleNames['super_admin'] ?? 'super_admin'], 'required' => true]) ?>
                <button type="submit" class="btn btn-primary"><?= e(__('common.create')) ?></button>
            </form>
        </div>
    </div>
</div>
