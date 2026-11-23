<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $exam */
/** @var array<string, mixed> $values */
/** @var array<string, array<int, string>> $options */
/** @var int|null $semesterId */
/** @var list<string> $forms */
$isNew = $exam === null;
$formOptions = [];
foreach ($forms as $f) {
    $formOptions[$f] = __('exams.forms.' . $f);
}
$conflicts = errors()['conflicts'] ?? [];
$back = $isNew ? '/exams' : '/exams/' . $exam['id'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('exams.create') : __('exams.edit'),
    'subtitle' => $isNew ? __('exams.create_hint') : localized(['name_ru' => $exam['subject_name_ru'], 'name_kk' => $exam['subject_name_kk']]) . ' · ' . $exam['group_code'] . ' · ' . localized(['name_ru' => $exam['type_name_ru'], 'name_kk' => $exam['type_name_kk']]),
    'back' => $back,
]) ?>

<?php if ($isNew): ?>
    <form method="get" action="<?= e(url('/exams/create')) ?>" class="card card-body mb-3 row g-2 align-items-end flex-row">
        <div class="col-md-6">
            <label class="form-label small text-muted mb-1" for="semester"><?= e(__('fields.semester')) ?></label>
            <select class="form-select" id="semester" name="semester" data-autosubmit-form>
                <?php foreach ($options['semesters'] as $id => $label): ?><option value="<?= e($id) ?>"<?= $semesterId === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><button type="submit" class="btn btn-light w-100"><?= e(__('exams.load_journals')) ?></button></div>
    </form>
<?php endif; ?>

<?php if ($conflicts !== []): ?>
    <div class="alert alert-danger small"><div class="fw-semibold mb-1"><?= e(__('schedule.conflicts_found')) ?></div><?php foreach ($conflicts as $c): ?><div><?= e($c) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/exams' : '/exams/' . $exam['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-body row">
                <?php if ($isNew): ?>
                    <div class="col-md-8"><?= $this->partial('partials/field', ['name' => 'assignment_id', 'label' => __('exams.journal'), 'type' => 'select', 'required' => true, 'options' => ['' => __('common.choose')] + $options['journals'], 'help' => __('exams.journal_hint')]) ?></div>
                    <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'assessment_type_id', 'label' => __('exams.type'), 'type' => 'select', 'required' => true, 'options' => ['' => __('common.choose')] + $options['types']]) ?></div>
                <?php endif; ?>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'exam_form', 'label' => __('exams.form'), 'type' => 'select', 'required' => true, 'value' => $values['exam_form'] ?? 'written', 'options' => $formOptions]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'period_start', 'label' => __('exams.period_start'), 'type' => 'date', 'value' => $values['period_start'] ?? '', 'help' => __('exams.period_hint')]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'exam_date', 'label' => __('exams.date'), 'type' => 'date', 'required' => true, 'value' => $values['exam_date'] ?? '']) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'start_time', 'label' => __('exams.time'), 'type' => 'time', 'value' => isset($values['start_time']) ? substr((string) $values['start_time'], 0, 5) : '']) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'classroom_id', 'label' => __('schedule.classroom'), 'type' => 'select', 'value' => $values['classroom_id'] ?? '', 'options' => ['' => '—'] + $options['classrooms']]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'examiner_teacher_id', 'label' => __('exams.examiner'), 'type' => 'select', 'required' => true, 'value' => $values['examiner_teacher_id'] ?? '', 'options' => ['' => __('common.choose')] + $options['teachers']]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'value' => $values['note'] ?? '', 'attrs' => ['maxlength' => 500]]) ?></div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url($back)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </div>
    </div>
</form>
