<?php
/** @var App\Core\View $this */
/** @var string $type */
/** @var string $token */
/** @var list<array{line: int, source: array<string, string>, values: array<string, mixed>, errors: list<string>}> $rows */
/** @var list<string> $unknown columns that were not recognised */
/** @var array<string, list<string>> $columns */
$ok = count(array_filter($rows, static fn (array $r): bool => $r['errors'] === []));
$bad = count($rows) - $ok;
$fields = array_values(array_filter(array_keys($columns), static fn (string $f): bool => array_filter($rows, static fn (array $r): bool => ($r['source'][$f] ?? '') !== '') !== []));
$showOnlyErrors = $bad > 0 && count($rows) > 50;
?>
<?= $this->partial('partials/page-header', ['heading' => __('import.preview'), 'subtitle' => __('import.title_' . $type), 'back' => '/import/' . $type]) ?>

<div class="row g-3 mb-3">
    <div class="col-4"><div class="card app-stat h-100"><div class="card-body"><div class="app-stat__label"><?= e(__('import.rows_total')) ?></div><div class="app-stat__value"><?= e(count($rows)) ?></div></div></div></div>
    <div class="col-4"><div class="card app-stat h-100"><div class="card-body"><div class="app-stat__label"><?= e(__('import.rows_ok')) ?></div><div class="app-stat__value text-success"><?= e($ok) ?></div></div></div></div>
    <div class="col-4"><div class="card app-stat h-100"><div class="card-body"><div class="app-stat__label"><?= e(__('import.rows_bad')) ?></div><div class="app-stat__value<?= $bad > 0 ? ' text-danger' : '' ?>"><?= e($bad) ?></div></div></div></div>
</div>
<?php if ($unknown !== []): ?><div class="alert alert-warning small"><?= e(__('import.unknown_columns', ['columns' => implode(', ', $unknown)])) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/import/' . $type . '/preview/' . $token)) ?>" class="card mb-3" data-confirm="<?= e(__('import.confirm', ['n' => $ok])) ?>">
    <?= csrf_field() ?>
    <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <span class="small"><?= e($bad > 0 ? __('import.only_valid', ['ok' => $ok, 'bad' => $bad]) : __('import.all_valid')) ?></span>
        <div class="d-flex gap-2">
            <a href="<?= e(url('/import/' . $type)) ?>" class="btn btn-outline-secondary"><?= e(__('import.another_file')) ?></a>
            <button type="submit" class="btn btn-success"<?= $ok === 0 ? ' disabled' : '' ?> data-loading-text="<?= e(__('common.loading')) ?>"><i class="bi bi-cloud-upload me-1" aria-hidden="true"></i><?= e(__('import.do_import', ['n' => $ok])) ?></button>
        </div>
    </div>
</form>

<?php if ($showOnlyErrors): ?><p class="small text-muted"><?= e(__('import.showing_errors')) ?></p><?php endif; ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 app-table">
            <thead class="table-light">
            <tr>
                <th>№</th>
                <?php foreach ($fields as $f): ?><th class="text-nowrap"><?= e(App\Services\ImportService::heading($columns[$f][0])) ?></th><?php endforeach; ?>
                <th><?= e(__('import.check_result')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): if ($showOnlyErrors && $r['errors'] === []) { continue; } ?>
                <tr class="<?= $r['errors'] !== [] ? 'table-danger' : '' ?>">
                    <td class="text-muted small"><?= e($r['line']) ?></td>
                    <?php foreach ($fields as $f): ?><td class="small"><?= e($r['source'][$f] ?? '') ?></td><?php endforeach; ?>
                    <td class="small">
                        <?php if ($r['errors'] === []): ?><span class="text-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= e(__('import.row_ok')) ?></span>
                        <?php else: ?><ul class="mb-0 ps-3 text-danger"><?php foreach ($r['errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
