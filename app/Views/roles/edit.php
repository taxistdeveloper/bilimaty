<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $role */
/** @var array<string, list<array<string, mixed>>> $modules */
/** @var list<int> $granted */
/** @var list<string> $grantable */
/** @var bool $canEdit */
/** @var bool $canDelete */
$isSuper = $role['slug'] === App\Core\Auth::SUPER_ADMIN;
$oldPermissions = old('permissions', null);
$checked = is_array($oldPermissions) ? array_map('intval', $oldPermissions) : $granted;
$someLocked = count($grantable) < array_sum(array_map('count', $modules));
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized($role),
    'subtitle' => $role['slug'] . ' · ' . ((int) $role['is_system'] === 1 ? __('roles.system') : __('roles.custom')),
    'back' => '/roles',
]) ?>

<?php if ($isSuper): ?>
    <div class="alert alert-info d-flex gap-2"><i class="bi bi-infinity" aria-hidden="true"></i><div><?= e(__('roles.super_admin_note')) ?></div></div>
<?php elseif (!$canEdit): ?>
    <div class="alert alert-warning"><?= e(__('roles.readonly_note')) ?></div>
<?php elseif ($someLocked): ?>
    <div class="alert alert-light border small"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('roles.not_grantable')) ?></div>
<?php endif; ?>

<?php if (!$isSuper): ?>
<form method="post" action="<?= e(url('/roles/' . $role['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>
    <fieldset<?= $canEdit ? '' : ' disabled' ?>>
        <div class="card mb-3">
            <div class="card-body row">
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'name_ru', 'label' => __('fields.name_ru'), 'value' => $role['name_ru'], 'required' => true, 'attrs' => ['maxlength' => 150]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'name_kk', 'label' => __('fields.name_kk'), 'value' => $role['name_kk'], 'required' => true, 'attrs' => ['maxlength' => 150]]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'description', 'label' => __('fields.description'), 'value' => $role['description'], 'attrs' => ['maxlength' => 255]]) ?></div>
            </div>
        </div>

        <h3 class="h6 fw-bold mb-3"><?= e(__('roles.matrix')) ?></h3>
        <div class="row g-3 mb-3">
            <?php foreach ($modules as $module => $permissions): ?>
                <div class="col-12 col-md-6 col-xxl-4">
                    <div class="card h-100" data-permission-group>
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <span class="fw-semibold"><?= e(__('roles.modules.' . $module)) ?></span>
                            <input class="form-check-input m-0" type="checkbox" data-group-toggle
                                   aria-label="<?= e(__('roles.select_module') . ': ' . __('roles.modules.' . $module)) ?>">
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($permissions as $permission): ?>
                                <?php
                                $pid = (int) $permission['id'];
                                $locked = !in_array($permission['slug'], $grantable, true);
                                $inputId = 'perm_' . $pid;
                                ?>
                                <li class="list-group-item">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="<?= e($inputId) ?>"
                                               name="permissions[]" value="<?= e($pid) ?>"
                                               <?= in_array($pid, $checked, true) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                                        <label class="form-check-label small" for="<?= e($inputId) ?>">
                                            <?= e(localized($permission)) ?>
                                            <?php if ($locked): ?><i class="bi bi-lock text-muted ms-1" aria-hidden="true"></i><?php endif; ?>
                                            <span class="d-block text-muted app-perm-slug"><?= e($permission['slug']) ?></span>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($canEdit): ?>
            <div class="d-flex gap-2 app-sticky-actions">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('common.save')) ?></button>
                <a href="<?= e(url('/roles')) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        <?php endif; ?>
    </fieldset>
</form>
<?php endif; ?>

<?php if ((int) $role['is_system'] === 0): ?>
    <div class="card mt-4 border-danger-subtle">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <span class="small text-muted"><?= e($canDelete ? __('roles.delete_confirm') : __('roles.cannot_delete')) ?></span>
            <?php if ($canDelete): ?>
                <form method="post" action="<?= e(url('/roles/' . $role['id'])) ?>" data-confirm="<?= e(__('roles.delete_confirm')) ?>" data-confirm-danger>
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
