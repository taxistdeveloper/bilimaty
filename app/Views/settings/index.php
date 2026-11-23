<?php
/** @var App\Core\View $this */
/** @var array<string, array{0: string, 1: string, 2: string}> $schema */
/** @var array<string, string|null> $values */
$languages = ['kk' => 'Қазақша', 'ru' => 'Русский'];
$lockOld = old('journal_lock_after_approval', null);
$lockChecked = $lockOld !== null ? true : ($values['journal_lock_after_approval'] ?? '0') === '1';
?>
<?= $this->partial('partials/page-header', ['heading' => __('settings.title')]) ?>

<form method="post" action="<?= e(url('/settings')) ?>" novalidate class="row">
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('settings.general')) ?></div>
            <div class="card-body row">
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'college_name_ru', 'label' => __('fields.college_name_ru'), 'value' => $values['college_name_ru'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'college_name_kk', 'label' => __('fields.college_name_kk'), 'value' => $values['college_name_kk'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('settings.interface')) ?></div>
            <div class="card-body row">
                <div class="col-md-6"><?= $this->partial('partials/field', ['name' => 'default_locale', 'label' => __('fields.default_locale'), 'type' => 'select', 'value' => $values['default_locale'] ?? 'ru', 'options' => $languages, 'required' => true]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('settings.academic')) ?></div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="journalLock" name="journal_lock_after_approval" value="1"<?= $lockChecked ? ' checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="journalLock"><?= e(__('fields.journal_lock_after_approval')) ?></label>
                    <div class="form-text"><?= e(__('settings.journal_lock_hint')) ?></div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('settings.documents')) ?></div>
            <div class="card-body row">
                <?php foreach (['college_address_ru' => 255, 'college_address_kk' => 255, 'document_signer_position_ru' => 150, 'document_signer_position_kk' => 150, 'document_signer_name' => 150] as $key => $max): ?>
                    <div class="col-md-6"><?= $this->partial('partials/field', ['name' => $key, 'label' => __('fields.' . $key), 'value' => $values[$key] ?? '', 'attrs' => ['maxlength' => $max]]) ?></div>
                <?php endforeach; ?>
                <div class="col-12 form-text mt-0"><?= e(__('settings.documents_hint')) ?></div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('common.save')) ?></button>
    </div>
</form>
