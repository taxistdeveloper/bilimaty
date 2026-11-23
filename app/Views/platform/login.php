<?php
/** @var App\Core\View $this */
?>
<div class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card shadow-sm" style="max-width: 420px; width: 100%">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <?= $this->partial('layouts/partials/logo') ?>
                <span class="fw-bold"><?= e(config('app.name')) ?></span>
                <span class="badge text-bg-dark ms-1"><?= e(__('platform.owner')) ?></span>
                <div class="ms-auto"><?= $this->partial('layouts/partials/lang-switch', ['class' => 'btn-outline-secondary btn-sm']) ?></div>
            </div>
            <h1 class="h4 fw-bold mb-1"><?= e(__('platform.login_title')) ?></h1>
            <p class="text-muted small mb-4"><?= e(__('platform.login_hint')) ?></p>
            <form method="post" action="<?= e(url('/login')) ?>" novalidate>
                <?= csrf_field() ?>
                <?= $this->partial('partials/field', ['name' => 'login', 'label' => __('auth.login'), 'required' => true, 'attrs' => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'maxlength' => 64]]) ?>
                <?= $this->partial('partials/field', ['name' => 'password', 'label' => __('auth.password'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
                <button type="submit" class="btn btn-primary w-100" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('auth.submit')) ?></button>
            </form>
        </div>
    </div>
</div>
