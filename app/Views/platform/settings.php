<?php
/** @var App\Core\View $this */
/** @var array<string, string> $values */
?>
<?= $this->partial('partials/page-header', ['heading' => __('platform.settings'), 'subtitle' => __('platform.settings_hint')]) ?>
<form method="post" action="<?= e(url('/settings')) ?>" novalidate class="card" style="max-width: 860px">
    <?= csrf_field() ?><?= method_field('PUT') ?>
    <div class="card-body row">
        <?php foreach (App\Controllers\Platform\PlatformController::SETTINGS as $key): ?>
            <div class="<?= in_array($key, ['owner_name', 'owner_address', 'invoice_service', 'payment_note'], true) ? 'col-12' : 'col-md-6' ?>">
                <?= $this->partial('partials/field', ['name' => $key, 'label' => __('platform.settings_fields.' . $key), 'value' => $values[$key], 'attrs' => ['maxlength' => 500]]) ?>
            </div>
        <?php endforeach; ?>
        <div class="col-12"><button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button></div>
    </div>
</form>
