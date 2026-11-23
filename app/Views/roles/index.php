<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $roles */
$hasErrors = errors() !== [];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('roles.title'),
    'actions' => '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleCreateModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('roles.create')) . '</button>',
]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 app-table">
            <thead class="table-light">
            <tr>
                <th scope="col"><?= e(__('roles.edit')) ?></th>
                <th scope="col" class="text-center"><?= e(__('roles.users_count')) ?></th>
                <th scope="col" class="text-center"><?= e(__('roles.permissions_count')) ?></th>
                <th scope="col" class="text-end"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e(localized($role)) ?></div>
                        <div class="small text-muted">
                            <code><?= e($role['slug']) ?></code> ·
                            <?= e((int) $role['is_system'] === 1 ? __('roles.system') : __('roles.custom')) ?>
                        </div>
                    </td>
                    <td class="text-center"><?= e($role['users_count']) ?></td>
                    <td class="text-center">
                        <?= $role['slug'] === App\Core\Auth::SUPER_ADMIN ? '<i class="bi bi-infinity" title="' . e(__('roles.super_admin_note')) . '"></i>' : e($role['permissions_count']) ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= e(url('/roles/' . $role['id'] . '/edit')) ?>" class="btn btn-sm btn-light" aria-label="<?= e(__('common.edit') . ': ' . localized($role)) ?>">
                            <i class="bi bi-sliders" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="roleCreateModal" tabindex="-1" aria-labelledby="roleCreateTitle" aria-hidden="true"<?= $hasErrors ? ' data-show-on-load' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" method="post" action="<?= e(url('/roles')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="roleCreateTitle"><?= e(__('roles.create')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <?= $this->partial('partials/field', ['name' => 'slug', 'label' => __('fields.slug'), 'required' => true, 'help' => __('roles.slug_hint'), 'attrs' => ['maxlength' => 50, 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
                <?= $this->partial('partials/field', ['name' => 'name_ru', 'label' => __('fields.name_ru'), 'required' => true, 'attrs' => ['maxlength' => 150]]) ?>
                <?= $this->partial('partials/field', ['name' => 'name_kk', 'label' => __('fields.name_kk'), 'required' => true, 'attrs' => ['maxlength' => 150]]) ?>
                <?= $this->partial('partials/field', ['name' => 'description', 'label' => __('fields.description'), 'attrs' => ['maxlength' => 255]]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.create')) ?></button>
            </div>
        </form>
    </div>
</div>
