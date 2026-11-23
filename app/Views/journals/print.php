<?php
/**
 * "Журнал учета теоретического обучения" by Order No. 130 (MES RK, 06.04.2020), for one discipline
 * of a group: title page with contents, form 5.3 (left side — attendance and marks, right side —
 * lessons and topics) and the page of inspectors' remarks.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $journal
 * @var array{lessons: list<array<string, mixed>>, students: list<array<string, mixed>>, scheme: ?array, components: list<array<string, mixed>>} $grid
 * @var array<string, mixed> $group
 * @var array{module_code: string, hours_total: int|null, control_form: string} $item
 */
use App\Services\JournalService;
use App\Services\Order130;

$n = static fn ($v): string => JournalService::normalize($v) ?? '';
$college = App\Services\Settings::collegeName();
$subject = localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]);
$teacher = full_name(['last_name' => $journal['teacher_last_name'], 'first_name' => $journal['teacher_first_name'], 'middle_name' => $journal['teacher_middle_name']]);
[$y1, $y2] = array_pad(explode('-', (string) $journal['year_title']), 2, '');
$module = $item['module_code'] !== '' ? $item['module_code'] : (string) $journal['subject_code'];
$students = array_values(array_filter($grid['students'], static fn (array $s): bool => in_array(true, array_column($s['cells'], 'member'), true) || $grid['lessons'] === []));
$chunks = $grid['lessons'] === [] ? [[]] : array_chunk($grid['lessons'], 22);
$totalHours = array_sum(array_map(static fn (array $l): int => (int) $l['hours'], $grid['lessons']));
?>
<style>@page { size: A4 landscape; margin: 10mm; } .sheet { width: 297mm; padding: 10mm; }</style>
<div class="o130">
    <section class="page cover">
        <div class="ministry"><?= e(__('o130.ministry')) ?></div>
        <?php if ($college !== ''): ?><div class="ministry" style="margin-top: 3mm"><?= e($college) ?></div><?php endif; ?>
        <h1><?= e(__('o130.journal_title')) ?></h1>
        <div class="line"><?= e(__('o130.group')) ?> <span><?= e($journal['group_code']) ?></span></div>
        <div class="line"><?= e(__('o130.course')) ?> <span><?= e($journal['course']) ?></span></div>
        <div class="line"><?= e(__('o130.specialty')) ?> <span><?= e(Order130::specialty($group)) ?></span></div>
        <div class="line"><?= e(__('o130.qualification')) ?> <span><?= e(Order130::qualification($group)) ?></span></div>
        <div class="line" style="text-align: center; margin-top: 10mm"><?= e($y1) ?>/<?= e($y2) ?> <?= e(__('o130.year')) ?></div>
    </section>

    <section class="page">
        <div class="form-title"><?= e(__('o130.contents')) ?></div>
        <table>
            <thead><tr><th style="width: 8%"><?= e(__('o130.n')) ?></th><th style="width: 14%"><?= e(__('o130.module_index')) ?></th><th><?= e(__('o130.subject_module')) ?></th><th style="width: 30%"><?= e(__('o130.teacher_fio')) ?></th><th style="width: 10%"><?= e(__('o130.pages')) ?></th></tr></thead>
            <tbody><tr><td>1</td><td><?= e($module) ?></td><td class="l"><?= e($subject) ?></td><td class="l"><?= e($teacher) ?></td><td>3–<?= e(2 + count($chunks) * 2) ?></td></tr></tbody>
        </table>
        <p class="basis"><?= e(__('o130.absent_note')) ?></p>
    </section>

    <?php foreach ($chunks as $page => $lessons): $last = $page === count($chunks) - 1; ?>
        <section class="page">
            <div class="form-no"><?= e(__('o130.form', ['n' => '5.3'])) ?></div>
            <div class="form-title"><?= e(__('o130.f53_title')) ?></div>
            <div class="side">(<?= e(__('o130.left')) ?>)</div>
            <div class="fields">
                <?= e(__('o130.module_index')) ?> <span><?= e($module) ?></span>
                <?= e(__('o130.subject_module')) ?> <span><?= e($subject) ?></span>
            </div>
            <table>
                <thead>
                <tr>
                    <th rowspan="3" style="width: 7mm"><?= e(__('o130.n')) ?></th>
                    <th rowspan="3" style="width: 62mm"><?= e(__('o130.student_fio')) ?></th>
                    <th colspan="<?= e(max(1, count($lessons))) ?>"><?= e(__('o130.month_day')) ?></th>
                    <?php if ($last): ?><th rowspan="3" style="width: 16mm"><span class="v"><?= e(__('o130.semester_mark')) ?></span></th><?php endif; ?>
                </tr>
                <tr>
                    <?php $spans = array_count_values(array_map(static fn (array $l): string => substr((string) $l['lesson_date'], 0, 7), $lessons)); ?>
                    <?php foreach ($spans as $month => $span): ?><th colspan="<?= e($span) ?>"><?= e(substr($month, 5, 2)) ?></th><?php endforeach; ?>
                    <?php if ($lessons === []): ?><th rowspan="2"></th><?php endif; ?>
                </tr>
                <tr>
                    <?php foreach ($lessons as $l): ?><th style="width: 8mm"><?= e(date('j', strtotime((string) $l['lesson_date']))) ?></th><?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= e($i + 1) ?></td>
                        <td class="l nw"><?= e(full_name($s)) ?></td>
                        <?php foreach ($lessons as $l): $c = $s['cells'][(int) $l['id']]; ?>
                            <td><?= e(!$c['member'] ? '' : (in_array($c['attendance'], ['absent', 'excused'], true) ? 'н' : $n($c['grade']))) ?></td>
                        <?php endforeach; ?>
                        <?php if ($lessons === []): ?><td></td><?php endif; ?>
                        <?php if ($last): ?><td><strong><?= e($n($journal['status'] === 'approved' ? ($s['result']['fixed_final'] ?? $s['result']['final']) : $s['result']['final'])) ?></strong></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="page">
            <div class="form-no"><?= e(__('o130.form', ['n' => '5.3'])) ?></div>
            <div class="side">(<?= e(__('o130.right')) ?>)</div>
            <div class="fields"><?= e(__('o130.teacher_fio')) ?> <span><?= e($teacher) ?></span></div>
            <table>
                <thead><tr><th style="width: 8mm"><?= e(__('o130.n')) ?></th><th style="width: 26mm"><?= e(__('o130.lesson_date')) ?></th><th style="width: 22mm"><?= e(__('o130.hours')) ?></th><th><?= e(__('o130.topics')) ?></th><th style="width: 32mm"><?= e(__('o130.teacher_sign')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($lessons as $k => $l): ?>
                    <tr><td><?= e($page * 22 + $k + 1) ?></td><td><?= e(format_date($l['lesson_date'])) ?></td><td><?= e($l['hours']) ?></td><td class="l"><?= e($l['topic'] ?? '') ?></td><td></td></tr>
                <?php endforeach; ?>
                <?php if ($last): ?>
                    <tr><td></td><td class="l" colspan="1"><strong><?= e(__('o130.total_module')) ?></strong></td><td><strong><?= e($totalHours) ?></strong></td><td class="l"><?= $item['hours_total'] !== null ? e(__('o130.hours_plan') . ': ' . $item['hours_total']) : '' ?></td><td></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>

    <section class="page">
        <div class="form-title"><?= e(__('o130.remarks_title')) ?></div>
        <table>
            <thead><tr><th style="width: 25mm"><?= e(__('o130.date')) ?></th><th><?= e(__('o130.remarks_content')) ?></th><th style="width: 80mm"><?= e(__('o130.checker')) ?></th></tr></thead>
            <tbody><?php for ($r = 0; $r < 12; $r++): ?><tr><td style="height: 9mm"></td><td></td><td></td></tr><?php endfor; ?></tbody>
        </table>
        <p class="basis"><?= e(__('o130.basis')) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></p>
    </section>
</div>
