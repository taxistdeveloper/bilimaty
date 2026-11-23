<?php
/**
 * Subscription notice of a college (several colleges on one installation).
 * Administrators see the reminder before the end and during the grace period; everybody sees read-only mode.
 */
$college = App\Core\Tenant::current();
if ($college === null) {
    return;
}
$state = App\Core\Tenant::state();
$isAdmin = App\Core\Auth::hasRole(App\Core\Auth::SUPER_ADMIN, 'admin');
$message = match (true) {
    $state === 'readonly' => __('platform.banner_readonly', ['date' => format_date((string) $college['paid_until'])]),
    $state === 'grace' && $isAdmin => __('platform.banner_grace', ['date' => format_date(App\Core\Tenant::graceEnd())]),
    $state === 'expiring' && $isAdmin => __('platform.banner_expiring', ['date' => format_date((string) $college['paid_until']), 'days' => max(0, App\Core\Tenant::daysLeft())]),
    default => null,
};
if ($message === null) {
    return;
}
$class = $state === 'expiring' ? 'alert-warning' : 'alert-danger';
?>
<div class="alert <?= e($class) ?> d-flex gap-2 align-items-start small mb-3" role="status">
    <i class="bi bi-credit-card-2-front fs-5" aria-hidden="true"></i>
    <div><?= e($message) ?><?php if ($isAdmin): ?> <?= e(__('platform.banner_contact')) ?><?php endif; ?></div>
</div>
