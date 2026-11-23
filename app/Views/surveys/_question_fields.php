<?php
/**
 * Question fields shared by "add question" and "edit question".
 *
 * @var App\Core\View $this
 * @var array<string, mixed>|null $q
 */
use App\Services\SurveyService;

$types = [];
foreach (SurveyService::TYPES as $t) {
    $types[$t] = __('surveys.types.' . $t);
}
$optionLines = $q !== null ? implode("\n", array_map(static fn (array $o): string => $o['label_ru'] . ($o['label_kk'] !== null ? ' | ' . $o['label_kk'] : ''), $q['options'])) : '';
$optionsError = error('options');
?>
<div class="row g-2" data-question-form>
    <div class="col-12 col-md-4"><?= $this->partial('partials/field', ['name' => 'type', 'label' => __('surveys.question_type'), 'type' => 'select', 'options' => $types, 'value' => $q['type'] ?? 'single', 'required' => true, 'idPrefix' => 'sq_', 'attrs' => ['data-question-type' => true]]) ?></div>
    <div class="col-12 col-md-8 d-flex align-items-end pb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="sq_required" name="is_required" value="1"<?= (old('text_ru') !== '' ? old('is_required') !== '' : (bool) ($q['is_required'] ?? true)) ? ' checked' : '' ?>>
            <label class="form-check-label" for="sq_required"><?= e(__('surveys.required')) ?></label>
        </div>
    </div>
    <div class="col-12 col-md-6"><?= $this->partial('partials/field', ['name' => 'text_ru', 'label' => __('fields.text_ru'), 'value' => $q['text_ru'] ?? '', 'required' => true, 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 500]]) ?></div>
    <div class="col-12 col-md-6"><?= $this->partial('partials/field', ['name' => 'text_kk', 'label' => __('fields.text_kk'), 'value' => $q['text_kk'] ?? '', 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 500]]) ?></div>
    <div class="col-12" data-show-for="single multiple">
        <label class="form-label fw-semibold" for="sq_options"><?= e(__('surveys.options')) ?> <span class="text-danger" aria-hidden="true">*</span></label>
        <textarea class="form-control font-monospace<?= $optionsError ? ' is-invalid' : '' ?>" id="sq_options" name="options" rows="5" aria-describedby="sq_options_help"><?= e(old('options', $optionLines)) ?></textarea>
        <div id="sq_options_help" class="form-text"><?= e(__('surveys.options_hint')) ?></div>
        <?php if ($optionsError): ?><div class="invalid-feedback d-block"><?= e($optionsError) ?></div><?php endif; ?>
    </div>
    <div class="col-12" data-show-for="scale">
        <div class="row g-2">
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'scale_min', 'label' => __('surveys.scale_min'), 'type' => 'number', 'value' => $q['scale_min'] ?? 1, 'idPrefix' => 'sq_', 'attrs' => ['min' => 0, 'max' => 9]]) ?></div>
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'scale_max', 'label' => __('surveys.scale_max'), 'type' => 'number', 'value' => $q['scale_max'] ?? 5, 'idPrefix' => 'sq_', 'attrs' => ['min' => 1, 'max' => 10]]) ?></div>
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'min_label_ru', 'label' => __('surveys.min_label') . ' (RU)', 'value' => $q['min_label_ru'] ?? '', 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 100, 'placeholder' => __('surveys.min_label_ph')]]) ?></div>
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'min_label_kk', 'label' => __('surveys.min_label') . ' (KK)', 'value' => $q['min_label_kk'] ?? '', 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 100]]) ?></div>
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'max_label_ru', 'label' => __('surveys.max_label') . ' (RU)', 'value' => $q['max_label_ru'] ?? '', 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 100, 'placeholder' => __('surveys.max_label_ph')]]) ?></div>
            <div class="col-6 col-md-2"><?= $this->partial('partials/field', ['name' => 'max_label_kk', 'label' => __('surveys.max_label') . ' (KK)', 'value' => $q['max_label_kk'] ?? '', 'idPrefix' => 'sq_', 'attrs' => ['maxlength' => 100]]) ?></div>
        </div>
    </div>
</div>
