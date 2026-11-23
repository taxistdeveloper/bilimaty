<?php
/** @var string $status */
$class = ['draft' => 'app-badge-muted', 'submitted' => 'app-badge-primary', 'in_review' => 'app-badge-warning', 'needs_info' => 'app-badge-danger',
    'approved' => 'app-badge-success', 'rejected' => 'app-badge-danger', 'completed' => 'app-badge-success'][$status] ?? 'app-badge-muted';
?>
<span class="badge <?= e($class) ?>"><?= e(__('documents.statuses.' . $status)) ?></span>
