<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array<string, int> $counts */
/** @var list<array<string, mixed>> $types */
$tabs = ['active' => ($counts['submitted'] ?? 0) + ($counts['in_review'] ?? 0) + ($counts['needs_info'] ?? 0) + ($counts['approved'] ?? 0)];
foreach (['submitted', 'in_review', 'needs_info', 'approved', 'completed', 'rejected', 'all'] as $s) {
    $tabs[$s] = $s === 'all' ? null : ($counts[$s] ?? 0);
}
$typeOptions = [];
foreach ($types as $t) {
    $typeOptions[(int) $t['id']] = localized($t);
}
$base = array_filter(['type' => $filters['type'], 'q' => $filters['q'], 'mine' => $filters['mine'] ? '1' : null, 'overdue' => $filters['overdue'] ? '1' : null], static fn ($v) => $v !== null && $v !== '');
$today = date('Y-m-d');
?>
<?= $this->partial('partials/page-header', ['heading' => __('documents.title'), 'subtitle' => __('documents.subtitle')]) ?>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 small">
    <?php foreach ($tabs as $s => $n): ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(url('/documents?' . http_build_query(['status' => $s] + $base))) ?>"><?= e(__('documents.tabs.' . $s)) ?><?php if ($n !== null): ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($n) ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/documents')) ?>" class="card-body row g-2 align-items-end">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <div class="col-12 col-md-4">
            <label class="form-label small text-muted mb-1" for="dq"><?= e(__('common.search')) ?></label>
            <input class="form-control" id="dq" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('documents.search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-3"><?= $this->partial('partials/filter-select', ['name' => 'type', 'label' => __('documents.type'), 'items' => $typeOptions, 'current' => $filters['type']]) ?></div>
        <div class="col-6 col-md-3 d-flex flex-column gap-1 pb-1">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="dmine" name="mine" value="1"<?= $filters['mine'] ? ' checked' : '' ?>><label class="form-check-label small" for="dmine"><?= e(__('documents.only_mine')) ?></label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="doverdue" name="overdue" value="1"<?= $filters['overdue'] ? ' checked' : '' ?>><label class="form-check-label small" for="doverdue"><?= e(__('documents.only_overdue')) ?><?= !empty($counts['overdue']) ? ' (' . e($counts['overdue']) . ')' : '' ?></label></div>
        </div>
        <div class="col-6 col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
</div>

<?php if ($result['items'] === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-inbox" aria-hidden="true"></i><p class="mb-0"><?= e(__('documents.empty_inbox')) ?></p></div></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th><?= e(__('documents.number')) ?></th>
                    <th><?= e(__('journal.student')) ?></th>
                    <th><?= e(__('documents.type')) ?></th>
                    <th><?= e(__('documents.submitted_at')) ?></th>
                    <th><?= e(__('documents.due_date')) ?></th>
                    <th><?= e(__('documents.assignee')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $r):
                    $overdue = $r['due_date'] !== null && $r['due_date'] < $today && in_array($r['status'], ['submitted', 'in_review', 'needs_info'], true); ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?= e(url('/documents/' . $r['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($r['number']) ?></a></td>
                        <td><?= e(full_name($r, true)) ?><div class="small text-muted"><?= e($r['group_code'] ?? '—') ?> · <?= e($r['student_number']) ?></div></td>
                        <td><?= e(localized(['name_ru' => $r['type_name_ru'], 'name_kk' => $r['type_name_kk']])) ?></td>
                        <td class="text-nowrap small"><?= e(format_date((string) $r['submitted_at'], 'd.m.Y H:i')) ?></td>
                        <td class="text-nowrap small<?= $overdue ? ' text-danger fw-semibold' : '' ?>"><?= e($r['due_date'] !== null ? format_date((string) $r['due_date']) : '—') ?><?php if ($overdue): ?> <i class="bi bi-exclamation-circle" title="<?= e(__('documents.overdue')) ?>" aria-label="<?= e(__('documents.overdue')) ?>"></i><?php endif; ?></td>
                        <td class="small"><?= e($r['assigned_to'] !== null ? full_name(['last_name' => $r['assignee_last_name'], 'first_name' => $r['assignee_first_name'], 'middle_name' => $r['assignee_middle_name']], true) : '—') ?></td>
                        <td><?= $this->partial('documents/_status', ['status' => $r['status']]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => ['status' => $filters['status']] + $base, 'path' => '/documents']) ?>
<?php endif; ?>
