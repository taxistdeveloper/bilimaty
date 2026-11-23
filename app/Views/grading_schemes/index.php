<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $schemes */
$statusClass = ['draft' => 'app-badge-warning', 'active' => 'app-badge-success', 'archived' => 'app-badge-muted'];
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('grading.title'),
    'subtitle' => __('grading.subtitle'),
    'actions' => '<a href="' . e(url('/grading-schemes/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('grading.create')) . '</a>',
]) ?>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('grading.formula_note')) ?></div>

<?php if ($schemes === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-sliders" aria-hidden="true"></i><p class="mb-0"><?= e(__('grading.empty')) ?></p></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($schemes as $s): ?>
            <div class="col-12 col-md-6 col-xxl-4">
                <a href="<?= e(url('/grading-schemes/' . $s['id'] . '/edit')) ?>" class="card app-link-card h-100 text-decoration-none text-body">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="fw-semibold"><?= e($s['title']) ?> <span class="text-muted small">v<?= e($s['version']) ?></span></div>
                            <span class="badge <?= e($statusClass[$s['status']] ?? '') ?>"><?= e(__('grading.statuses.' . $s['status'])) ?></span>
                        </div>
                        <div class="small text-muted mb-2">
                            <?= e(__('grading.scale')) ?>: <?= e($num($s['grade_min'])) ?>–<?= e($num($s['grade_max'])) ?>
                            <?php if ($s['pass_threshold'] !== null): ?> · <?= e(__('grading.threshold')) ?>: <?= e($num($s['pass_threshold'])) ?><?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($s['components'] as $c): ?>
                                <span class="badge app-badge-primary"><?= e(localized($c)) ?> · <?= e($num($c['weight'])) ?>%</span>
                            <?php endforeach; ?>
                            <?php if ($s['components'] === []): ?><span class="small text-warning-emphasis"><?= e(__('grading.no_components')) ?></span><?php endif; ?>
                        </div>
                        <div class="small text-muted mt-2"><?= e(__('grading.journals_count', ['count' => $s['journals_count']])) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
