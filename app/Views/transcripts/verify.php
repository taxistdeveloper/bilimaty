<?php
/** @var App\Core\View $this */
/** @var string $code */
/** @var array<string, mixed>|null $result only document facts — never personal data */
$college = App\Services\Settings::collegeName();
?>
<div class="auth-shell justify-content-center align-items-center p-3">
    <div class="card border-0 shadow-sm app-error-card">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2"><?= $this->partial('layouts/partials/logo') ?><span class="fw-bold"><?= e(config('app.name')) ?></span></div>
                <?= $this->partial('layouts/partials/lang-switch', ['class' => 'btn-outline-secondary btn-sm']) ?>
            </div>
            <h1 class="h4 fw-bold"><?= e(__('transcripts.verify_title_any')) ?></h1>
            <?php if ($college !== ''): ?><p class="small text-muted mb-3"><?= e($college) ?></p><?php endif; ?>

            <?php if ($code !== '' && $result === null): ?>
                <div class="alert alert-danger"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i><?= e(__('transcripts.verify_not_found')) ?></div>
            <?php elseif ($result !== null): ?>
                <div class="alert <?= $result['status'] === 'issued' ? 'alert-success' : 'alert-danger' ?>">
                    <div class="fw-semibold"><i class="bi <?= $result['status'] === 'issued' ? 'bi-check-circle' : 'bi-x-octagon' ?> me-1" aria-hidden="true"></i>
                        <?= e($result['status'] === 'issued' ? __('transcripts.verify_valid') : __('transcripts.verify_revoked', ['date' => format_date((string) $result['revoked_at'])])) ?></div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal"><?= e(__('transcripts.document_kind')) ?></dt><dd class="col-7"><?= e(__('transcripts.kinds.' . $result['kind'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('transcripts.number')) ?></dt><dd class="col-7"><?= e($result['number']) ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('transcripts.issued_at')) ?></dt><dd class="col-7"><?= e(format_date((string) $result['issued_at'])) ?></dd>
                    <?php if ($result['kind'] === 'transcript'): ?><dt class="col-5 text-muted fw-normal"><?= e(__('transcripts.subjects_count')) ?></dt><dd class="col-7"><?= e($result['items_count']) ?></dd><?php endif; ?>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('transcripts.fingerprint')) ?></dt><dd class="col-7 font-monospace"><?= e(strtoupper(substr((string) $result['fingerprint'], 0, 16))) ?></dd>
                </dl>
                <p class="small text-muted mt-3 mb-0"><?= e(__('transcripts.verify_compare')) ?></p>
            <?php endif; ?>

            <form method="get" action="<?= e(url('/verify')) ?>" class="mt-4">
                <label class="form-label small fw-semibold" for="code"><?= e(__('transcripts.verify_code')) ?></label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="code" name="code" maxlength="32" value="<?= e($code) ?>" autocomplete="off" spellcheck="false">
                    <button type="submit" class="btn btn-primary"><?= e(__('transcripts.verify_button')) ?></button>
                </div>
                <div class="form-text"><?= e(__('transcripts.verify_privacy')) ?></div>
            </form>
        </div>
    </div>
</div>
