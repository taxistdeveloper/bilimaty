<?php
/** @var App\Core\View $this */
$cards = [
    ['reports.performance', 'reports.performance_hint', 'bi-mortarboard', '/reports/performance', can('reports.view')],
    ['reports.attendance', 'reports.attendance_hint', 'bi-calendar-check', '/reports/attendance', can('reports.view')],
    ['workload.reports', 'reports.workload_hint', 'bi-hourglass-split', '/workload/reports', can('workload.view') || can('workload.manage')],
    ['surveys.title', 'reports.surveys_hint', 'bi-ui-checks', '/surveys', can('surveys.results') || can('surveys.manage')],
];
?>
<?= $this->partial('partials/page-header', ['heading' => __('reports.title'), 'subtitle' => __('reports.subtitle')]) ?>
<div class="row g-3">
    <?php foreach ($cards as [$title, $hint, $icon, $path, $allowed]): if (!$allowed) { continue; } ?>
        <div class="col-12 col-md-6 col-xl-3">
            <a href="<?= e(url($path)) ?>" class="card h-100 text-decoration-none text-reset app-card-link">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1"><i class="bi <?= e($icon) ?> text-primary fs-5" aria-hidden="true"></i><span class="fw-semibold"><?= e(__($title)) ?></span></div>
                    <p class="small text-muted mb-0"><?= e(__($hint)) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
