<?php
/**
 * Demo mode: "view as role" dropdown in the top bar (super admin only, never in production).
 */
if (!App\Services\DemoRoleSwitch::enabled()) {
    return;
}
$options = App\Services\DemoRoleSwitch::options();
$active = App\Services\DemoRoleSwitch::active();
$currentName = '';
foreach ($options as $option) {
    if ($option['current'] && ($option['slug'] === App\Services\DemoRoleSwitch::currentRole() || !$active)) {
        $currentName = $option['name'];
    }
}
?>
<div class="dropdown">
    <button class="btn <?= $active ? 'btn-warning' : 'btn-light' ?> dropdown-toggle d-inline-flex align-items-center gap-2"
            type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(__('demo.view_as_hint')) ?>">
        <i class="bi bi-eye" aria-hidden="true"></i>
        <span class="d-none d-xl-inline small fw-semibold text-truncate" style="max-width: 14rem"><?= e(__('demo.view_as')) ?>: <?= e($currentName) ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 20rem">
        <div class="px-3 py-1 small text-muted"><?= e(__('demo.view_as_hint')) ?></div>
        <div class="dropdown-divider"></div>
        <?php foreach ($options as $option): ?>
            <form method="post" action="<?= e(url('/demo/role')) ?>" class="px-2">
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= e($option['slug']) ?>">
                <button type="submit" class="dropdown-item rounded d-flex justify-content-between align-items-center gap-3<?= $option['current'] && ($option['slug'] === App\Services\DemoRoleSwitch::currentRole() || !$active) ? ' active' : '' ?>"
                        <?= $option['user_id'] === null ? 'disabled' : '' ?>>
                    <span><?= e($option['name']) ?></span>
                    <span class="small opacity-75 text-truncate" style="max-width: 9rem"><?= e($option['user_name'] ?? '—') ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
