<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $order */
/** @var array<string, mixed> $details */
/** @var array<string, mixed> $employee */
/** @var array<int, string> $departments */
/** @var bool $canManage */
/** @var bool $print */
$name = full_name($employee);
$dept = isset($details['department_id']) && $details['department_id'] !== null && $details['department_id'] !== '' ? ($departments[(int) $details['department_id']] ?? '') : '';
$replace = [
    'name' => $name,
    'number' => $employee['personnel_number'],
    'date' => format_date($order['effective_date']),
    'position' => (string) ($details['position'] ?? ''),
    'department' => $dept !== '' ? $dept : '—',
    'rate' => (string) ($details['rate'] ?? ''),
    'reason' => (string) ($details['reason'] ?? ''),
];
$text = $order['type'] === 'other' ? (string) ($details['text'] ?? '') : __('hr.order_body.' . $order['type'], $replace);
$heading = __('hr.order_types.' . $order['type']);
?>
<?php if ($print): $college = App\Services\Settings::collegeName(); ?>
    <header class="doc-header">
        <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
        <h1><?= e(__('hr.order_doc')) ?> № <?= e($order['number'] ?? '____') ?></h1>
        <div class="doc-meta"><?= e(format_date($order['order_date'])) ?> · <?= e($heading) ?></div>
    </header>
    <p><?= e($text) ?></p>
    <?php if ($order['basis']): ?><p><?= e(__('fields.basis')) ?>: <?= e($order['basis']) ?></p><?php endif; ?>
    <?php if ($order['status'] !== 'signed'): ?><p><strong><?= e(__('hr.order_not_signed_print')) ?></strong></p><?php endif; ?>
    <div class="doc-footer">
        <?php $signer = (string) (App\Services\Settings::get('document_signer_position_' . locale()) ?: App\Services\Settings::get('document_signer_position_ru')); ?>
        <p><?= e($signer !== '' ? $signer : __('transcripts.signature')) ?> ____________ <?= e((string) App\Services\Settings::get('document_signer_name')) ?></p>
        <p><?= e(__('hr.acquainted')) ?>: ____________ <?= e(full_name($employee, true)) ?> «___» __________ 20__</p>
    </div>
<?php else: ?>
    <?= $this->partial('partials/page-header', [
        'heading' => $heading . ($order['number'] ? ' № ' . $order['number'] : ''),
        'subtitle' => $name,
        'back' => '/hr/employees/' . $employee['id'],
        'actions' => '<a href="' . e(url('/hr/orders/' . $order['id'] . '?format=print')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>',
    ]) ?>
    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e(__('hr.order_doc')) ?></span>
                    <?= $this->partial('hr/_order_status', ['status' => $order['status']]) ?>
                </div>
                <div class="card-body">
                    <p class="mb-2"><?= e($text) ?></p>
                    <?php if ($order['basis']): ?><p class="small text-muted mb-0"><?= e(__('fields.basis')) ?>: <?= e($order['basis']) ?></p><?php endif; ?>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('fields.order_date')) ?></span><span><?= e(format_date($order['order_date'])) ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('fields.effective_date')) ?></span><span><?= e(format_date($order['effective_date'])) ?></span></li>
                    <?php if ($order['signed_at']): ?><li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('hr.signed_at')) ?></span><span><?= e(format_date($order['signed_at'], 'd.m.Y H:i')) ?></span></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <?php if ($canManage && $order['status'] === 'draft'): ?>
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <p class="small text-muted"><?= e(__('hr.sign_hint')) ?></p>
                        <form method="post" action="<?= e(url('/hr/orders/' . $order['id'] . '/sign')) ?>" data-confirm="<?= e(__('hr.sign_confirm')) ?>" class="mb-2"><?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-pen me-1" aria-hidden="true"></i><?= e(__('hr.sign')) ?></button></form>
                        <form method="post" action="<?= e(url('/hr/orders/' . $order['id'] . '/cancel')) ?>" data-confirm="<?= e(__('hr.order_cancel_confirm')) ?>" data-confirm-danger><?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger w-100"><?= e(__('hr.cancel_draft')) ?></button></form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
