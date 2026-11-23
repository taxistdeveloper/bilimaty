<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $plans */
?>
<?= $this->partial('partials/page-header', ['heading' => __('platform.plans'), 'subtitle' => __('platform.plans_hint')]) ?>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <?php if ($plans === []): ?>
            <div class="card"><div class="app-empty"><i class="bi bi-tags" aria-hidden="true"></i><p class="mb-0"><?= e(__('platform.no_plans')) ?></p></div></div>
        <?php endif; ?>
        <?php foreach ($plans as $p): ?>
            <details class="card mb-2">
                <summary class="card-header d-flex flex-wrap justify-content-between gap-2">
                    <span class="fw-semibold"><?= e($p['name']) ?><?= (int) $p['is_active'] === 1 ? '' : ' · ' . e(__('common.inactive')) ?></span>
                    <span class="small text-muted"><?= e($p['max_students'] !== null ? __('platform.up_to_students', ['n' => $p['max_students']]) : __('platform.unlimited')) ?> · <?= e(number_format((float) $p['monthly_price'], 0, ',', ' ')) ?> ₸ · <?= e(__('platform.colleges_n', ['n' => $p['colleges']])) ?></span>
                </summary>
                <form method="post" action="<?= e(url('/plans/' . $p['id'])) ?>" novalidate class="card-body row">
                    <?= csrf_field() ?><?= method_field('PUT') ?>
                    <?php $pre = 'p' . $p['id'] . '_'; ?>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'name', 'label' => __('fields.name'), 'value' => $p['name'], 'required' => true, 'idPrefix' => $pre, 'ownsInput' => false]) ?></div>
                    <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'max_students', 'label' => __('platform.max_students'), 'type' => 'number', 'value' => $p['max_students'] ?? '', 'idPrefix' => $pre, 'ownsInput' => false, 'attrs' => ['min' => 1]]) ?></div>
                    <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'monthly_price', 'label' => __('platform.monthly_price'), 'value' => $p['monthly_price'], 'required' => true, 'idPrefix' => $pre, 'ownsInput' => false]) ?></div>
                    <div class="col-12"><?= $this->partial('partials/field', ['name' => 'description', 'label' => __('fields.description'), 'value' => $p['description'] ?? '', 'idPrefix' => $pre, 'ownsInput' => false, 'attrs' => ['maxlength' => 500]]) ?></div>
                    <div class="col-12 d-flex align-items-center gap-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="<?= e($pre) ?>active"<?= (int) $p['is_active'] === 1 ? ' checked' : '' ?>><label class="form-check-label" for="<?= e($pre) ?>active"><?= e(__('platform.plan_active')) ?></label></div>
                        <button type="submit" class="btn btn-primary btn-sm"><?= e(__('common.save')) ?></button>
                    </div>
                </form>
            </details>
        <?php endforeach; ?>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('platform.plan_new')) ?></div>
            <form method="post" action="<?= e(url('/plans')) ?>" novalidate class="card-body">
                <?= csrf_field() ?>
                <?= $this->partial('partials/field', ['name' => 'name', 'label' => __('fields.name'), 'required' => true, 'attrs' => ['maxlength' => 100, 'placeholder' => __('platform.plan_placeholder')]]) ?>
                <?= $this->partial('partials/field', ['name' => 'max_students', 'label' => __('platform.max_students'), 'type' => 'number', 'help' => __('platform.max_students_hint'), 'attrs' => ['min' => 1]]) ?>
                <?= $this->partial('partials/field', ['name' => 'monthly_price', 'label' => __('platform.monthly_price'), 'required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?= $this->partial('partials/field', ['name' => 'description', 'label' => __('fields.description'), 'attrs' => ['maxlength' => 500]]) ?>
                <button type="submit" class="btn btn-primary"><?= e(__('common.create')) ?></button>
            </form>
        </div>
    </div>
</div>
