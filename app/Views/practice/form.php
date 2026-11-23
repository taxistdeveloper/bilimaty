<?php
/**
 * @var App\Core\View $this
 * @var array<string, mixed>|null $assignment
 * @var array<int, string> $teachers
 * @var array<int, string> $groups
 */
use App\Services\PracticeService;

$a = $assignment ?? [];
$types = [];
foreach (PracticeService::TYPES as $t) {
    $types[$t] = __('practice.types.' . $t);
}
?>
<?= $this->partial('partials/page-header', ['heading' => __($assignment === null ? 'practice.assign' : 'practice.edit'), 'back' => $assignment === null ? '/practice' : '/practice/' . $a['id']]) ?>

<div class="card" style="max-width: 760px">
    <form method="post" action="<?= e(url($assignment === null ? '/practice' : '/practice/' . $a['id'])) ?>" class="card-body" novalidate>
        <?= csrf_field() ?>
        <?php if ($assignment !== null): ?><?= method_field('PUT') ?><?php endif; ?>
        <div class="row">
            <div class="col-12"><?= $this->partial('partials/field', ['name' => 'title', 'label' => __('practice.f_title'), 'value' => $a['title'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 255, 'list' => 'practice_titles']]) ?></div>
            <datalist id="practice_titles"><option value="Учебная практика"><option value="Производственная практика"><option value="Преддипломная практика"><option value="Ознакомительная практика"></datalist>
            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'practice_type', 'label' => __('practice.f_type'), 'type' => 'select', 'options' => $types, 'value' => $a['practice_type'] ?? 'production', 'required' => true]) ?></div>
            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'group_id', 'label' => __('practice.f_group'), 'type' => 'select', 'options' => ['' => '—'] + $groups, 'value' => $a['group_id'] ?? '', 'required' => true]) ?></div>
            <div class="col-12"><?= $this->partial('partials/field', ['name' => 'module_name', 'label' => __('practice.f_module'), 'value' => $a['module_name'] ?? '', 'attrs' => ['maxlength' => 255]]) ?></div>
            <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'teacher_id', 'label' => __('practice.f_teacher'), 'type' => 'select', 'options' => ['' => '—'] + $teachers, 'value' => $a['teacher_id'] ?? '', 'required' => true]) ?></div>
            <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'hours', 'label' => __('practice.f_hours'), 'type' => 'number', 'value' => $a['hours'] ?? '36', 'required' => true, 'attrs' => ['min' => 1, 'max' => 2000]]) ?></div>
            <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'deadline', 'label' => __('practice.f_deadline'), 'type' => 'date', 'value' => $a['deadline'] ?? date('Y-m-d', strtotime('+14 days')), 'required' => true]) ?></div>
        </div>
        <p class="small text-muted"><?= e(__('practice.f_hours_help')) ?></p>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url($assignment === null ? '/practice' : '/practice/' . $a['id'])) ?>" class="btn btn-outline-secondary"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>
