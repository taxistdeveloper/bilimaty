<?php
/** @var App\Core\View $this */
/** @var string|null $title */
$user = auth_user() ?? [];
$initials = mb_strtoupper(mb_substr((string) ($user['last_name'] ?? ''), 0, 1) . mb_substr((string) ($user['first_name'] ?? ''), 0, 1));
?>
<header class="app-topbar">
    <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
            aria-controls="appSidebar" aria-label="<?= e(__('common.toggle_menu')) ?>">
        <i class="bi bi-list fs-5" aria-hidden="true"></i>
    </button>

    <h1 class="app-topbar__title text-truncate d-none d-sm-block"><?= e($title ?? '') ?></h1>

    <div class="d-flex align-items-center gap-2 ms-auto">
        <?= $this->partial('layouts/partials/demo-switch') ?>
        <?= $this->partial('layouts/partials/lang-switch', ['class' => 'btn-light']) ?>

        <div class="dropdown" id="notificationsDropdown">
            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false" aria-label="<?= e(__('notifications.title')) ?>">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                      data-notifications-count>0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow app-notifications p-0">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <strong class="small"><?= e(__('notifications.title')) ?></strong>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-notifications-read>
                        <?= e(__('notifications.mark_all_read')) ?>
                    </button>
                </div>
                <div class="app-notifications__list" data-notifications-list>
                    <div class="text-center text-muted small py-4"><?= e(__('common.loading')) ?></div>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2 ps-1 pe-2" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <span class="app-avatar" aria-hidden="true"><?= e($initials) ?></span>
                <span class="d-none d-md-inline small fw-semibold text-truncate app-topbar__user"><?= e(full_name($user, true)) ?></span>
                <i class="bi bi-chevron-down small text-muted" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                <div class="px-3 py-2">
                    <div class="fw-semibold"><?= e(full_name($user)) ?></div>
                    <div class="small text-muted"><?= e($user['email'] ?? $user['login'] ?? '') ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <div class="px-2">
                    <a class="dropdown-item rounded d-flex align-items-center gap-2" href="<?= e(url('/profile')) ?>">
                        <i class="bi bi-person-circle" aria-hidden="true"></i><?= e(__('nav.profile')) ?>
                    </a>
                    <a class="dropdown-item rounded d-flex align-items-center gap-2" href="<?= e(url('/profile/password')) ?>">
                        <i class="bi bi-key" aria-hidden="true"></i><?= e(__('profile.change_password')) ?>
                    </a>
                </div>
                <div class="dropdown-divider"></div>
                <form method="post" action="<?= e(url('/logout')) ?>" class="px-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item rounded text-danger d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><?= e(__('auth.logout')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
