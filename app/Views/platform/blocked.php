<?php
/**
 * A college suspended by the platform owner.
 *
 * @var array<string, mixed>|null $college
 */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(__('platform.blocked_title') . ' · ' . config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="auth-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<main class="card shadow-sm text-center" style="max-width: 480px">
    <div class="card-body p-4">
        <img src="<?= e(asset('assets/images/mascot-tablet.png')) ?>" alt="" width="100" class="mb-3">
        <h1 class="h4 fw-bold"><?= e(__('platform.blocked_title')) ?></h1>
        <?php if ($college !== null): ?><p class="fw-semibold text-primary mb-1"><?= e($college['name']) ?></p><?php endif; ?>
        <p class="text-muted mb-0"><?= e(__('platform.blocked_text')) ?></p>
    </div>
</main>
</body>
</html>
