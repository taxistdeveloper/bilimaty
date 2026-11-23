<?php
/** @var int $status */
/** @var string $message */
/** @var Throwable|null $debug */
$titleKey = 'errors.' . $status . '.title';
$title = __($titleKey) === $titleKey ? __('errors.500.title') : __($titleKey);
?>
<div class="card border-0 shadow-sm app-error-card text-center">
    <div class="card-body p-4 p-md-5">
        <img src="<?= e(asset('assets/images/mascot-tablet.png')) ?>" alt="" class="app-error-mascot mb-2" width="129" height="144">
        <div class="display-6 fw-bold text-body-emphasis"><?= e($status) ?></div>
        <h1 class="h4 mt-2"><?= e($title) ?></h1>
        <p class="text-muted mb-4"><?= e($message) ?></p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="<?= e(url('/')) ?>" class="btn btn-primary"><i class="bi bi-house me-1" aria-hidden="true"></i><?= e(__('errors.back_home')) ?></a>
        </div>
        <?php if ($debug !== null): ?>
            <details class="text-start mt-4 small">
                <summary><?= e(__('errors.details')) ?></summary>
                <pre class="mt-2 p-3 bg-light rounded overflow-auto"><?= e($debug::class . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine()) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
