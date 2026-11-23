<?php
/**
 * Bootstrap form field with label, old input and validation error.
 *
 * @var string $name
 * @var string $label
 * @var string|null $type  text|email|password|tel|select
 * @var mixed $value       current value (old input wins)
 * @var bool|null $required
 * @var string|null $help
 * @var array<string, string>|null $options  for select: value => label
 * @var array<string, string|int|bool>|null $attrs extra attributes (escaped)
 * @var string|null $idPrefix  prefix of the generated id (several forms on one page)
 * @var bool|null $ownsInput  false when another form on the page was submitted
 */
$type ??= 'text';
$required ??= false;
$attrs ??= [];
$help ??= null;
$value ??= '';
$options ??= [];
$own = $ownsInput ?? true; // false: this form was not the one submitted, ignore old input/errors
$fieldError = $own ? error($name) : null;
$current = $type === 'password' ? '' : ($own ? old($name, $value) : $value);
$id = ($idPrefix ?? 'f_') . preg_replace('/\W/', '_', $name);
$describedBy = trim(($help ? $id . '_help ' : '') . ($fieldError ? $id . '_error' : ''));
$attrHtml = '';
foreach ($attrs as $attr => $attrValue) {
    if ($attrValue === false) {
        continue;
    }
    $attrHtml .= ' ' . e($attr) . ($attrValue === true ? '' : '="' . e($attrValue) . '"');
}
?>
<div class="mb-3">
    <label for="<?= e($id) ?>" class="form-label fw-semibold">
        <?= e($label) ?><?php if ($required): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
    </label>
    <?php if ($type === 'select'): ?>
        <select id="<?= e($id) ?>" name="<?= e($name) ?>" class="form-select<?= $fieldError ? ' is-invalid' : '' ?>"
                <?= $required ? 'required' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?><?= $attrHtml ?>>
            <?php foreach ($options ?? [] as $optionValue => $optionLabel): ?>
                <option value="<?= e($optionValue) ?>"<?= (string) $current === (string) $optionValue ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($current) ?>"
               class="form-control<?= $fieldError ? ' is-invalid' : '' ?>" <?= $required ? 'required' : '' ?>
               <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?><?= $attrHtml ?>>
    <?php endif; ?>
    <?php if ($help): ?><div id="<?= e($id) ?>_help" class="form-text"><?= e($help) ?></div><?php endif; ?>
    <?php if ($fieldError): ?><div id="<?= e($id) ?>_error" class="invalid-feedback d-block"><?= e($fieldError) ?></div><?php endif; ?>
</div>
