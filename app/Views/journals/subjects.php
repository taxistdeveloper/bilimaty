<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $subjects */
?>
<?= $this->partial('partials/page-header', ['heading' => __('teaching.my_subjects'), 'subtitle' => __('teaching.my_subjects_hint')]) ?>
<?php if ($subjects === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-book" aria-hidden="true"></i><p class="mb-0"><?= e(__('teaching.no_subjects')) ?></p></div></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light"><tr><th><?= e(__('curricula.subject')) ?></th><th><?= e(__('fields.group_id')) ?></th><th class="text-center"><?= e(__('teaching.lessons')) ?></th><th><?= e(__('common.status')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($subjects as $s): foreach ($s['journals'] as $i => $j): ?>
                    <tr>
                        <?php if ($i === 0): ?><td rowspan="<?= e(count($s['journals'])) ?>" class="fw-semibold"><?= e($s['code']) ?> <?= e($s['name']) ?></td><?php endif; ?>
                        <td><a href="<?= e(url('/journal/' . $j['id'])) ?>" class="text-decoration-none"><?= e($j['group_code']) ?></a></td>
                        <td class="text-center"><?= e($j['lessons_count']) ?></td>
                        <td><span class="badge <?= $j['status'] === 'approved' ? 'app-badge-success' : 'app-badge-muted' ?>"><?= e(__('journal.statuses.' . $j['status'])) ?></span></td>
                    </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
