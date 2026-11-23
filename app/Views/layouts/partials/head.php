<?php
/** @var App\Core\View $this */
/** @var string|null $title */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e(isset($title) ? $title . ' · ' . config('app.name') : config('app.name')) ?></title>
<link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
