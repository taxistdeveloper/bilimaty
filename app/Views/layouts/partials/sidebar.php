<?php
/** @var App\Core\View $this */
$currentPath = App\Core\Application::instance()->request()?->path ?? '/';
$items = (array) config('navigation', []);

// Drop items the user cannot access, then drop sections left empty.
$visible = [];
foreach ($items as $item) {
    if (isset($item['section'])) {
        $visible[] = $item;
        continue;
    }
    if (App\Core\Auth::canAny(...$item['can']) && (!isset($item['roles']) || App\Core\Auth::hasRole(...$item['roles']))) {
        $visible[] = $item;
    }
}
$visible = array_values(array_filter($visible, static function (array $item, int $i) use ($visible): bool {
    return !isset($item['section']) || (isset($visible[$i + 1]) && !isset($visible[$i + 1]['section']));
}, ARRAY_FILTER_USE_BOTH));
?>
<aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="app-sidebar__brand">
        <a href="<?= e(url('/dashboard')) ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <?= $this->partial('layouts/partials/logo') ?>
            <span class="d-flex flex-column lh-sm min-w-0">
                <span id="appSidebarLabel" class="app-sidebar__title"><?= e(config('app.name')) ?></span>
                <?php $college = App\Services\Settings::collegeName(); ?>
                <?php if ($college !== ''): ?><span class="app-sidebar__college text-truncate"><?= e($college) ?></span><?php endif; ?>
            </span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#appSidebar" aria-label="<?= e(__('common.close')) ?>"></button>
    </div>

    <nav class="app-sidebar__nav" aria-label="<?= e(__('common.menu')) ?>">
        <ul class="list-unstyled mb-0">
            <?php foreach ($visible as $item): ?>
                <?php if (isset($item['section'])): ?>
                    <li class="app-nav__section"><?= e(__($item['section'])) ?></li>
                <?php elseif (isset($item['stage'])): ?>
                    <li>
                        <span class="app-nav__link is-disabled" aria-disabled="true"
                              title="<?= e(__('nav.coming_soon_hint', ['stage' => $item['stage']])) ?>">
                            <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                            <span class="flex-grow-1"><?= e(__($item['label'])) ?></span>
                            <span class="app-nav__badge"><?= e(__('nav.coming_soon', ['stage' => $item['stage']])) ?></span>
                        </span>
                    </li>
                <?php else: ?>
                    <?php
                    // The most specific matching menu path wins (/workload/reports vs /workload).
                    $active = ($currentPath === $item['path'] || str_starts_with($currentPath, $item['path'] . '/'))
                        && array_filter($visible, static fn (array $other): bool => isset($other['path']) && strlen($other['path']) > strlen($item['path'])
                            && ($currentPath === $other['path'] || str_starts_with($currentPath, $other['path'] . '/'))) === [];
                    ?>
                    <li>
                        <a href="<?= e(url($item['path'])) ?>" class="app-nav__link<?= $active ? ' is-active' : '' ?>"
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                            <span class="flex-grow-1"><?= e(__($item['label'])) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
