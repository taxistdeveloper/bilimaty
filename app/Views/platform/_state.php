<?php
/** @var string $state */
$class = ['active' => 'app-badge-success', 'expiring' => 'app-badge-warning', 'grace' => 'app-badge-warning', 'readonly' => 'app-badge-danger', 'suspended' => 'text-bg-dark'][$state] ?? 'text-bg-light';
?>
<span class="badge <?= e($class) ?>"><?= e(__('platform.states.' . $state)) ?></span>
