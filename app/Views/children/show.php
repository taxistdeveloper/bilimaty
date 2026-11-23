<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $child student */
/** @var list<array{journal: array<string, mixed>, result: array<string, mixed>, scheme: ?array}> $items */
/** @var int $absent */
/** @var int $excused */
/** @var list<array<string, mixed>> $exams */
/** @var list<array<string, mixed>> $children all children of the parent (switcher) */
$debts = count(array_filter($items, static fn (array $i): bool => ($i['result']['passed'] ?? null) === false));
$today = date('Y-m-d');
$upcoming = count(array_filter($exams, static fn (array $e): bool => $e['exam_date'] >= $today));
$switcher = '';
if (count($children) > 1) {
    foreach ($children as $c) {
        $switcher .= '<a href="' . e(url('/children/' . $c['id'])) . '" class="btn btn-sm ' . ((int) $c['id'] === (int) $child['id'] ? 'btn-primary' : 'btn-outline-primary') . '">' . e(full_name($c, true)) . '</a>';
    }
}
?>
<?= $this->partial('partials/page-header', [
    'heading' => full_name($child),
    'subtitle' => ($child['group_code'] ?? '—') . ($child['course'] !== null ? ' · ' . __('groups.course_n', ['n' => $child['course']]) : '') . ' · ' . localized(['name_ru' => $child['specialty_name_ru'], 'name_kk' => $child['specialty_name_kk']]),
    'back' => count($children) > 1 ? '/children' : null,
    'actions' => $switcher,
]) ?>

<?php if ($child['status'] !== 'active'): ?>
    <div class="alert alert-warning small"><?= e(__('children.status_note', ['status' => __('students.statuses.' . $child['status'])])) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['children.subjects', count($items), 'bi-journal-bookmark', ''],
        ['children.debts', $debts, 'bi-exclamation-triangle', $debts > 0 ? 'text-danger' : ''],
        ['children.absences', $absent + $excused, 'bi-calendar-x', ''],
        ['children.upcoming', $upcoming, 'bi-clipboard-check', ''],
    ] as [$label, $value, $icon, $class]): ?>
        <div class="col-6 col-lg-3"><div class="card app-stat h-100"><div class="card-body">
            <div class="app-stat__label"><i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e(__($label)) ?></div>
            <div class="app-stat__value <?= e($class) ?>"><?= e($value) ?></div>
            <?php if ($label === 'children.absences'): ?><div class="small text-muted"><?= e(__('children.absences_detail', ['absent' => $absent, 'excused' => $excused])) ?></div><?php endif; ?>
        </div></div></div>
    <?php endforeach; ?>
</div>

<h2 class="h5 fw-bold mb-2"><?= e(__('children.grades')) ?></h2>
<?php if ($items === []): ?>
    <div class="card mb-4"><div class="app-empty"><i class="bi bi-journal" aria-hidden="true"></i><p class="mb-0"><?= e(__('journal.my_empty')) ?></p></div></div>
<?php else: ?>
    <p class="small text-muted mb-2"><?= e(__('children.grades_hint')) ?></p>
    <?= $this->partial('grades/_results', ['items' => $items, 'linkBase' => '/children/' . $child['id'] . '/journal']) ?>
<?php endif; ?>

<h2 class="h5 fw-bold mb-2 mt-4"><?= e(__('children.exams')) ?></h2>
<?= $this->partial('exams/_student_list', ['exams' => $exams]) ?>
