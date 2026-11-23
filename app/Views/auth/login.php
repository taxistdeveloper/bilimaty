<?php
/** @var App\Core\View $this */
$loginError = error('login');
$passwordError = error('password');
?>
<div class="auth-shell">
    <section class="auth-promo d-none d-lg-flex" aria-hidden="true">
        <div class="auth-promo__inner">
            <div class="d-flex align-items-center gap-2 mb-5">
                <?= $this->partial('layouts/partials/logo', ['size' => 'lg']) ?>
                <span class="fs-4 fw-bold text-white"><?= e(config('app.name')) ?></span>
            </div>
            <h2 class="auth-promo__title"><?= e(__('auth.promo_title')) ?></h2>
            <ul class="list-unstyled auth-promo__list">
                <?php foreach (explode('|', __('auth.promo_items')) as $item): ?>
                    <li><i class="bi bi-check2-circle"></i><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <img src="<?= e(asset('assets/images/mascot-wave.png')) ?>" alt="" class="auth-promo__mascot" width="372" height="480">
        </div>
        <div class="auth-promo__grid"></div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel__top">
            <div class="d-flex align-items-center gap-2 d-lg-none">
                <?= $this->partial('layouts/partials/logo') ?>
                <span class="fw-bold"><?= e(config('app.name')) ?></span>
            </div>
            <div class="ms-auto">
                <?= $this->partial('layouts/partials/lang-switch', ['class' => 'btn-outline-secondary btn-sm']) ?>
            </div>
        </div>

        <div class="auth-form">
            <img src="<?= e(asset('assets/images/mascot-wave.png')) ?>" alt="" class="auth-mascot d-lg-none" width="93" height="120">
            <?php $collegeName = App\Services\Settings::collegeName(); ?>
            <?php if ($collegeName !== ''): ?><div class="small fw-semibold text-primary mb-2"><?= e($collegeName) ?></div><?php endif; ?>
            <h1 class="h3 fw-bold mb-1"><?= e(__('auth.title')) ?></h1>
            <p class="text-muted mb-4"><?= e(__('auth.subtitle')) ?></p>

            <form method="post" action="<?= e(url('/login')) ?>" novalidate data-persist-form>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="login" class="form-label fw-semibold"><?= e(__('auth.login')) ?></label>
                    <div class="input-group input-group-lg has-validation">
                        <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                        <input type="text" class="form-control<?= $loginError ? ' is-invalid' : '' ?>" id="login" name="login"
                               value="<?= e(old('login')) ?>" autocomplete="username" autocapitalize="none"
                               spellcheck="false" maxlength="190" required autofocus
                               <?= $loginError ? 'aria-describedby="loginError"' : '' ?>>
                        <?php if ($loginError): ?>
                            <div class="invalid-feedback" id="loginError"><?= e($loginError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold"><?= e(__('auth.password')) ?></label>
                    <div class="input-group input-group-lg has-validation">
                        <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                        <input type="password" class="form-control<?= $passwordError ? ' is-invalid' : '' ?>" id="password"
                               name="password" autocomplete="current-password" maxlength="255" required
                               <?= $passwordError ? 'aria-describedby="passwordError"' : '' ?>>
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password"
                                aria-label="<?= e(__('auth.show_password')) ?>" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                        <?php if ($passwordError): ?>
                            <div class="invalid-feedback" id="passwordError"><?= e($passwordError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold" data-loading-text="<?= e(__('common.loading')) ?>">
                    <?= e(__('auth.submit')) ?>
                </button>
            </form>

            <p class="small text-muted mt-4 mb-0"><?= e(__('auth.forgot_hint')) ?></p>
        </div>

        <p class="auth-panel__footer small text-muted">
            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= e(__('auth.secure_note')) ?>
        </p>
    </section>
</div>
