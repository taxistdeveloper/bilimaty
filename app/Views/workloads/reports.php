<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $report */
/** @var list<string> $header */
/** @var array<string, array<int|string, string>> $options */
$by = $report['by'];
$params = array_filter([
    'year' => $filters['year'], 'semester' => $filters['semester'], 'department' => $filters['department'], 'teacher' => $filters['teacher'],
    'group' => $filters['group'], 'type' => $filters['type'], 'as_of' => $filters['as_of'], 'by' => $by,
], static fn ($v): bool => $v !== null && $v !== '');
$query = http_build_query($params);
$byOptions = [];
foreach (App\Services\WorkloadService::GROUP_BY as $key) {
    $byOptions[$key] = __('workload.by.' . $key);
}
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('workload.reports'),
    'subtitle' => __('workload.reports_subtitle'),
    'back' => '/workload?year=' . $filters['year'],
    'actions' => '<a href="' . e(url('/workload/reports/print?' . $query)) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
        . '<a href="' . e(url('/workload/reports/export?' . $query)) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>',
]) ?>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/workload/reports')) ?>" class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3"><?= $this->partial('partials/filter-select', ['name' => 'by', 'label' => __('workload.report_kind'), 'items' => $byOptions, 'current' => $by, 'all' => false]) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $this->partial('partials/filter-select', ['name' => 'year', 'label' => __('fields.academic_year_id'), 'items' => $options['years'], 'current' => $filters['year'], 'all' => false]) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $this->partial('partials/filter-select', ['name' => 'semester', 'label' => __('fields.semester_id'), 'items' => $options['semesters'], 'current' => $filters['semester']]) ?></div>
        <div class="col-6 col-md-4 col-xl-3"><?= $this->partial('partials/filter-select', ['name' => 'department', 'label' => __('fields.department_id'), 'items' => $options['departments'], 'current' => $filters['department']]) ?></div>
        <div class="col-6 col-md-4 col-xl-2"><?= $this->partial('partials/filter-select', ['name' => 'teacher', 'label' => __('workload.teacher'), 'items' => $options['teachers'], 'current' => $filters['teacher']]) ?></div>
        <div class="col-6 col-md-4 col-xl-3"><?= $this->partial('partials/filter-select', ['name' => 'group', 'label' => __('fields.group_id'), 'items' => $options['groups'], 'current' => $filters['group']]) ?></div>
        <div class="col-6 col-md-4 col-xl-3"><?= $this->partial('partials/filter-select', ['name' => 'type', 'label' => __('fields.workload_type_id'), 'items' => $options['types'], 'current' => $filters['type']]) ?></div>
        <div class="col-6 col-md-4 col-xl-3">
            <label class="form-label small text-muted mb-1" for="flt_as_of"><?= e(__('workload.as_of')) ?></label>
            <input type="date" class="form-control" id="flt_as_of" name="as_of" value="<?= e($filters['as_of']) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
</div>

<p class="small text-muted"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('workload.fact_hint')) ?></p>

<?php if ($report['rows'] === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><p class="mb-0"><?= e(__('workload.report_empty')) ?></p></div></div>
<?php else: ?>
    <div class="card"><?= $this->partial('workloads/_report_table', ['report' => $report, 'header' => $header, 'doc' => false, 'yearId' => $filters['year']]) ?></div>
<?php endif; ?>
