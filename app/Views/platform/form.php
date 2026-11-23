<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $college */
/** @var array<int, string> $plans */
$isNew = $college === null;
$c = $college ?? [];
$back = $isNew ? '/' : '/colleges/' . $c['id'];
?>
<?= $this->partial('partials/page-header', ['heading' => $isNew ? __('platform.college_create') : $c['name'], 'subtitle' => $isNew ? __('platform.create_hint') : __('platform.college_edit'), 'back' => $back]) ?>
<form method="post" action="<?= e(url($isNew ? '/colleges' : '/colleges/' . $c['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3"><div class="card-header fw-semibold"><?= e(__('platform.section_college')) ?></div><div class="card-body row">
            <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'name', 'label' => __('platform.college_name'), 'value' => $c['name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
            <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'city', 'label' => __('platform.city'), 'value' => $c['city'] ?? '', 'attrs' => ['maxlength' => 100]]) ?></div>
            <?php if ($isNew): ?>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'code', 'label' => __('platform.code'), 'required' => true, 'help' => __('platform.code_hint'), 'attrs' => ['maxlength' => 30, 'pattern' => '[a-z0-9][a-z0-9\-]{1,29}', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'admin_login', 'label' => __('platform.admin_login'), 'value' => 'admin', 'help' => __('platform.admin_login_hint'), 'attrs' => ['maxlength' => 64, 'autocapitalize' => 'none']]) ?></div>
            <?php else: ?>
                <div class="col-md-6"><div class="mb-3"><div class="form-label fw-semibold"><?= e(__('platform.code')) ?></div><div>/<?= e($c['code']) ?> <span class="text-muted small">· <?= e($c['db_name']) ?></span></div></div></div>
            <?php endif; ?>
            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'bin', 'label' => __('platform.bin'), 'value' => $c['bin'] ?? '', 'attrs' => ['maxlength' => 12, 'inputmode' => 'numeric']]) ?></div>
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold"><?= e(__('platform.contact')) ?></div><div class="card-body row">
            <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'contact_name', 'label' => __('platform.contact_name'), 'value' => $c['contact_name'] ?? '', 'attrs' => ['maxlength' => 150]]) ?></div>
            <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'contact_phone', 'label' => __('fields.phone'), 'type' => 'tel', 'value' => $c['contact_phone'] ?? '', 'attrs' => ['maxlength' => 20]]) ?></div>
            <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'contact_email', 'label' => __('fields.email'), 'type' => 'email', 'value' => $c['contact_email'] ?? '', 'attrs' => ['maxlength' => 190]]) ?></div>
            <div class="col-12"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'value' => $c['note'] ?? '', 'attrs' => ['maxlength' => 500]]) ?></div>
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-3"><div class="card-header fw-semibold"><?= e(__('platform.section_subscription')) ?></div><div class="card-body">
            <?= $this->partial('partials/field', ['name' => 'plan_id', 'label' => __('platform.plan'), 'type' => 'select', 'value' => $c['plan_id'] ?? '', 'options' => ['' => __('platform.no_plan')] + $plans, 'help' => __('platform.plan_hint')]) ?>
            <?= $this->partial('partials/field', ['name' => 'monthly_price', 'label' => __('platform.monthly_price'), 'value' => $c['monthly_price'] ?? '', 'help' => __('platform.price_hint'), 'attrs' => ['inputmode' => 'decimal']]) ?>
            <?= $this->partial('partials/field', ['name' => 'grace_days', 'label' => __('platform.grace_days'), 'type' => 'number', 'value' => $c['grace_days'] ?? config('tenancy.grace_days', 7), 'required' => true, 'help' => __('platform.grace_hint'), 'attrs' => ['min' => 0, 'max' => 60]]) ?>
            <?php if (!$isNew): ?>
                <?= $this->partial('partials/field', ['name' => 'paid_until', 'label' => __('platform.paid_until'), 'type' => 'date', 'value' => $c['paid_until'] ?? '', 'help' => __('platform.paid_until_hint')]) ?>
            <?php else: ?>
                <p class="small text-muted mb-0"><?= e(__('platform.trial_note', ['days' => config('tenancy.trial_days', 14)])) ?></p>
            <?php endif; ?>
        </div></div>
        <?php if ($isNew): ?>
            <details class="card mb-3"><summary class="card-header fw-semibold"><?= e(__('platform.existing_db')) ?></summary><div class="card-body">
                <?= $this->partial('partials/field', ['name' => 'existing_db', 'label' => __('platform.existing_db_name'), 'help' => __('platform.existing_db_hint'), 'attrs' => ['maxlength' => 64, 'autocapitalize' => 'none']]) ?>
            </div></details>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('platform.create_btn') : __('common.save')) ?></button>
            <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
