<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $year */
/** @var array<string, mixed>|null $event */
/** @var array<string, mixed> $values */
/** @var list<string> $types */
/** @var array<int, string> $groups */
$isNew = $event === null;
$typeOptions = [];
foreach ($types as $type) {
    $typeOptions[$type] = __('calendar.types.' . $type);
}
$back = '/calendar?year=' . $year['id'] . '&month=' . substr((string) ($values['start_date'] ?? $year['start_date']), 0, 7);
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('calendar.create') : __('calendar.edit'),
    'subtitle' => __('fields.academic_year_id') . ' ' . str_replace('-', '–', (string) $year['title']) . ' · ' . format_date($year['start_date']) . ' — ' . format_date($year['end_date']),
    'back' => $back,
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="post" action="<?= e(url($isNew ? '/calendar' : '/calendar/' . $event['id'])) ?>" novalidate class="card">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
            <input type="hidden" name="academic_year_id" value="<?= e($year['id']) ?>">
            <div class="card-body row">
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'type', 'label' => __('fields.type'), 'type' => 'select', 'required' => true, 'value' => $values['type'] ?? 'theory', 'options' => $typeOptions]) ?></div>
                <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'start_date', 'label' => __('years.start'), 'type' => 'date', 'required' => true, 'value' => $values['start_date'] ?? '', 'attrs' => ['min' => $year['start_date'], 'max' => $year['end_date']]]) ?></div>
                <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'end_date', 'label' => __('years.end'), 'type' => 'date', 'required' => true, 'value' => $values['end_date'] ?? '', 'attrs' => ['min' => $year['start_date'], 'max' => $year['end_date']]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'title_ru', 'label' => __('calendar.title_ru'), 'required' => true, 'value' => $values['title_ru'] ?? '', 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'title_kk', 'label' => __('calendar.title_kk'), 'required' => true, 'value' => $values['title_kk'] ?? '', 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'course', 'label' => __('fields.course'), 'type' => 'select', 'value' => $values['course'] ?? '', 'options' => ['' => __('calendar.all_courses'), 1 => '1', 2 => '2', 3 => '3', 4 => '4']]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'value' => $values['group_id'] ?? '', 'options' => ['' => __('calendar.all_groups')] + $groups, 'help' => __('calendar.audience_hint')]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.comment'), 'value' => $values['note'] ?? '', 'attrs' => ['maxlength' => 500]]) ?></div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </form>
    </div>
    <?php if (!$isNew): ?>
        <div class="col-12 col-xl-4">
            <div class="card"><div class="card-body">
                <form method="post" action="<?= e(url('/calendar/' . $event['id'])) ?>" data-confirm="<?= e(__('calendar.delete_confirm')) ?>" data-confirm-danger>
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
                </form>
            </div></div>
        </div>
    <?php endif; ?>
</div>
