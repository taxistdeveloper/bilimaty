<?php
/**
 * Compact select for GET filter forms.
 *
 * @var string $name
 * @var string $label
 * @var array<int|string, string> $items
 * @var mixed $current
 * @var bool|null $all  adds an empty "all" option (default true)
 */
$all ??= true;
$id = 'flt_' . preg_replace('/\W/', '_', $name);
?>
<label class="form-label small text-muted mb-1" for="<?= e($id) ?>"><?= e($label) ?></label>
<select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>">
    <?php if ($all): ?><option value=""><?= e(__('common.all')) ?></option><?php endif; ?>
    <?php foreach ($items as $value => $text): ?>
        <option value="<?= e($value) ?>"<?= (string) $current === (string) $value ? ' selected' : '' ?>><?= e($text) ?></option>
    <?php endforeach; ?>
</select>
