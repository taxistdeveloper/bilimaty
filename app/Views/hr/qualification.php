<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed> $filters */
/** @var array<int, string> $departments */
/** @var array<int, array<string, mixed>> $expiring */
/** @var array<int, array<string, mixed>> $due */
/** @var bool $print */
$today = date('Y-m-d');
$query = http_build_query(array_filter(['department' => $filters['department'], 'q' => $filters['q']], static fn ($v): bool => $v !== null && $v !== ''));
?>
<?php if ($print): $college = App\Services\Settings::collegeName(); ?>
    <header class="doc-header">
        <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
        <h1><?= e(__('hr.qualification')) ?></h1>
        <div class="doc-meta"><?= e(format_date($today)) ?></div>
    </header>
<?php else: ?>
    <?= $this->partial('partials/page-header', [
        'heading' => __('hr.qualification'),
        'subtitle' => __('hr.qualification_hint'),
        'actions' => '<a href="' . e(url('/hr/qualification?' . $query . ($query !== '' ? '&' : '') . 'format=print')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
            . '<a href="' . e(url('/hr/qualification?' . $query . ($query !== '' ? '&' : '') . 'format=csv')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>',
    ]) ?>
    <?= $this->partial('hr/_nav', ['active' => 'qualification']) ?>
    <form method="get" action="<?= e(url('/hr/qualification')) ?>" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1" for="department"><?= e(__('fields.department_id')) ?></label>
            <select class="form-select" id="department" name="department"><option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($departments as $id => $label): ?><option value="<?= e($id) ?>"<?= $filters['department'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-6 col-md-2"><button type="submit" class="btn btn-primary w-100"><?= e(__('common.filter')) ?></button></div>
    </form>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-award" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.empty')) ?></p></div></div>
<?php else: ?>
    <div class="<?= $print ? '' : 'card' ?>">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th scope="col">№</th>
                    <th scope="col"><?= e(__('users.name')) ?></th>
                    <th scope="col"><?= e(__('fields.position')) ?></th>
                    <th scope="col"><?= e(__('fields.qualification_category')) ?></th>
                    <th scope="col"><?= e(__('fields.category_valid_until')) ?></th>
                    <th scope="col"><?= e(__('hr.last_training')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): $id = (int) $r['id']; ?>
                    <tr>
                        <td class="small"><?= e($i + 1) ?></td>
                        <td><?php if ($print): ?><?= e(full_name($r)) ?><?php else: ?><a href="<?= e(url('/hr/employees/' . $id . '?tab=trainings')) ?>" class="text-decoration-none"><?= e(full_name($r)) ?></a><?php endif; ?></td>
                        <td class="small"><?= e($r['position'] ?: '—') ?></td>
                        <td class="small"><?= e($r['qualification_category'] ? __('hr.categories.' . $r['qualification_category']) : '—') ?></td>
                        <td class="small<?= isset($expiring[$id]) ? ($r['category_valid_until'] < $today ? ' text-danger fw-semibold' : ' text-warning-emphasis fw-semibold') : '' ?>"><?= e(format_date($r['category_valid_until']) ?: '—') ?></td>
                        <td class="small<?= isset($due[$id]) ? ' text-danger fw-semibold' : '' ?>"><?= e($r['last_training'] ? format_date($r['last_training']) : __('hr.training_none')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2"><?= e(__('hr.qualification_note', ['days' => config('hr.category_warning_days'), 'years' => config('hr.training_period_years')])) ?></p>
<?php endif; ?>
