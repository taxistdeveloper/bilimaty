<?php
/** Flash messages rendered as Bootstrap toasts (shown by app.js). */
$types = [
    'success' => ['bi-check-circle-fill', 'text-success'],
    'warning' => ['bi-exclamation-triangle-fill', 'text-warning'],
    'error' => ['bi-x-octagon-fill', 'text-danger'],
    'info' => ['bi-info-circle-fill', 'text-primary'],
];
?>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true">
    <?php foreach ($types as $type => [$icon, $color]): ?>
        <?php $message = App\Core\Session::pullFlash($type); ?>
        <?php if (is_string($message) && $message !== ''): ?>
            <div class="toast align-items-center border-0 shadow" role="status" data-flash-toast>
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-start gap-2">
                        <i class="bi <?= e($icon) ?> <?= e($color) ?>" aria-hidden="true"></i>
                        <span><?= e($message) ?></span>
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= e(__('common.close')) ?>"></button>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
