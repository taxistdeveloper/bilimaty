<?php
/** @var string $status */
$class = ['draft' => 'app-badge-warning', 'signed' => 'app-badge-success', 'cancelled' => 'text-bg-light'][$status] ?? 'text-bg-light';
?>
<span class="badge <?= e($class) ?>"><?= e(__('hr.order_statuses.' . $status)) ?></span>
