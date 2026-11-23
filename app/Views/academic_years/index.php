<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $years */
$statusClass = ['active' => 'app-badge-success', 'planned' => 'app-badge-primary', 'archived' => 'app-badge-muted'];
$today = date('Y-m-d');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('years.title'),
    'subtitle' => __('years.subtitle'),
    'actions' => '<a href="' . e(url('/academic-years/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('years.create')) . '</a>',
]) ?>

<div class="row g-3">
    <?php foreach ($years as $year): ?>
        <?php
        $id = (int) $year['id'];
        $usage = (int) $year['groups_count'] + (int) $year['events_count'];
        $isActive = $year['status'] === 'active';
        $isArchived = $year['status'] === 'archived';
        ?>
        <div class="col-12 col-md-6 col-xxl-3" id="year-<?= e($id) ?>">
            <div class="card h-100<?= $isActive ? ' border-primary' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="h4 fw-bold mb-0"><?= e(str_replace('-', '–', (string) $year['title'])) ?></div>
                        <span class="badge <?= e($statusClass[$year['status']] ?? '') ?>"><?= e(__('years.statuses.' . $year['status'])) ?></span>
                    </div>
                    <div class="small text-muted mb-3"><?= e(format_date($year['start_date'])) ?> — <?= e(format_date($year['end_date'])) ?></div>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($year['semesters'] as $semester): ?>
                            <?php $current = $semester['start_date'] <= $today && $today <= $semester['end_date']; ?>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span><?= e(__('dashboard.semester', ['number' => $semester['number']])) ?><?php if ($current): ?> <span class="badge text-bg-primary"><?= e(__('dashboard.current')) ?></span><?php endif; ?></span>
                                <span class="text-muted"><?= e(format_date($semester['start_date'], 'd.m')) ?> — <?= e(format_date($semester['end_date'], 'd.m.Y')) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($year['semesters'] === []): ?><li class="text-warning-emphasis"><?= e(__('years.no_semesters')) ?></li><?php endif; ?>
                    </ul>
                    <div class="small text-muted"><?= e(__('years.usage', ['groups' => $year['groups_count'], 'events' => $year['events_count']])) ?></div>
                </div>
                <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                    <?php if (!$isArchived): ?>
                        <a href="<?= e(url('/academic-years/' . $id . '/edit')) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__('common.edit')) ?></a>
                    <?php endif; ?>
                    <a href="<?= e(url('/calendar?year=' . $id)) ?>" class="btn btn-sm btn-light"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= e(__('nav.calendar')) ?></a>
                    <?php if (!$isActive && !$isArchived): ?>
                        <form method="post" action="<?= e(url('/academic-years/' . $id . '/activate')) ?>" data-confirm="<?= e(__('years.activate_confirm', ['title' => $year['title']])) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= e(__('years.activate')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$isActive && !$isArchived): ?>
                        <form method="post" action="<?= e(url('/academic-years/' . $id . '/archive')) ?>" data-confirm="<?= e(__('years.archive_confirm')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-light"><i class="bi bi-archive me-1" aria-hidden="true"></i><?= e(__('years.archive')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isArchived): ?>
                        <form method="post" action="<?= e(url('/academic-years/' . $id . '/restore')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-light"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= e(__('years.restore')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (!$isActive && $usage === 0): ?>
                        <form method="post" action="<?= e(url('/academic-years/' . $id)) ?>" data-confirm="<?= e(__('years.delete_confirm')) ?>" data-confirm-danger class="ms-auto">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
