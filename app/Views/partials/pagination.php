<?php
/**
 * Server-side pagination that keeps the current filters.
 *
 * @var array{page: int, pages: int, total: int} $result
 * @var array<string, mixed> $filters
 * @var string $path
 */
$page = $result['page'];
$pages = $result['pages'];
$link = static function (int $p) use ($filters, $path): string {
    $query = array_filter($filters + ['page' => $p > 1 ? $p : null], static fn ($v) => $v !== null && $v !== '');

    return url($path) . ($query !== [] ? '?' . http_build_query($query) : '');
};
$window = array_unique(array_filter(
    [1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages],
    static fn (int $p): bool => $p >= 1 && $p <= $pages
));
sort($window);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
    <span class="small text-muted"><?= e(__('common.total', ['count' => $result['total']])) ?></span>
    <?php if ($pages > 1): ?>
        <nav aria-label="<?= e(__('common.pagination')) ?>">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($link(max(1, $page - 1))) ?>" aria-label="<?= e(__('common.prev')) ?>">&laquo;</a>
                </li>
                <?php $previous = 0; foreach ($window as $p): ?>
                    <?php if ($p - $previous > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($link($p)) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= e($p) ?></a>
                    </li>
                <?php $previous = $p; endforeach; ?>
                <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($link(min($pages, $page + 1))) ?>" aria-label="<?= e(__('common.next')) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
