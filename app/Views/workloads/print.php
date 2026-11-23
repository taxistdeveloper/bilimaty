<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $report */
/** @var list<string> $header */
/** @var string $yearTitle */
/** @var array<string, array<int|string, string>> $options */
$college = App\Services\Settings::collegeName();
$applied = [];
foreach (['semester' => 'fields.semester_id', 'department' => 'fields.department_id', 'teacher' => 'workload.teacher', 'group' => 'fields.group_id', 'type' => 'fields.workload_type_id'] as $key => $label) {
    $optionKey = $key === 'semester' ? 'semesters' : ($key === 'type' ? 'types' : $key . 's');
    if ($filters[$key] !== null && isset($options[$optionKey][$filters[$key]])) {
        $applied[] = __($label) . ': ' . $options[$optionKey][$filters[$key]];
    }
}
?>
<header class="doc-header">
    <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
    <h1><?= e(__('workload.reports')) ?>: <?= e(__('workload.by.' . $report['by'])) ?></h1>
    <div class="doc-meta">
        <?= e(__('fields.academic_year_id')) ?> <?= e($yearTitle) ?> · <?= e(__('workload.as_of')) ?> <?= e(format_date($filters['as_of'])) ?>
        <?php if ($applied !== []): ?><br><?= e(implode(' · ', $applied)) ?><?php endif; ?>
    </div>
</header>
<?php if ($report['rows'] === []): ?>
    <p><?= e(__('workload.report_empty')) ?></p>
<?php else: ?>
    <?= $this->partial('workloads/_report_table', ['report' => $report, 'header' => $header, 'doc' => true, 'yearId' => $filters['year']]) ?>
<?php endif; ?>
<p class="muted-sm" style="font-size: 8pt; color: #64748b"><?= e(__('workload.fact_hint')) ?></p>
<p style="margin-top: 12mm"><?= e(__('workload.signature')) ?> ____________________</p>
