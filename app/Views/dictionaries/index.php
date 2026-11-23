<?php
/** @var App\Core\View $this */
/** @var string $dictionary */
/** @var array<string, mixed> $definition */
/** @var array<string, array<int|string, string>> $options */
/** @var array<string, string> $filters */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
$listFields = array_filter($definition['fields'], static fn (array $f): bool => !empty($f['list']));
$filterFields = array_filter($definition['fields'], static fn (array $f): bool => !empty($f['filter']));
$createQuery = array_filter(array_intersect_key($filters, $filterFields), static fn ($v) => $v !== '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('dictionaries.' . $dictionary . '.title'),
    'back' => '/dictionaries',
    'actions' => '<a href="' . e(url('/dictionaries/' . $dictionary . '/create') . ($createQuery ? '?' . http_build_query($createQuery) : '')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('common.create')) . '</a>',
]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/dictionaries/' . $dictionary)) ?>" class="card-body border-bottom row g-2 align-items-end" role="search">
        <div class="col-12 col-md">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>">
        </div>
        <?php foreach ($filterFields as $name => $field): ?>
            <div class="col-12 col-md">
                <label class="form-label small text-muted mb-1" for="filter_<?= e($name) ?>"><?= e(__('fields.' . $name)) ?></label>
                <select class="form-select" id="filter_<?= e($name) ?>" name="<?= e($name) ?>">
                    <option value=""><?= e(__('common.all')) ?></option>
                    <?php foreach ($options[$name] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= (string) $filters[$name] === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/dictionaries/' . $dictionary)) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi <?= e($definition['icon']) ?>" aria-hidden="true"></i><p class="mb-0"><?= e(__('dictionaries.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <?php foreach ($listFields as $name => $field): ?>
                        <th scope="col"<?= $field['type'] === 'switch' ? ' class="text-center"' : '' ?>><?= e(__('fields.' . $name)) ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="text-end"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr class="<?= (int) $row['is_active'] === 0 ? 'text-muted' : '' ?>">
                        <?php foreach ($listFields as $name => $field): ?>
                            <?php $value = $row[$name]; ?>
                            <td<?= $field['type'] === 'switch' ? ' class="text-center"' : '' ?>>
                                <?php if ($field['type'] === 'switch'): ?>
                                    <i class="bi <?= (int) $value === 1 ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted' ?>" aria-label="<?= e((int) $value === 1 ? __('common.yes') : __('common.no')) ?>"></i>
                                <?php elseif ($field['type'] === 'time'): ?>
                                    <?= e(substr((string) $value, 0, 5)) ?>
                                <?php elseif ($field['type'] === 'select'): ?>
                                    <?= e($value === null ? '—' : ($options[$name][$value] ?? $value)) ?>
                                <?php else: ?>
                                    <?= e($value === null || $value === '' ? '—' : $value) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-end">
                            <a href="<?= e(url('/dictionaries/' . $dictionary . '/' . $row['id'] . '/edit')) ?>" class="btn btn-sm btn-light" aria-label="<?= e(__('common.edit')) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/dictionaries/' . $dictionary]) ?>
</div>
