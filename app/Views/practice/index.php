<?php
/**
 * @var App\Core\View $this
 * @var list<array<string, mixed>> $list
 * @var array<string, int> $counts
 * @var array{status: string, teacher: int, group: int, overdue: bool} $filters
 * @var bool $own  the signed-in teacher's own practices
 * @var array<int, string> $teachers
 * @var array<int, string> $groups
 */
use App\Services\PracticeService;

$actions = '<a href="' . e(url('/practice/day')) . '" class="btn btn-outline-secondary"><i class="bi bi-calendar-day me-1" aria-hidden="true"></i>' . e(__('practice.day_title')) . '</a>';
if (can('practice.manage')) {
    $actions .= '<a href="' . e(url('/practice/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('practice.assign')) . '</a>';
}
$stats = [
    ['practice.stats_assigned', ($counts['assigned'] ?? 0) + ($counts['in_progress'] ?? 0) + ($counts['returned'] ?? 0), 'bi-pencil-square', 'primary', ''],
    ['practice.stats_submitted', $counts['submitted'] ?? 0, 'bi-hourglass-split', 'warning', 'status=submitted'],
    ['practice.stats_approved', $counts['approved'] ?? 0, 'bi-check2-circle', 'success', 'status=approved'],
    ['practice.stats_overdue', $counts['overdue'] ?? 0, 'bi-alarm', 'danger', 'overdue=1'],
];
?>
<?= $this->partial('partials/page-header', ['heading' => __('practice.title'), 'subtitle' => __($own ? 'practice.subtitle_own' : 'practice.subtitle'), 'actions' => $actions]) ?>

<div class="row g-3 mb-3">
    <?php foreach ($stats as [$label, $value, $icon, $tone, $query]): ?>
        <div class="col-6 col-lg-3">
            <a class="card h-100 text-decoration-none text-body" href="<?= e(url('/practice' . ($query !== '' ? '?' . $query : ''))) ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-3 d-inline-flex align-items-center justify-content-center bg-<?= e($tone) ?>-subtle text-<?= e($tone) ?>" style="width: 44px; height: 44px"><i class="bi <?= e($icon) ?> fs-5" aria-hidden="true"></i></span>
                    <span><span class="d-block fs-4 fw-bold lh-1"><?= e($value) ?></span><span class="small text-muted"><?= e(__($label)) ?></span></span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (!$own): ?>
        <form method="get" action="<?= e(url('/practice')) ?>" class="card-body border-bottom row g-2 align-items-end">
            <div class="col-6 col-lg-3">
                <label class="form-label small text-muted mb-1" for="status"><?= e(__('common.status')) ?></label>
                <select class="form-select" id="status" name="status"><option value=""><?= e(__('practice.all_statuses')) ?></option>
                    <?php foreach (PracticeService::STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e(__('practice.statuses.' . $s)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-6 col-lg-3"><?= $this->partial('partials/filter-select', ['name' => 'teacher', 'label' => __('practice.f_teacher'), 'items' => $teachers, 'current' => $filters['teacher'] ?: '']) ?></div>
            <div class="col-6 col-lg-2"><?= $this->partial('partials/filter-select', ['name' => 'group', 'label' => __('practice.f_group'), 'items' => $groups, 'current' => $filters['group'] ?: '']) ?></div>
            <div class="col-6 col-lg-2">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1"<?= $filters['overdue'] ? ' checked' : '' ?>>
                    <label class="form-check-label small" for="overdue"><?= e(__('practice.overdue_only')) ?></label></div>
            </div>
            <div class="col-12 col-lg-2"><button type="submit" class="btn btn-primary w-100 text-nowrap"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
        </form>
    <?php endif; ?>
    <?php if ($list === []): ?>
        <div class="app-empty"><i class="bi bi-briefcase" aria-hidden="true"></i><p class="mb-0"><?= e(__($own ? 'practice.empty_own' : 'practice.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th scope="col"><?= e(__('practice.f_title')) ?></th>
                    <th scope="col"><?= e(__('practice.group_short')) ?></th>
                    <?php if (!$own): ?><th scope="col"><?= e(__('practice.supervisor_short')) ?></th><?php endif; ?>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('practice.period')) ?></th>
                    <th scope="col"><?= e(__('practice.deadline_short')) ?></th>
                    <th scope="col"><?= e(__('common.status')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($list as $p): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/practice/' . $p['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($p['title']) ?></a>
                            <div class="small text-muted"><?= e(__('practice.types.' . $p['practice_type'])) ?> · <?= e(__('practice.f_hours')) ?>: <?= e($p['hours']) ?></div>
                        </td>
                        <td class="text-nowrap"><?= e($p['group_code']) ?></td>
                        <?php if (!$own): ?><td class="text-nowrap"><?= e(full_name(['last_name' => $p['teacher_last_name'], 'first_name' => $p['teacher_first_name'], 'middle_name' => $p['teacher_middle_name']], true)) ?></td><?php endif; ?>
                        <td class="d-none d-md-table-cell small text-nowrap"><?= $p['first_date'] !== null ? e(format_date($p['first_date']) . ' – ' . format_date($p['last_date'])) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-nowrap<?= PracticeService::isOverdue($p) ? ' text-danger fw-semibold' : '' ?>"><?= e(format_date($p['deadline'])) ?></td>
                        <td class="text-nowrap"><?= $this->partial('practice/_status', ['assignment' => $p]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
