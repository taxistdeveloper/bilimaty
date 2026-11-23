<?php
/** @var App\Core\View $this */
/** @var string $content */
/** Owner's panel: top navigation, no college data. */
$admin = App\Platform\PlatformAuth::user();
$path = App\Core\Application::instance()->request()?->path ?? '/';
$items = ['/' => ['platform.colleges', 'bi-buildings'], '/plans' => ['platform.plans', 'bi-tags'], '/announcements' => ['platform.announcements', 'bi-megaphone'],
    '/reports' => ['platform.reports', 'bi-bar-chart-line'], '/settings' => ['platform.settings', 'bi-gear']];
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <?= $this->partial('layouts/partials/head', ['title' => $title ?? null]) ?>
</head>
<body class="app-body">
<nav class="navbar navbar-expand-md bg-white border-bottom sticky-top">
    <div class="container-xl">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= e(url('/')) ?>">
            <?= $this->partial('layouts/partials/logo') ?> <?= e(config('app.name')) ?> <span class="badge text-bg-dark fw-normal"><?= e(__('platform.owner')) ?></span>
        </a>
        <?php if ($admin !== null): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pnav" aria-controls="pnav" aria-expanded="false" aria-label="<?= e(__('platform.menu')) ?>"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="pnav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($items as $href => [$label, $icon]): ?>
                        <?php $active = $href === '/' ? ($path === '/' || str_starts_with($path, '/colleges') || str_starts_with($path, '/invoices')) : str_starts_with($path, $href); ?>
                        <li class="nav-item"><a class="nav-link<?= $active ? ' active fw-semibold' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?> href="<?= e(url($href)) ?>"><i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e(__($label)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <?= $this->partial('layouts/partials/lang-switch', ['class' => 'btn-outline-secondary btn-sm']) ?>
                    <a href="<?= e(url('/password')) ?>" class="btn btn-sm btn-light"><i class="bi bi-person-lock me-1" aria-hidden="true"></i><?= e($admin['name']) ?></a>
                    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('auth.logout')) ?></button></form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>
<main id="main" class="container-xl py-4">
    <?= $content ?>
</main>
<?= $this->partial('layouts/partials/toasts') ?>
<?= $this->partial('layouts/partials/confirm-modal') ?>
<?= $this->partial('layouts/partials/i18n') ?>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
