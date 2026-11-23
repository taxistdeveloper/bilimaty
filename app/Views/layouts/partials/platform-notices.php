<?php
/**
 * Several colleges: owner's announcements and the "signed in as the owner" banner.
 */
$college = App\Core\Tenant::current();
if ($college === null) {
    return;
}
$impersonator = App\Core\Session::get('impersonator');
$isAdmin = App\Core\Auth::hasRole(App\Core\Auth::SUPER_ADMIN, 'admin');
?>
<?php if (is_array($impersonator)): ?>
    <div class="alert alert-dark d-flex flex-wrap gap-2 align-items-center justify-content-between small mb-3" role="status">
        <span><i class="bi bi-incognito me-1" aria-hidden="true"></i><?= e(__('platform.impersonating', ['owner' => $impersonator['name'], 'user' => full_name(auth_user() ?? [])])) ?></span>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-light"><?= e(__('platform.impersonate_leave')) ?></button></form>
    </div>
<?php endif; ?>
<?php foreach (App\Platform\CollegeManager::current($college, $isAdmin) as $a): ?>
    <div class="alert <?= $a['level'] === 'warning' ? 'alert-warning' : 'alert-info' ?> small mb-3" role="status">
        <div class="fw-semibold"><i class="bi bi-megaphone me-1" aria-hidden="true"></i><?= e($a['title']) ?></div>
        <?php if ($a['body']): ?><div class="mt-1" style="white-space: pre-line"><?= e($a['body']) ?></div><?php endif; ?>
    </div>
<?php endforeach; ?>
