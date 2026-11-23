<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $year */
/** @var array<string, string> $values */
$isNew = $year === null;
$date = fn (string $name, string $label): string => $this->partial('partials/field', [
    'name' => $name, 'label' => $label, 'type' => 'date', 'required' => true, 'value' => $values[$name] ?? '',
]);
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('years.create') : __('years.edit') . ' ' . str_replace('-', '–', (string) $year['title']),
    'subtitle' => __('years.form_hint'),
    'back' => '/academic-years',
]) ?>

<form method="post" action="<?= e(url($isNew ? '/academic-years' : '/academic-years/' . $year['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('years.period')) ?></div>
            <div class="card-body row">
                <div class="col-md-6"><?= $date('start_date', __('years.start')) ?></div>
                <div class="col-md-6"><?= $date('end_date', __('years.end')) ?></div>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <?php foreach ([1, 2] as $n): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold"><?= e(__('dashboard.semester', ['number' => $n])) ?></div>
                        <div class="card-body">
                            <?= $date("s{$n}_start", __('years.start')) ?>
                            <?= $date("s{$n}_end", __('years.end')) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
            <a href="<?= e(url('/academic-years')) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
