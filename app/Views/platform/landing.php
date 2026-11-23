<?php
/**
 * Root page of the multi-college site: the college code leads to its sign-in page.
 * Rendered without a session (no college is chosen yet).
 *
 * @var string $code
 * @var string|null $error
 * @var string $base
 */
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(config('app.name')) ?></title>
    <link rel="icon" href="<?= e($base . '/assets/images/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($base . '/assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= e($base . '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= e($base . '/assets/css/app.css') ?>">
</head>
<body class="auth-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<main class="card shadow-sm" style="max-width: 440px; width: 100%">
    <div class="card-body p-4 text-center">
        <img src="<?= e($base . '/assets/images/mascot-wave.png') ?>" alt="" width="93" height="120" class="mb-2">
        <h1 class="h4 fw-bold mb-1"><?= e(config('app.name')) ?></h1>
        <p class="text-muted small mb-4"><?= e(__('platform.landing_hint')) ?></p>
        <form method="get" action="<?= e($base . '/') ?>" class="text-start">
            <label for="code" class="form-label fw-semibold"><?= e(__('platform.landing_code')) ?></label>
            <input type="text" id="code" name="code" value="<?= e($code) ?>" class="form-control form-control-lg<?= $error ? ' is-invalid' : '' ?>" required maxlength="30" autocapitalize="none" spellcheck="false" autofocus
                   placeholder="<?= e(__('platform.landing_placeholder')) ?>"<?= $error ? ' aria-describedby="code_error"' : '' ?>>
            <?php if ($error): ?><div id="code_error" class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><?= e(__('platform.landing_go')) ?></button>
        </form>
    </div>
</main>
</body>
</html>
