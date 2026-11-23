<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $employee */
/** @var list<array<string, mixed>> $leaves */
/** @var array{entitled: int, used: int, requested: int, left: int}|null $balance */
/** @var int $year */
?>
<?= $this->partial('partials/page-header', ['heading' => __('hr.my_leaves'), 'subtitle' => __('hr.my_leaves_hint')]) ?>

<?php if ($employee === null): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-person-vcard" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.no_file')) ?></p></div></div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('hr.balance_title', ['year' => $year])) ?></div>
                <div class="card-body">
                    <div class="display-6 fw-bold<?= $balance['left'] < 0 ? ' text-danger' : '' ?>"><?= e($balance['left']) ?></div>
                    <div class="small text-muted mb-2"><?= e(__('hr.balance_left_hint')) ?></div>
                    <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_entitled')) ?></span><span><?= e($balance['entitled']) ?></span></div>
                    <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_used')) ?></span><span><?= e($balance['used']) ?></span></div>
                    <div class="small d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.balance_requested')) ?></span><span><?= e($balance['requested']) ?></span></div>
                </div>
            </div>
            <?php if ($employee['status'] !== 'dismissed'): ?>
                <div class="card"><div class="card-header fw-semibold"><?= e(__('hr.leave_request')) ?></div><div class="card-body">
                    <?= $this->partial('hr/_leave_form', ['action' => '/my/leaves', 'types' => App\Services\HrService::SELF_LEAVE_TYPES, 'byHr' => false]) ?>
                    <p class="small text-muted mb-0 mt-2"><?= e(__('hr.sick_note')) ?></p>
                </div></div>
            <?php endif; ?>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('hr.my_history')) ?></div>
                <?php if ($leaves === []): ?>
                    <div class="app-empty"><i class="bi bi-calendar2" aria-hidden="true"></i><p class="mb-0"><?= e(__('hr.no_leaves')) ?></p></div>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($leaves as $l): ?>
                            <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                <span><strong><?= e(__('hr.leave_types.' . $l['type'])) ?></strong>
                                    <span class="text-muted">· <?= e(format_date($l['start_date'])) ?>–<?= e(format_date($l['end_date'])) ?> · <?= e(__('hr.days', ['n' => $l['days']])) ?></span>
                                    <?php if ($l['decision_comment']): ?><span class="d-block text-muted fst-italic"><?= e($l['decision_comment']) ?></span><?php endif; ?></span>
                                <span class="d-flex gap-2 align-items-center">
                                    <?= $this->partial('hr/_leave_status', ['status' => $l['status']]) ?>
                                    <?php if ($l['status'] === 'requested'): ?>
                                        <form method="post" action="<?= e(url('/my/leaves/' . $l['id'] . '/cancel')) ?>" data-confirm="<?= e(__('hr.leave_cancel_confirm')) ?>" data-confirm-danger><?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0"><?= e(__('hr.withdraw')) ?></button></form>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
