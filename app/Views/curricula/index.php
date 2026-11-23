<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var list<array<string, mixed>> $plans */
/** @var array<int, string> $specialties */
$statusClass = ['draft' => 'app-badge-warning', 'approved' => 'app-badge-success', 'archived' => 'app-badge-muted'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('curricula.title'),
    'subtitle' => __('curricula.subtitle'),
    'actions' => can('curricula.manage') ? '<a href="' . e(url('/curricula/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('curricula.create')) . '</a>' : '',
]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/curricula')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1" for="specialty"><?= e(__('fields.specialty_id')) ?></label>
            <select class="form-select" id="specialty" name="specialty">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($specialties as $id => $label): ?><option value="<?= e($id) ?>"<?= $filters['specialty'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-1" for="year"><?= e(__('curricula.intake')) ?></label>
            <input type="number" class="form-control" id="year" name="year" min="2000" max="2100" value="<?= e($filters['year'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (array_keys($statusClass) as $s): ?><option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(__('curricula.statuses.' . $s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/curricula')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>
    <?php if ($plans === []): ?>
        <div class="app-empty"><i class="bi bi-journal-text" aria-hidden="true"></i><p class="mb-0"><?= e(__('curricula.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('curricula.one')) ?></th>
                    <th scope="col"><?= e(__('fields.specialty_id')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('curricula.intake')) ?></th>
                    <th scope="col" class="text-center d-none d-md-table-cell"><?= e(__('curricula.hours')) ?></th>
                    <th scope="col" class="text-center d-none d-md-table-cell"><?= e(__('nav.groups')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/curricula/' . $plan['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($plan['title']) ?></a>
                            <div class="small text-muted"><?= e(__('groups.forms.' . $plan['study_form'])) ?> · <?= e(__('curricula.bases.' . $plan['base_education'])) ?></div>
                        </td>
                        <td class="small"><?= e($plan['specialty_code']) ?><div class="text-muted"><?= e($plan['qualification_code'] ?? '') ?></div></td>
                        <td class="text-center"><?= e($plan['admission_year']) ?></td>
                        <td class="text-center d-none d-md-table-cell"><?= e($plan['hours_total']) ?></td>
                        <td class="text-center d-none d-md-table-cell"><?= e($plan['groups_count']) ?></td>
                        <td><span class="badge <?= e($statusClass[$plan['status']] ?? '') ?>"><?= e(__('curricula.statuses.' . $plan['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
