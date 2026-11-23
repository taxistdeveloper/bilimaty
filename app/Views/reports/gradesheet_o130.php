<?php
/**
 * Summary grade sheet for printing: Form 5.5 "Итоги учебно-воспитательной работы" of the theory
 * journal (Order No. 130, MES RK, 06.04.2020) with grades in the five-point system (Типовые правила,
 * Order No. 125, 18.03.2008), subject totals under the table and the group totals after it.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $form result of App\Services\Order130::withTotals()
 */
use App\Services\Order130;

$group = $form['group'];
$college = App\Services\Settings::collegeName();
$pages = $form['subjects'] === [] ? [[]] : array_chunk($form['subjects'], 4);
$subCols = ['half1' => 's1', 'half2' => 's2', 'rating' => 'rating', 'credit' => 'credit', 'exam' => 'exam', 'final' => 'final'];
$summary = $form['summary'];
$pct = static fn (?float $v): string => $v === null ? '—' : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . '%';
$avg = static fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '');
$markHtml = static function (?array $g): string {
    if ($g === null) {
        return '';
    }

    return '<b>' . e($g['mark']) . '</b>' . ($g['letter'] !== null ? '<span class="lt">' . e($g['letter']) . '</span>' : '');
};
$header = static function (string $formNo, string $title) use ($college, $group, $form): string {
    return '<div class="form-no">' . e($formNo) . '</div>'
        . '<div class="form-title">' . e($title) . '</div>'
        . '<div class="fields" style="text-align: center">' . ($college !== '' ? e($college) . ' · ' : '')
        . e(__('o130.group')) . ' <span>' . e($group['code'] ?? '') . '</span> '
        . e(__('o130.course')) . ' <span>' . e($group['course'] ?? '') . '</span> '
        . e(__('o130.specialty')) . ' <span>' . e(Order130::specialty($group)) . '</span> '
        . '<span>' . e($form['year']) . '</span> ' . e(__('o130.year')) . '</div>';
};
$signs = '<div class="sign">' . e(__('o130.curator_sign')) . ' ____________________ &nbsp;&nbsp;&nbsp; ' . e(__('o130.deputy')) . ' ____________________</div>';
?>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    .sheet { width: 297mm; padding: 10mm; }
    .o130 .lt { font-size: 6.5pt; margin-left: .4mm; }
    .o130 tr.tot td { font-weight: bold; }
    .o130 .totals { display: flex; gap: 10mm; align-items: flex-start; }
    .o130 .totals table { width: auto; }
    .o130 .totals td { text-align: left; padding: 1mm 2mm; font-size: 9.5pt; }
    .o130 .totals td.c { text-align: center; }
    .o130 .names { font-size: 8pt; }
