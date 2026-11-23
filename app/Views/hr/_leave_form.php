<?php
/**
 * Leave request / absence record form.
 *
 * @var App\Core\View $this
 * @var string $action
 * @var list<string> $types
 * @var bool $byHr
 */
$own = in_array(old('_form'), ['', 'leave'], true);
$options = [];
foreach ($types as $t) {
    $options[$t] = __('hr.leave_types.' . $t);
}
?>
<form method="post" action="<?= e(url($action)) ?>" novalidate class="row g-0">
    <?= csrf_field() ?>
    <input type="hidden" name="_form" value="leave">
    <div class="col-12"><?= $this->partial('partials/field', ['name' => 'type', 'label' => __('fields.type'), 'type' => 'select', 'value' => 'annual', 'options' => $options, 'required' => true, 'idPrefix' => 'lv_', 'ownsInput' => $own]) ?></div>
    <div class="col-6 pe-2"><?= $this->partial('partials/field', ['name' => 'start_date', 'label' => __('fields.start_date'), 'type' => 'date', 'required' => true, 'idPrefix' => 'lv_', 'ownsInput' => $own]) ?></div>
    <div class="col-6"><?= $this->partial('partials/field', ['name' => 'end_date', 'label' => __('fields.end_date'), 'type' => 'date', 'required' => true, 'idPrefix' => 'lv_', 'ownsInput' => $own]) ?></div>
    <?php if ($byHr): ?>
        <div class="col-6 pe-2"><?= $this->partial('partials/field', ['name' => 'document_number', 'label' => __('fields.document_number'), 'idPrefix' => 'lv_', 'ownsInput' => $own, 'help' => __('hr.document_number_hint'), 'attrs' => ['maxlength' => 50]]) ?></div>
        <div class="col-6"><?= $this->partial('partials/field', ['name' => 'destination', 'label' => __('fields.destination'), 'idPrefix' => 'lv_', 'ownsInput' => $own, 'help' => __('hr.destination_hint'), 'attrs' => ['maxlength' => 255]]) ?></div>
    <?php endif; ?>
    <div class="col-12"><?= $this->partial('partials/field', ['name' => 'reason', 'label' => __('fields.comment'), 'idPrefix' => 'lv_', 'ownsInput' => $own, 'attrs' => ['maxlength' => 500]]) ?></div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($byHr ? __('hr.leave_record') : __('hr.leave_request')) ?></button>
        <?php if ($byHr): ?><div class="form-text"><?= e(__('hr.record_hint')) ?></div><?php endif; ?>
    </div>
</form>
