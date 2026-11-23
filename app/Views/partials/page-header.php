<?php
/**
 * @var string $heading
 * @var string|null $subtitle
 * @var string|null $actions raw HTML (already escaped by the caller)
 * @var string|null $back URL path for the back link
 */
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="min-w-0">
        <?php if (!empty($back)): ?>
            <a href="<?= e(url($back)) ?>" class="small text-decoration-none d-inline-flex align-items-center gap-1 mb-1">
                <i class="bi bi-arrow-left" aria-hidden="true"></i><?= e(__('common.back')) ?>
            </a>
        <?php endif; ?>
        <h2 class="h4 fw-bold mb-0"><?= e($heading) ?></h2>
        <?php if (!empty($subtitle)): ?><p class="text-muted mb-0 mt-1"><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="d-flex flex-wrap gap-2"><?= $actions ?></div><?php endif; ?>
</div>
