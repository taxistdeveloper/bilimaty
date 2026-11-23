<?php
/** @var App\Core\View $this */
/** @var bool $forced */
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('profile.change_password'),
    'back' => $forced ? null : '/profile',
]) ?>

<div class="row">
    <div class="col-12 col-md-8 col-xl-5">
        <?php if ($forced): ?>
            <div class="alert alert-warning d-flex gap-2" role="alert">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                <div><?= e(__('profile.must_change_password')) ?></div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('/profile/password')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?= method_field('PUT') ?>
                    <?= $this->partial('partials/field', ['name' => 'current_password', 'label' => __('fields.current_password'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password', 'maxlength' => 255]]) ?>
                    <?= $this->partial('partials/field', ['name' => 'password', 'label' => __('profile.new_password'), 'type' => 'password', 'required' => true, 'help' => __('profile.password_rules'), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10, 'maxlength' => 255]]) ?>
                    <?= $this->partial('partials/field', ['name' => 'password_confirmation', 'label' => __('profile.confirm_password'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 255]]) ?>
                    <p class="small text-muted"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('profile.change_password_hint')) ?></p>
                    <button type="submit" class="btn btn-primary w-100" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('profile.change_password')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
