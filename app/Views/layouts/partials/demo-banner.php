<?php
/** Demo mode banner: which role is shown and a way back to the administrator. */
if (!App\Services\DemoRoleSwitch::active()) {
    return;
}
$roleName = App\Services\DemoRoleSwitch::currentRoleName();
?>
<div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between small mb-3" role="status">
    <span><i class="bi bi-eye me-1" aria-hidden="true"></i><?= e(__('demo.banner', ['role' => $roleName, 'user' => full_name(auth_user() ?? [])])) ?></span>
    <form method="post" action="<?= e(url('/demo/back')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i><?= e(__('demo.back')) ?></button></form>
</div>
