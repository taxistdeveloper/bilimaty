<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $plan */
/** @var array<string, mixed> $item */
/** @var array<int, string> $subjects */
/** @var list<string> $controls */
?>
<?= $this->partial('partials/page-header', ['heading' => __('curricula.edit_item'), 'subtitle' => $plan['title'], 'back' => '/curricula/' . $plan['id']]) ?>

<div class="row">
    <div class="col-12 col-xl-9">
        <form method="post" action="<?= e(url('/curricula/' . $plan['id'] . '/items/' . $item['id'])) ?>" novalidate class="card">
            <?= csrf_field() ?>
            <?= method_field('PUT') ?>
            <div class="card-body">
                <?= $this->partial('curricula/_item_fields', ['item' => $item, 'subjects' => $subjects, 'controls' => $controls, 'semesters' => (int) $plan['semesters_count']]) ?>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                <a href="<?= e(url('/curricula/' . $plan['id'])) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
