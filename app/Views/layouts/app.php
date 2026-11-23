<?php
/** @var App\Core\View $this */
/** @var string $content */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <?= $this->partial('layouts/partials/head', ['title' => $title ?? null]) ?>
</head>
<body class="app-body">
<a class="visually-hidden-focusable app-skip" href="#main"><?= e(__('common.skip_to_content')) ?></a>

<div class="app-shell">
    <?= $this->partial('layouts/partials/sidebar') ?>

    <div class="app-main">
        <?= $this->partial('layouts/partials/topbar', ['title' => $title ?? null]) ?>
        <main id="main" class="app-content" tabindex="-1">
            <?= $this->partial('layouts/partials/demo-banner') ?>
            <?= $this->partial('layouts/partials/platform-notices') ?>
            <?= $this->partial('layouts/partials/subscription') ?>
            <?= $content ?>
        </main>
    </div>
</div>

<?= $this->partial('layouts/partials/toasts') ?>
<?= $this->partial('layouts/partials/confirm-modal') ?>
<?= $this->partial('layouts/partials/i18n') ?>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?= $this->section('scripts') ?>
</body>
</html>
