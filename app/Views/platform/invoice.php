<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $invoice */
/** @var array<string, mixed> $college */
/** @var array<string, string> $owner */
/** @var bool $print */
$i = $invoice;
$money = static fn (float|string $v): string => number_format((float) $v, 2, ',', ' ');
$service = $owner['invoice_service'] !== '' ? $owner['invoice_service'] : __('platform.invoice_service_default', ['app' => config('app.name')]);
?>
<?php if (!$print): ?>
    <?= $this->partial('partials/page-header', [
        'heading' => __('platform.invoice') . ' № ' . $i['number'],
        'subtitle' => $college['name'] . ' · ' . __('platform.invoice_statuses.' . $i['status']),
        'back' => '/colleges/' . $college['id'],
        'actions' => '<a href="' . e(url('/invoices/' . $i['id'] . '?format=print')) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
            . ($i['status'] === 'issued' ? '<form method="post" action="' . e(url('/invoices/' . $i['id'] . '/cancel')) . '" data-confirm="' . e(__('platform.invoice_cancel_confirm')) . '" data-confirm-danger>' . csrf_field() . '<button type="submit" class="btn btn-outline-danger">' . e(__('platform.invoice_cancel')) . '</button></form>' : ''),
    ]) ?>
    <?php if ($owner['owner_name'] === ''): ?><div class="alert alert-warning small"><?= e(__('platform.requisites_missing')) ?> <a href="<?= e(url('/settings')) ?>" class="alert-link"><?= e(__('platform.settings')) ?></a></div><?php endif; ?>
    <div class="card"><div class="card-body">
<?php endif; ?>
<header class="doc-header">
    <h1><?= e(__('platform.invoice_doc', ['number' => $i['number'], 'date' => format_date($i['issued_on'])])) ?></h1>
</header>
<table class="table table-sm table-bordered small">
    <tbody>
    <tr><th scope="row" style="width: 30%"><?= e(__('platform.supplier')) ?></th><td><?= e($owner['owner_name'] ?: '—') ?><?= $owner['owner_bin'] !== '' ? ', ' . e(__('platform.bin')) . ' ' . e($owner['owner_bin']) : '' ?><?= $owner['owner_address'] !== '' ? '<br>' . e($owner['owner_address']) : '' ?><?= $owner['owner_phone'] !== '' ? '<br>' . e($owner['owner_phone']) : '' ?></td></tr>
    <tr><th scope="row"><?= e(__('platform.bank_details')) ?></th><td><?= e($owner['owner_bank'] ?: '—') ?><?= $owner['owner_iban'] !== '' ? '<br>IBAN ' . e($owner['owner_iban']) : '' ?><?= $owner['owner_bik'] !== '' ? ', БИК ' . e($owner['owner_bik']) : '' ?><?= $owner['owner_kbe'] !== '' ? ', КБе ' . e($owner['owner_kbe']) : '' ?></td></tr>
    <tr><th scope="row"><?= e(__('platform.customer')) ?></th><td><?= e($college['name']) ?><?= $college['bin'] ? ', ' . e(__('platform.bin')) . ' ' . e($college['bin']) : '' ?><?= $college['city'] ? ', ' . e($college['city']) : '' ?></td></tr>
    </tbody>
</table>
<table class="table table-sm table-bordered small">
    <thead><tr><th scope="col">№</th><th scope="col"><?= e(__('platform.service')) ?></th><th scope="col"><?= e(__('platform.months')) ?></th><th scope="col" class="text-end"><?= e(__('platform.amount')) ?>, ₸</th></tr></thead>
    <tbody><tr><td>1</td><td><?= e($service) ?> (<?= e($college['name']) ?>)</td><td><?= e($i['months']) ?></td><td class="text-end" style="white-space: nowrap"><?= e($money($i['amount'])) ?></td></tr></tbody>
    <tfoot><tr><th colspan="3" class="text-end"><?= e(__('platform.total')) ?></th><th class="text-end" style="white-space: nowrap"><?= e($money($i['amount'])) ?></th></tr></tfoot>
</table>
<?php if ($owner['payment_note'] !== ''): ?><p class="small"><?= e($owner['payment_note']) ?></p><?php endif; ?>
<?php if ($i['status'] === 'paid'): ?><p class="small"><strong><?= e(__('platform.paid_mark', ['date' => format_date($i['paid_on']), 'from' => format_date($i['period_from']), 'to' => format_date($i['period_to'])])) ?></strong></p><?php endif; ?>
<?php if ($i['status'] === 'cancelled'): ?><p><strong><?= e(__('platform.invoice_statuses.cancelled')) ?></strong></p><?php endif; ?>
<div class="doc-footer"><p><?= e(__('platform.signature')) ?> ____________ <?= e($owner['owner_name']) ?></p></div>
<?php if (!$print): ?></div></div><?php endif; ?>
