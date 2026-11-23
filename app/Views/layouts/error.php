<?php
/** @var App\Core\View $this */
/** @var string $content */
/** @var int $status */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <?= $this->partial('layouts/partials/head', ['title' => __('errors.' . $status . '.title')]) ?>
</head>
<body class="auth-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<?= $content ?>
</body>
</html>
