<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $year */
/** @var list<array<string, mixed>> $events */
/** @var array<string, mixed>|null $placement */
/** @var array<string, mixed> $filters */
/** @var array<int, string> $groups */
$college = App\Services\Settings::collegeName();
$audience = match (true) {
    $placement !== null => __('groups.one') . ' ' . $placement['code'],
    $filters['group'] !== null => __('groups.one') . ' ' . ($groups[$filters['group']] ?? ''),
    $filters['course'] !== null => __('groups.course_n', ['n' => $filters['course']]),
    default => __('calendar.all_college'),
};
$byType = [];
foreach ($events as $event) {
    $byType[$event['type']][] = $event;
}
$period = static fn (string $a, string $b): string => format_date($a) . ($a !== $b ? ' — ' . format_date($b) : '');
?>
<header class="doc-header">
    <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
    <h1><?= e(__('calendar.print_title')) ?> <?= e(str_replace('-', '–', (string) $year['title'])) ?></h1>
    <div class="doc-meta"><?= e($audience) ?><?= $filters['type'] !== '' ? ' · ' . e(__('calendar.types.' . $filters['type'])) : '' ?></div>
</header>

<h2><?= e(__('calendar.semesters')) ?></h2>
<table>
    <thead><tr><th><?= e(__('calendar.semester')) ?></th><th><?= e(__('calendar.period')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($year['semesters'] as $semester): ?>
        <tr><td><?= e(__('dashboard.semester', ['number' => $semester['number']])) ?></td><td><?= e($period($semester['start_date'], $semester['end_date'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($events === []): ?>
    <p class="muted"><?= e(__('calendar.empty')) ?></p>
<?php endif; ?>
<?php foreach ($byType as $type => $items): ?>
    <h2><?= e(__('calendar.types.' . $type)) ?></h2>
    <table>
        <thead><tr><th style="width: 28%"><?= e(__('calendar.period')) ?></th><th><?= e(__('calendar.event')) ?></th><th style="width: 22%"><?= e(__('calendar.audience')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $event): ?>
            <tr>
                <td><?= e($period($event['start_date'], $event['end_date'])) ?></td>
                <td><?= e(localized($event, 'title')) ?><?= $event['note'] ? '<div class="muted">' . e($event['note']) . '</div>' : '' ?></td>
                <td><?= e($event['group_code'] !== null ? __('groups.one') . ' ' . $event['group_code'] : ($event['course'] !== null ? __('groups.course_n', ['n' => $event['course']]) : __('calendar.all_college'))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<footer class="doc-footer"><?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></footer>
