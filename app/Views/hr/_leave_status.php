<?php
/** @var string $status */
$class = ['requested' => 'app-badge-warning', 'approved' => 'app-badge-success', 'rejected' => 'app-badge-danger', 'cancelled' => 'text-bg-light'][$status] ?? 'text-bg-light';
?>
<span class="badge <?= e($class) ?>"><?= e(__('hr.leave_statuses.' . $status)) ?></span>
