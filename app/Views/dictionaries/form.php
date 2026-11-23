<?php
/** @var App\Core\View $this */
/** @var string $dictionary */
/** @var array<string, mixed> $definition */
/** @var array<string, array<int|string, string>> $options */
/** @var array<string, mixed>|null $record */
/** @var array<string, mixed> $values */
/** @var int $usage */
$isNew = $record === null;
$base = '/dictionaries/' . $dictionary;
?>
<?= $this->partial('partials/page-header', [
    'heading' => ($isNew ? __('common.create') : __('common.edit')) . ': ' . __('dictionaries.' . $dictionary . '.one'),
    'back' => $base,
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form method="post" action="<?= e(url($isNew ? $base : $base . '/' . $record['id'])) ?>" novalidate class="card">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
            <div class="card-body row">
                <?php foreach ($definition['fields'] as $name => $field): ?>
                    <?php $required = str_contains($field['rules'], 'required'); ?>
                    <?php if ($field['type'] === 'switch'): ?>
                        <?php $checked = old($name, null) !== null || (errors() === [] && (int) ($values[$name] ?? 0) === 1); ?>
                        <div class="col-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="1"<?= $checked ? ' checked' : '' ?>>
                                <label class="form-check-label" for="f_<?= e($name) ?>"><?= e(__('fields.' . $name)) ?></label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-6">
                            <?= $this->partial('partials/field', [
                                'name' => $name,
                                'label' => __('fields.' . $name),
                                'type' => $field['type'] === 'number' ? 'number' : $field['type'],
                                'value' => $field['type'] === 'time' ? substr((string) ($values[$name] ?? ''), 0, 5) : ($values[$name] ?? ''),
                                'required' => $required,
                                'options' => $field['type'] === 'select'
                                    ? ($required ? [] : ['' => '—']) + ($required && ($values[$name] ?? '') === '' ? ['' => __('common.choose')] : []) + $options[$name]
                                    : [],
                            ]) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e($isNew ? __('common.create') : __('common.save')) ?></button>
                <a href="<?= e(url($base)) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        </form>
    </div>

    <?php if (!$isNew): ?>
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <?php if ($usage > 0): ?>
                        <p class="small text-muted mb-0"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i><?= e(__('dictionaries.usage', ['count' => $usage])) ?></p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url($base . '/' . $record['id'])) ?>" data-confirm="<?= e(__('dictionaries.delete_confirm')) ?>" data-confirm-danger>
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('common.delete')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
