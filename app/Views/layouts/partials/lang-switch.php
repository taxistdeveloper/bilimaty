<?php
/** @var string $class extra classes for the toggle */
$labels = ['kk' => 'Қазақша', 'ru' => 'Русский'];
?>
<div class="dropdown">
    <button class="btn <?= e($class ?? 'btn-light') ?> dropdown-toggle d-inline-flex align-items-center gap-2"
            type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('common.language')) ?>">
        <i class="bi bi-translate" aria-hidden="true"></i>
        <span class="text-uppercase fw-semibold small"><?= e(locale()) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <?php foreach ($labels as $code => $label): ?>
            <li>
                <a class="dropdown-item d-flex justify-content-between align-items-center<?= locale() === $code ? ' active' : '' ?>"
                   href="<?= e(url('/lang/' . $code)) ?>" data-lang-switch lang="<?= e($code) ?>">
                    <?= e($label) ?>
                    <span class="badge text-bg-light text-uppercase ms-3"><?= e($code) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
