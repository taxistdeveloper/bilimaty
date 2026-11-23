<?php
/**
 * Header, filters and export buttons of a group report (or the document header when printing).
 *
 * @var App\Core\View $this
 * @var string $kind performance|attendance
 * @var array<int, string> $groups
 * @var array<int, string> $semesters
 * @var int $groupId
 * @var int $semesterId
 * @var string $groupCode
 * @var string $semesterTitle
 * @var bool $print
 */
$query = http_build_query(['group' => $groupId, 'semester' => $semesterId]);
?>
<?php if ($print): $college = App\Services\Settings::collegeName(); ?>
    <header class="doc-header">
        <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
        <h1><?= e(__('reports.' . $kind)) ?></h1>
        <div class="doc-meta"><?= e(__('fields.group_id')) ?> <?= e($groupCode) ?> · <?= e($semesterTitle) ?> · <?= e(format_date(date('Y-m-d'))) ?></div>
    </header>
<?php else: ?>
    <?= $this->partial('partials/page-header', [
        'heading' => __('reports.' . $kind),
        'subtitle' => __('reports.' . $kind . '_hint'),
        'back' => '/reports',
        'actions' => $groupId !== 0
            ? '<a href="' . e(url('/reports/' . $kind . '?' . $query . '&format=print')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
              . '<a href="' . e(url('/reports/' . $kind . '?' . $query . '&format=csv')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Excel (CSV)</a>'
            : '',
    ]) ?>
    <div class="card mb-3">
        <form method="get" action="<?= e(url('/reports/' . $kind)) ?>" class="card-body row g-2 align-items-end">
            <div class="col-12 col-md-5"><?= $this->partial('partials/filter-select', ['name' => 'semester', 'label' => __('fields.semester_id'), 'items' => $semesters, 'current' => $semesterId, 'all' => false]) ?></div>
            <div class="col-8 col-md-4"><?= $this->partial('partials/filter-select', ['name' => 'group', 'label' => __('fields.group_id'), 'items' => $groups, 'current' => $groupId, 'all' => false]) ?></div>
            <div class="col-4 col-md-3"><button type="submit" class="btn btn-primary w-100"><?= e(__('common.show')) ?></button></div>
        </form>
    </div>
<?php endif; ?>
