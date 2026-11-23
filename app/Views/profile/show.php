<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $user */
/** @var list<array<string, mixed>> $roles */
$languages = ['kk' => 'Қазақша', 'ru' => 'Русский'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('profile.title'),
    'actions' => '<a href="' . e(url('/profile/password')) . '" class="btn btn-outline-primary"><i class="bi bi-key me-1" aria-hidden="true"></i>' . e(__('profile.change_password')) . '</a>',
]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body d-flex flex-column align-items-center text-center p-4">
                <span class="app-avatar app-avatar--xl mb-3" aria-hidden="true">
                    <?= e(mb_strtoupper(mb_substr((string) $user['last_name'], 0, 1) . mb_substr((string) $user['first_name'], 0, 1))) ?>
                </span>
                <h3 class="h5 fw-bold mb-1"><?= e(full_name($user)) ?></h3>
                <div class="text-muted small mb-3"><?= e($user['login']) ?></div>
                <div class="d-flex flex-wrap justify-content-center gap-1">
                    <?php foreach ($roles as $role): ?>
                        <span class="badge rounded-pill app-role-badge"><?= e(localized($role)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between gap-3 px-4">
                    <span class="text-muted"><?= e(__('profile.last_login')) ?></span>
                    <span><?= e($user['last_login_at'] ? format_date($user['last_login_at'], 'd.m.Y H:i') : __('profile.never')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3 px-4">
                    <span class="text-muted"><?= e(__('profile.password_changed_at')) ?></span>
                    <span><?= e(format_date($user['password_changed_at'], 'd.m.Y H:i')) ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('profile.personal')) ?></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.last_name')) ?></dt>
                    <dd class="col-sm-8"><?= e($user['last_name']) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.first_name')) ?></dt>
                    <dd class="col-sm-8"><?= e($user['first_name']) ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.middle_name')) ?></dt>
                    <dd class="col-sm-8"><?= e($user['middle_name'] ?: '—') ?></dd>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e(__('profile.login')) ?></dt>
                    <dd class="col-sm-8 mb-0"><?= e($user['login']) ?></dd>
                </dl>
                <p class="form-text mb-0 mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('profile.personal_hint')) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('profile.contacts')) ?></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/profile')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?= method_field('PUT') ?>
                    <div class="row">
                        <div class="col-md-6">
                            <?= $this->partial('partials/field', ['name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $user['email'], 'attrs' => ['maxlength' => 190, 'autocomplete' => 'email']]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $this->partial('partials/field', ['name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $user['phone'], 'attrs' => ['maxlength' => 20, 'autocomplete' => 'tel', 'placeholder' => '+7 700 000 00 00']]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $this->partial('partials/field', ['name' => 'locale', 'label' => __('fields.locale'), 'type' => 'select', 'value' => $user['locale'], 'options' => $languages, 'required' => true]) ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('common.save')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
