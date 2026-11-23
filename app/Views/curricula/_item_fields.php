<?php
/**
 * Fields of a curriculum row (shared by the add form and the edit page).
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $item
 * @var array<int, string> $subjects
 * @var list<string> $controls
 * @var int $semesters
 */
$controlOptions = [];
foreach ($controls as $c) {
    $controlOptions[$c] = __('curricula.controls.' . $c);
}
$semesterOptions = [];
for ($n = 1; $n <= $semesters; $n++) {
    $semesterOptions[$n] = (string) $n;
}
$num = static fn (string $name, string $label, bool $required = false) => ['name' => $name, 'label' => $label, 'type' => 'number', 'required' => $required, 'value' => $item[$name] ?? '', 'attrs' => ['min' => 0, 'max' => 2000]];
?>
<div class="row">
    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'subject_id', 'label' => __('curricula.subject'), 'type' => 'select', 'required' => true, 'value' => $item['subject_id'] ?? '', 'options' => ['' => __('common.choose')] + $subjects]) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'module_code', 'label' => __('curricula.module'), 'value' => $item['module_code'] ?? '', 'attrs' => ['maxlength' => 30, 'placeholder' => 'ПМ 01']]) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'semester', 'label' => __('calendar.semester'), 'type' => 'select', 'required' => true, 'value' => $item['semester'] ?? 1, 'options' => $semesterOptions]) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', $num('hours_total', __('curricula.hours_total'), true)) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', $num('hours_theory', __('curricula.hours_theory'))) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', $num('hours_practice', __('curricula.hours_practice'))) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', $num('hours_lab', __('curricula.hours_lab'))) ?></div>
    <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'credits', 'label' => __('curricula.credits'), 'value' => $item['credits'] ?? '', 'help' => __('curricula.credits_hint'), 'attrs' => ['inputmode' => 'decimal', 'maxlength' => 6]]) ?></div>
    <div class="col-6 col-md-4"><?= $this->partial('partials/field', ['name' => 'control_form', 'label' => __('curricula.control'), 'type' => 'select', 'required' => true, 'value' => $item['control_form'] ?? 'exam', 'options' => $controlOptions]) ?></div>
</div>
