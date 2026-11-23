<?php
/** @var App\Core\View $this */
/** @var string $content */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <?= $this->partial('layouts/partials/head', ['title' => $title ?? null]) ?>
</head>
<body class="auth-body">
<?= $content ?>
<?= $this->partial('layouts/partials/toasts') ?>
<?= $this->partial('layouts/partials/i18n') ?>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
