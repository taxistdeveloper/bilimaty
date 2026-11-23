<?php
/** @var array<string, mixed> $assignment */
$classes = ['assigned' => 'text-bg-secondary', 'in_progress' => 'text-bg-info', 'submitted' => 'text-bg-warning', 'approved' => 'text-bg-success', 'returned' => 'text-bg-danger'];
?>
<span class="badge <?= e($classes[$assignment['status']] ?? 'text-bg-light') ?>"><?= e(__('practice.statuses.' . $assignment['status'])) ?></span>
<?php if (App\Services\PracticeService::isOverdue($assignment)): ?><span class="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle"><i class="bi bi-alarm me-1" aria-hidden="true"></i><?= e(__('practice.overdue')) ?></span><?php endif; ?>
