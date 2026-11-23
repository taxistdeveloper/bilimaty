<?php
/** @var App\Core\View $this */
?>
<?= $this->partial('partials/page-header', ['heading' => __('platform.password_title'), 'subtitle' => __('platform.password_hint')]) ?>
<div class="card" style="max-width: 520px">
    <form method="post" action="<?= e(url('/password')) ?>" novalidate class="card-body">
        <?= csrf_field() ?>
        <?= $this->partial('partials/field', ['name' => 'current_password', 'label' => __('fields.current_password'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
        <?= $this->partial('partials/field', ['name' => 'password', 'label' => __('profile.new_password'), 'type' => 'password', 'required' => true, 'help' => __('profile.password_rules'), 'attrs' => ['autocomplete' => 'new-password']]) ?>
        <?= $this->partial('partials/field', ['name' => 'password_confirmation', 'label' => __('fields.password_confirmation'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>
