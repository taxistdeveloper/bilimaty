<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $scheme */
/** @var array<string, mixed> $values */
/** @var array<int, string> $weights */
/** @var list<array<string, mixed>> $types */
/** @var bool $readonly */
$isNew = $scheme === null;
$oldWeights = old('weights', null);
$weights = is_array($oldWeights) ? $oldWeights : $weights;
$num = static fn ($v): string => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$action = static fn (string $a, string $label, string $class, ?string $confirm = null): string => '<form method="post" action="' . e(url('/grading-schemes/' . ($scheme['id'] ?? 0) . '/status')) . '"'
    . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . e($a) . '">'
    . '<button type="submit" class="btn ' . e($class) . '">' . e($label) . '</button></form>';
$actions = '';
if (!$isNew) {
    if ($scheme['status'] === 'draft') {
        $actions .= $action('activate', __('grading.activate'), 'btn-primary', __('grading.activate_confirm'));
        if ((int) $scheme['journals_count'] === 0) {
            $actions .= $action('delete', __('common.delete'), 'btn-outline-danger', __('grading.delete_confirm'));
        }
    } else {
        $actions .= $action('version', __('grading.new_version'), 'btn-primary', __('grading.version_confirm'));
        if ($scheme['status'] === 'active') {
            $actions .= $action('archive', __('grading.archive'), 'btn-light', __('grading.archive_confirm'));
        }
    }
}
$weightsError = error('weights');
?>
<?= $this->partial('partials/page-header', [
    'heading' => $isNew ? __('grading.create') : $scheme['title'] . ' · v' . $scheme['version'],
    'subtitle' => $isNew ? null : __('grading.statuses.' . $scheme['status']) . ' · ' . __('grading.journals_count', ['count' => $scheme['journals_count']]),
    'back' => '/grading-schemes',
    'actions' => $actions,
]) ?>

<?php if ($readonly): ?>
    <div class="alert alert-light border small"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('grading.immutable')) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/grading-schemes' : '/grading-schemes/' . $scheme['id'])) ?>" novalidate class="row g-3">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><?= method_field('PUT') ?><?php endif; ?>
    <fieldset class="col-12 col-xl-8"<?= $readonly ? ' disabled' : '' ?>>
        <div class="card mb-3">
            <div class="card-body row">
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'title', 'label' => __('fields.title'), 'required' => true, 'value' => $values['title'] ?? '', 'help' => __('grading.title_hint'), 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'grade_min', 'label' => __('grading.grade_min'), 'required' => true, 'value' => $num($values['grade_min'] ?? ''), 'attrs' => ['inputmode' => 'decimal', 'maxlength' => 7]]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'grade_max', 'label' => __('grading.grade_max'), 'required' => true, 'value' => $num($values['grade_max'] ?? ''), 'attrs' => ['inputmode' => 'decimal', 'maxlength' => 7]]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'pass_threshold', 'label' => __('grading.threshold'), 'value' => $num($values['pass_threshold'] ?? ''), 'help' => __('grading.threshold_hint'), 'attrs' => ['inputmode' => 'decimal', 'maxlength' => 7]]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'final_decimals', 'label' => __('grading.decimals'), 'type' => 'select', 'required' => true, 'value' => $values['final_decimals'] ?? 0, 'options' => [0 => '0', 1 => '1', 2 => '2']]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('grading.basis'), 'value' => $values['note'] ?? '', 'help' => __('grading.basis_hint'), 'attrs' => ['maxlength' => 500]]) ?></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('grading.components')) ?></div>
            <div class="card-body">
                <p class="small text-muted"><?= e(__('grading.components_hint')) ?></p>
                <div class="row g-2" data-weights>
                    <?php foreach ($types as $type): ?>
                        <div class="col-12 col-md-6">
                            <label class="app-check-card justify-content-between" for="w_<?= e($type['id']) ?>">
                                <span>
                                    <span class="d-block fw-semibold small"><?= e(localized($type)) ?></span>
                                    <span class="d-block small text-muted"><?= e(__('grading.sources.' . $type['source'])) ?></span>
                                </span>
                                <span class="input-group input-group-sm app-weight">
                                    <input type="text" class="form-control text-end" id="w_<?= e($type['id']) ?>" name="weights[<?= e($type['id']) ?>]"
                                           value="<?= e($num($weights[(int) $type['id']] ?? '')) ?>" inputmode="decimal" maxlength="6" data-weight>
                                    <span class="input-group-text">%</span>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 small">
                    <span><?= e(__('grading.weights_sum')) ?>: <strong data-weights-sum>0</strong>%</span>
                    <?php if ($weightsError): ?><span class="text-danger"><?= e($weightsError) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$readonly): ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><?= e(__('common.save')) ?></button>
                <a href="<?= e(url('/grading-schemes')) ?>" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
            </div>
        <?php endif; ?>
    </fieldset>
</form>
