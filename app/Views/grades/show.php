<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $journal */
/** @var list<array<string, mixed>> $lessons */
/** @var list<array<string, mixed>> $components */
/** @var array<string, mixed>|null $scheme */
/** @var array<string, mixed> $student */
/** @var string|null $back parent's view: link back to the child */
/** @var string|null $childName parent's view: whose grades */
use App\Services\JournalService;

$n = static fn ($v): string => JournalService::normalize($v) ?? '—';
$r = $student['result'];
$final = $journal['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final']) : $r['final'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]),
    'subtitle' => (isset($childName) ? $childName . ' · ' : '') . $journal['group_code'] . ' · ' . full_name(['last_name' => $journal['teacher_last_name'], 'first_name' => $journal['teacher_first_name'], 'middle_name' => $journal['teacher_middle_name']], true),
    'back' => $back ?? '/grades',
]) ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card app-stat h-100"><div class="card-body"><div class="app-stat__label"><?= e(__('journal.current')) ?></div><div class="app-stat__value"><?= e($n($r['current'])) ?></div></div></div></div>
    <?php foreach ($components as $c): ?>
        <?php if ($c['source'] === 'manual'): ?>
            <div class="col-6 col-md-3"><div class="card app-stat h-100"><div class="card-body"><div class="app-stat__label"><?= e(localized($c)) ?></div><div class="app-stat__value"><?= e($n($r['components'][(int) $c['assessment_type_id']] ?? null)) ?></div></div></div></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="col-6 col-md-3"><div class="card app-stat h-100 border-primary-subtle"><div class="card-body"><div class="app-stat__label"><?= e(__('journal.final')) ?><?= $journal['status'] === 'approved' ? ' · ' . e(__('journal.statuses.approved')) : '' ?></div><div class="app-stat__value<?= $r['passed'] === false ? ' text-danger' : '' ?>"><?= e($n($final)) ?></div></div></div></div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><?= e(__('journal.lessons')) ?> <span class="text-muted small fw-normal">· <?= e(__('journal.absences')) ?>: <?= e($r['absent']) ?> / <?= e(__('journal.attendance_values.excused')) ?>: <?= e($r['excused']) ?></span></div>
    <?php if ($lessons === []): ?>
        <div class="card-body small text-muted"><?= e(__('journal.no_lessons')) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light"><tr><th scope="col"><?= e(__('fields.date')) ?></th><th scope="col"><?= e(__('journal.topic')) ?></th><th scope="col" class="d-none d-md-table-cell"><?= e(__('journal.homework')) ?></th><th scope="col" class="text-center"><?= e(__('journal.mark')) ?></th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($lessons) as $l): ?>
                    <?php $c = $student['cells'][(int) $l['id']]; ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(format_date($l['lesson_date'])) ?></td>
                        <td class="small"><?= e($l['topic'] ?? '—') ?></td>
                        <td class="small d-none d-md-table-cell"><?= e($l['homework'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if ($c['attendance'] !== null): ?>
                                <span class="badge <?= $c['attendance'] === 'absent' ? 'app-badge-danger' : 'app-badge-warning' ?>"><?= e(__('journal.attendance_values.' . $c['attendance'])) ?></span>
                            <?php else: ?>
                                <strong><?= e(JournalService::normalize($c['grade']) ?? '') ?></strong>
                            <?php endif; ?>
                            <?php if ($c['comment'] !== null): ?><div class="small text-muted"><?= e($c['comment']) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
