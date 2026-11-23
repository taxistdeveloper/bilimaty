<?php
/** @var App\Core\View $this */
/** @var string $content */
/** Minimal A4 layout for documents the browser prints or saves as PDF. */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') . ' · ' . config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/print.css')) ?>">
</head>
<body>
<div class="print-toolbar">
    <button type="button" data-print><?= e(__('calendar.print_save')) ?></button>
    <span><?= e(__('calendar.print_hint')) ?></span>
</div>
<?php $notices = array_merge(array_filter([(string) App\Core\Session::getFlash('success', '')]), ...array_values(errors())); ?>
<?php if ($notices !== []): ?>
    <div class="print-notice" role="status"><?php foreach ($notices as $notice): ?><div><?= e($notice) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<main class="sheet">
    <?= $content ?>
</main>
<script src="<?= e(asset('assets/js/print.js')) ?>"></script>
</body>
</html>
