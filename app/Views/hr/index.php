<?php
/** @var App\Core\View $this */
/** @var int $total */
/** @var list<array<string, mixed>> $absent */
/** @var list<array<string, mixed>> $requests */
/** @var list<array<string, mixed>> $drafts */
/** @var array{categories: list<array<string, mixed>>, trainings: list<array<string, mixed>>} $reminders */
/** @var int $withoutFile */
$today = date('Y-m-d');
$stats = [
    ['hr.stat_staff', $total, 'bi-people', '/hr/employees'],
    ['hr.stat_absent', count($absent), 'bi-calendar2-x', '/hr/leaves'],
    ['hr.stat_requests', count($requests), 'bi-inbox', '/hr/leaves?status=requested'],
    ['hr.stat_reminders', count($reminders['categories']) + count($reminders['trainings']), 'bi-bell', '/hr/qualification'],
];
?>
<?= $this->partial('partials/page-header', ['heading' => __('hr.title'), 'subtitle' => __('hr.subtitle')]) ?>
<?= $this->partial('hr/_nav', ['active' => 'index']) ?>

<?php if ($withoutFile > 0): ?>
    <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('hr.teachers_without_file', ['count' => $withoutFile])) ?></span>
        <form method="post" action="<?= e(url('/hr/employees/register-teachers')) ?>" data-confirm="<?= e(__('hr.register_confirm')) ?>"><?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('hr.register_teachers')) ?></button>
        </form>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($stats as [$label, $value, $icon, $link]): ?>
        <div class="col-6 col-xl-3">
            <a href="<?= e(url($link)) ?>" class="card h-100 text-decoration-none text-reset app-card-link">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi <?= e($icon) ?> fs-3 text-primary" aria-hidden="true"></i>
                    <div><div class="fs-4 fw-bold lh-1"><?= e($value) ?></div><div class="small text-muted"><?= e(__($label)) ?></div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= e(__('hr.absent_today')) ?></div>
            <?php if ($absent === []): ?>
                <div class="card-body small text-muted"><?= e(__('hr.nobody_absent')) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($absent as $l): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span><a href="<?= e(url('/hr/employees/' . $l['employee_id'])) ?>" class="text-decoration-none fw-semibold"><?= e(full_name($l)) ?></a>
                                <span class="text-muted">· <?= e($l['position'] ?: '') ?></span></span>
                            <span class="text-nowrap"><?= e(__('hr.leave_types.' . $l['type'])) ?> · <?= e(__('hr.until', ['date' => format_date($l['end_date'])])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= e(__('hr.pending_requests')) ?></div>
            <?php if ($requests === []): ?>
                <div class="card-body small text-muted"><?= e(__('hr.no_requests')) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach (array_slice($requests, 0, 8) as $l): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span class="fw-semibold"><?= e(full_name($l)) ?></span>
                            <span class="text-nowrap"><?= e(__('hr.leave_types.' . $l['type'])) ?> · <?= e(format_date($l['start_date'])) ?>–<?= e(format_date($l['end_date'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-footer bg-transparent"><a href="<?= e(url('/hr/leaves?status=requested')) ?>" class="small"><?= e(__('hr.go_decide')) ?></a></div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($drafts !== []): ?>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header fw-semibold"><?= e(__('hr.draft_orders')) ?></div>
                <div class="list-group list-group-flush small">
                    <?php foreach ($drafts as $o): ?>
                        <a href="<?= e(url('/hr/orders/' . $o['id'])) ?>" class="list-group-item list-group-item-action d-flex justify-content-between gap-2">
                            <span><?= e(__('hr.order_types.' . $o['type'])) ?> · <?= e(full_name($o)) ?></span>
                            <span class="text-muted text-nowrap"><?= e(format_date($o['order_date'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><?= e(__('hr.reminders')) ?></div>
            <?php if ($reminders['categories'] === [] && $reminders['trainings'] === []): ?>
                <div class="card-body small text-muted"><?= e(__('hr.no_reminders')) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($reminders['categories'] as $r): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <a href="<?= e(url('/hr/employees/' . $r['id'])) ?>" class="text-decoration-none"><?= e(full_name($r)) ?></a>
                            <span class="<?= $r['category_valid_until'] < $today ? 'text-danger' : 'text-warning-emphasis' ?> text-nowrap"><?= e(__($r['category_valid_until'] < $today ? 'hr.category_expired' : 'hr.category_expires', ['date' => format_date($r['category_valid_until'])])) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($reminders['trainings'], 0, 10) as $r): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <a href="<?= e(url('/hr/employees/' . $r['id'])) ?>" class="text-decoration-none"><?= e(full_name($r)) ?></a>
                            <span class="text-muted text-nowrap"><?= e($r['last_training'] ? __('hr.training_last', ['date' => format_date($r['last_training'])]) : __('hr.training_none')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-footer bg-transparent"><a href="<?= e(url('/hr/qualification')) ?>" class="small"><?= e(__('hr.qualification')) ?></a></div>
            <?php endif; ?>
        </div>
    </div>
</div>
