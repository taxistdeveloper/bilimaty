<?php
/** @var string $status */
$class = ['draft' => 'app-badge-muted', 'published' => 'app-badge-success', 'closed' => 'app-badge-primary'][$status] ?? 'app-badge-muted';
?>
<span class="badge <?= e($class) ?>"><?= e(__('surveys.statuses.' . $status)) ?></span>
