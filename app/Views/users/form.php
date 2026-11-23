<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $roles */
/** @var list<int> $userRoleIds */
/** @var list<int> $assignable */
/** @var bool $canChangeRoles */
/** @var bool $canChangeStatus */
/** @var bool $canResetPassword */
$isNew = $user === null;
$languages = ['kk' => 'Қазақша', 'ru' => 'Русский'];
$oldRoles = old('roles', null);
$checkedRoles = is_array($oldRoles) ? array_map('intval', $oldRoles) : $userRoleIds;
$rolesError = error('roles');
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('users.create') : full_name($user),
    'subtitle' => $isNew ? null : __('users.edit'),
    'back' => '/users',
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="post" action="<?= e(url($isNew ? '/users' : '/users/' . $user['id'])) ?>" novalidate id="userForm">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('users.section_personal')) ?></div>
                <div class="card-body row">
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'last_name', 'label' => __('fields.last_name'), 'value' => $user['last_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'first_name', 'label' => __('fields.first_name'), 'value' => $user['first_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'middle_name', 'label' => __('fields.middle_name'), 'value' => $user['middle_name'] ?? '', 'attrs' => ['maxlength' => 100]]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $user['email'] ?? '', 'attrs' => ['maxlength' => 190]]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $user['phone'] ?? '', 'attrs' => ['maxlength' => 20, 'placeholder' => '+7 700 000 00 00']]) ?></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('users.section_account')) ?></div>
                <div class="card-body row">
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'login', 'label' => __('fields.login'), 'value' => $user['login'] ?? '', 'required' => true, 'help' => __('users.login_hint'), 'attrs' => ['maxlength' => 64, 'autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?></div>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'locale', 'label' => __('fields.locale'), 'type' => 'select', 'value' => $user['locale'] ?? locale(), 'options' => $languages, 'required' => true]) ?></div>
                    <?php if ($isNew): ?>
                        <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'password', 'label' => __('users.password_optional'), 'type' => 'password', 'help' => __('users.password_optional_hint'), 'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 255]]) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('users.section_access')) ?></div>
                <div class="card-body">
                    <?php if (!$canChangeRoles): ?>
                        <p class="small text-muted"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('users.roles_locked_self')) ?></p>
                    <?php else: ?>
                        <p class="small text-muted"><?= e(__('users.roles_hint')) ?></p>
                    <?php endif; ?>
                    <fieldset class="row g-2"<?= $canChangeRoles ? '' : ' disabled' ?> aria-describedby="<?= $rolesError ? 'rolesError' : '' ?>">
                        <legend class="visually-hidden"><?= e(__('fields.roles')) ?></legend>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $roleId = (int) $role['id'];
                            $locked = !in_array($roleId, $assignable, true);
                            $checked = in_array($roleId, $checkedRoles, true);
                            ?>
                            <div class="col-sm-6">
                                <label class="app-check-card<?= $locked ? ' is-locked' : '' ?>">
                                    <input class="form-check-input mt-0" type="checkbox" name="roles[]" value="<?= e($roleId) ?>"
                                        <?= $checked ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                                    <span>
                                        <span class="d-block fw-semibold small"><?= e(localized($role)) ?></span>
                                        <?php if ($locked): ?><span class="d-block small text-muted"><?= e(__('users.role_locked')) ?></span><?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if ($rolesError): ?><div class="invalid-feedback d-block" id="rolesError"><?= e($rolesError) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url('/users')) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </form>
    </div>

    <?php if (!$isNew && ($canChangeStatus || $canResetPassword)): ?>
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('common.actions')) ?></div>
                <div class="card-body d-grid gap-2">
                    <div class="small text-muted mb-1">
                        <?= e(__('common.status')) ?>:
                        <strong class="<?= (int) $user['is_active'] === 1 ? 'text-success' : 'text-danger' ?>">
                            <?= e((int) $user['is_active'] === 1 ? __('common.active') : __('common.inactive')) ?>
                        </strong>
                    </div>
                    <?php if ($canResetPassword): ?>
                        <form method="post" action="<?= e(url('/users/' . $user['id'] . '/reset-password')) ?>" data-confirm="<?= e(__('users.reset_confirm')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('users.reset_password')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canChangeStatus): ?>
                        <form method="post" action="<?= e(url('/users/' . $user['id'] . '/toggle-active')) ?>"
                              <?= (int) $user['is_active'] === 1 ? 'data-confirm="' . e(__('users.deactivate_confirm')) . '" data-confirm-danger' : '' ?>>
                            <?= csrf_field() ?>
                            <?php if ((int) $user['is_active'] === 1): ?>
                                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('users.deactivate')) ?></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-outline-success w-100"><i class="bi bi-unlock me-1" aria-hidden="true"></i><?= e(__('users.activate')) ?></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
