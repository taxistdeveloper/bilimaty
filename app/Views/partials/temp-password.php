<?php
/**
 * One-time display of an issued temporary password.
 *
 * @var array{login: string, password: string}|null $tempPassword
 */
?>
<?php if (is_array($tempPassword)): ?>
    <div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center gap-3" role="alert">
        <i class="bi bi-key-fill fs-4" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold"><?= e(__('users.temp_password_title')) ?></div>
            <div class="small"><?= e(__('users.temp_password_text', ['login' => $tempPassword['login']])) ?></div>
        </div>
        <div class="input-group app-temp-password">
            <input type="text" class="form-control font-monospace" value="<?= e($tempPassword['password']) ?>" readonly id="tempPassword" aria-label="<?= e(__('users.temp_password_title')) ?>">
            <button class="btn btn-outline-primary" type="button" data-copy="#tempPassword" data-copied-text="<?= e(__('common.copied')) ?>">
                <i class="bi bi-clipboard" aria-hidden="true"></i> <?= e(__('common.copy')) ?>
            </button>
        </div>
    </div>
<?php endif; ?>
