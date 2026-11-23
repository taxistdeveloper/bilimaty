<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $groups */
?>
<?= $this->partial('partials/page-header', ['heading' => __('teaching.my_groups'), 'subtitle' => __('teaching.my_groups_hint')]) ?>
<?php if ($groups === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-collection" aria-hidden="true"></i><p class="mb-0"><?= e(__('teaching.no_groups')) ?></p></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($groups as $g): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><?= e($g['code']) ?> <span class="text-muted fw-normal small">· <?= e(__('groups.course_n', ['n' => $g['course']])) ?> · <?= e(__('teaching.students_n', ['n' => $g['students'] ?? 0])) ?></span></span>
                        <?php if ($g['curated']): ?><span class="badge app-badge-primary"><?= e(__('teaching.curator')) ?></span><?php endif; ?>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($g['journals'] as $j): ?>
                            <a href="<?= e(url('/journal/' . $j['id'])) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small">
                                <span><?= e($j['subject_code'] . ' ' . localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']])) ?></span>
                                <span class="badge <?= $j['status'] === 'approved' ? 'app-badge-success' : 'app-badge-muted' ?>"><?= e(__('journal.statuses.' . $j['status'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($g['journals'] === []): ?><div class="list-group-item small text-muted"><?= e(__('teaching.curated_only')) ?></div><?php endif; ?>
                    </div>
                    <?php if (can('reports.view')): ?>
                        <div class="card-footer bg-transparent d-flex flex-wrap gap-2 small">
                            <a href="<?= e(url('/reports/attendance?group=' . $g['id'])) ?>" class="text-decoration-none"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i><?= e(__('reports.attendance')) ?></a>
                            <a href="<?= e(url('/reports/performance?group=' . $g['id'])) ?>" class="text-decoration-none"><i class="bi bi-mortarboard me-1" aria-hidden="true"></i><?= e(__('reports.performance')) ?></a>
                            <?php if (can('groups.view')): ?><a href="<?= e(url('/groups/' . $g['id'])) ?>" class="text-decoration-none"><i class="bi bi-people me-1" aria-hidden="true"></i><?= e(__('teaching.group_card')) ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