</style>
<div class="o130">
    <?php foreach ($pages as $p => $subjects): ?>
        <section class="page">
            <?= $header(__('o130.form', ['n' => '5.5']), __('o130.f55_title')) ?>
            <table>
                <thead>
                <tr>
                    <th rowspan="2" style="width: 7mm"><?= e(__('o130.n')) ?></th>
                    <th rowspan="2" style="width: 48mm"><?= e(__('o130.student_short')) ?></th>
                    <?php foreach ($subjects as $s): ?>
                        <th colspan="6"><?= e(__('o130.subjects')) ?>:<br><strong><?= e($s['name']) ?></strong> / <?= e($s['module'] !== '' ? $s['module'] : $s['code']) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($subjects as $s): foreach ($subCols as $label => $key): ?>
                        <th style="height: 46mm"><span class="v"><?= e(__('o130.' . $label)) ?></span></th>
                    <?php endforeach; endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($form['students'] as $i => $st): ?>
                    <tr>
                        <td><?= e($i + 1) ?></td>
                        <td class="l nw"><?= e($st['name']) ?></td>
                        <?php foreach ($subjects as $s): $c = $st['cells'][$s['id']] ?? []; $m = $st['marks'][$s['id']] ?? []; foreach ($subCols as $key): ?>
                            <?php if ($key === 'rating'): ?>
                                <td><?= e(App\Services\JournalService::normalize($c['rating'] ?? null) ?? '') ?></td>
                            <?php elseif ($key === 'credit' && ($m['credit'] ?? null) === null): ?>
                                <td><?= e(is_string($c['credit'] ?? null) ? $c['credit'] : '') ?></td>
                            <?php else: ?>
                                <td class="nw"><?= $markHtml($m[$key] ?? null) ?></td>
                            <?php endif; ?>
                        <?php endforeach; endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td></td><td class="l"><?= e(__('o130.hours_plan')) ?></td>
                    <?php foreach ($subjects as $s): ?><td colspan="6"><?= e($s['hours_plan'] ?? '') ?></td><?php endforeach; ?>
                </tr>
                <tr>
                    <td></td><td class="l"><?= e(__('o130.hours_given')) ?></td>
                    <?php foreach ($subjects as $s): ?><td colspan="6"><?= e($s['hours_given']) ?></td><?php endforeach; ?>
                </tr>
                <?php foreach (['absolute' => $pct, 'quality' => $pct, 'average' => $avg] as $key => $fmt): ?>
                    <tr class="tot">
                        <td></td><td class="l"><?= e(__('reports.gs_' . $key)) ?></td>
                        <?php foreach ($subjects as $s): ?><td colspan="6"><?= e($fmt($form['subject_stats'][$s['id']][$key] ?? null)) ?></td><?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td></td><td class="l"><?= e(__('reports.gs_counts')) ?></td>
                    <?php foreach ($subjects as $s): $k = $form['subject_stats'][$s['id']]['counts'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0]; ?><td colspan="6"><?= e($k[5] . ' / ' . $k[4] . ' / ' . $k[3] . ' / ' . $k[2]) ?></td><?php endforeach; ?>
                </tr>
                </tbody>
            </table>
            <?php if ($p === count($pages) - 1): ?>
                <?= $signs ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="page">
        <div class="form-title"><?= e(__('reports.gs_totals_title')) ?></div>
        <div class="fields" style="text-align: center"><?= e(__('o130.group')) ?> <span><?= e($group['code'] ?? '') ?></span> <span><?= e($form['year']) ?></span> <?= e(__('o130.year')) ?></div>
        <div class="totals">
            <table>
                <tr><td><?= e(__('reports.gs_students')) ?></td><td class="c"><b><?= e($summary['students']) ?></b></td><td class="names"><?= e(__('reports.gs_graded', ['n' => $summary['graded']])) ?></td></tr>
                <?php foreach ($summary['categories'] as $key => $names): ?>
                    <tr><td><?= e(__('reports.gs_cat.' . $key)) ?></td><td class="c"><b><?= e(count($names)) ?></b></td><td class="names"><?= e($key === 'ungraded' ? '' : implode(', ', $names)) ?></td></tr>
                <?php endforeach; ?>
                <tr><td><?= e(__('reports.gs_absolute')) ?></td><td class="c"><b><?= e($pct($summary['absolute'])) ?></b></td><td></td></tr>
                <tr><td><?= e(__('reports.gs_quality')) ?></td><td class="c"><b><?= e($pct($summary['quality'])) ?></b></td><td></td></tr>
                <tr><td><?= e(__('reports.gs_average')) ?></td><td class="c"><b><?= e($avg($summary['average'])) ?></b></td><td></td></tr>
            </table>
            <?php if ($form['scale'] !== []): ?>
                <table>
                    <tr><th><?= e(__('transcripts.grade_letter')) ?></th><th><?= e(__('transcripts.scale_range')) ?></th><th><?= e(__('transcripts.grade_points')) ?></th><th><?= e(__('transcripts.grade_traditional')) ?></th></tr>
                    <?php foreach ($form['scale'] as $idx => [$from, $letter, $points]): $to = $idx === 0 ? 100 : $form['scale'][$idx - 1][0] - 1;
                        $mark = $points >= 3.67 ? 5 : ($points >= 2.67 ? 4 : ($points >= 1.0 ? 3 : 2)); ?>
                        <tr><td class="c"><?= e($letter) ?></td><td class="c"><?= e($from . '–' . $to) ?></td><td class="c"><?= e(number_format((float) $points, 2, ',', '')) ?></td><td class="c"><?= e($mark) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        <?= $signs ?>
        <p class="basis">
            <?= e(__('reports.gs_formula')) ?><br>
            <?= e(__('reports.gs_totals_note')) ?><br>
            <?= e(__('o130.rating_note')) ?><br>
            <?= e(__('o130.basis')) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?>
        </p>
    </section>
</div>
