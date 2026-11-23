<?php
/**
 * Form 5.5 "Итоги учебно-воспитательной работы" of the theory journal (Order No. 130, MES RK,
 * 06.04.2020) for a group and academic year. $part: "results" — grades by discipline (performance
 * report), "missed" — hours missed (attendance report).
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $form result of App\Services\Order130::yearResults()
 * @var string $part results|missed
 */
use App\Services\JournalService;
use App\Services\Order130;

$n = static fn ($v): string => is_string($v) && !is_numeric($v) ? $v : (JournalService::normalize($v) ?? '');
$group = $form['group'];
$college = App\Services\Settings::collegeName();
$pages = $form['subjects'] === [] ? [[]] : array_chunk($form['subjects'], 4);
$subCols = ['half1', 'half2', 'rating', 'credit', 'exam', 'final'];
$keys = ['half1' => 's1', 'half2' => 's2', 'rating' => 'rating', 'credit' => 'credit', 'exam' => 'exam', 'final' => 'final'];
?>
<style>@page { size: A4 landscape; margin: 10mm; } .sheet { width: 297mm; padding: 10mm; }</style>
<div class="o130">
    <?php $header = static function () use ($college, $group, $form): string {
        return '<div class="form-no">' . e(__('o130.form', ['n' => '5.5'])) . '</div>'
            . '<div class="form-title">' . e(__('o130.f55_title')) . '</div>'
            . '<div class="fields" style="text-align: center">' . ($college !== '' ? e($college) . ' · ' : '')
            . e(__('o130.group')) . ' <span>' . e($group['code'] ?? '') . '</span> '
            . e(__('o130.course')) . ' <span>' . e($group['course'] ?? '') . '</span> '
            . e(__('o130.specialty')) . ' <span>' . e(Order130::specialty($group)) . '</span> '
            . '<span>' . e($form['year']) . '</span> ' . e(__('o130.year')) . '</div>';
    }; ?>

    <?php if ($part === 'results'): ?>
        <?php foreach ($pages as $p => $subjects): ?>
            <section class="page">
                <?= $header() ?>
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
                        <?php foreach ($subjects as $s): foreach ($subCols as $col): ?>
                            <th style="height: 46mm"><span class="v"><?= e(__('o130.' . $col)) ?></span></th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($form['students'] as $i => $st): ?>
                        <tr>
                            <td><?= e($i + 1) ?></td>
                            <td class="l nw"><?= e($st['name']) ?></td>
                            <?php foreach ($subjects as $s): $c = $st['cells'][$s['id']] ?? []; foreach ($subCols as $col): ?>
                                <td><?= e($n($c[$keys[$col]] ?? null)) ?></td>
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
                    </tbody>
                </table>
                <?php if ($p === count($pages) - 1): ?>
                    <div class="sign"><?= e(__('o130.curator_sign')) ?> ____________________ &nbsp;&nbsp;&nbsp; <?= e(__('o130.deputy')) ?> ____________________</div>
                    <p class="basis"><?= e(__('o130.rating_note')) ?><br><?= e(__('o130.basis')) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <section class="page">
            <?= $header() ?>
            <table>
                <thead>
                <tr>
                    <th rowspan="3" style="width: 7mm"><?= e(__('o130.n')) ?></th>
                    <th rowspan="3" style="width: 60mm"><?= e(__('o130.student_short')) ?></th>
                    <th colspan="6"><?= e(__('o130.missed')) ?></th>
                </tr>
                <tr><th colspan="2"><?= e(__('o130.missed_h1')) ?></th><th colspan="2"><?= e(__('o130.missed_h2')) ?></th><th colspan="2"><?= e(__('o130.missed_year')) ?></th></tr>
                <tr><?php for ($k = 0; $k < 3; $k++): ?><th><?= e(__('o130.total')) ?></th><th><?= e(__('o130.unexcused')) ?></th><?php endfor; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($form['students'] as $i => $st): $m = $st['missed']; ?>
                    <tr>
                        <td><?= e($i + 1) ?></td>
                        <td class="l nw"><?= e($st['name']) ?></td>
                        <td><?= e($m[1]['total']) ?></td><td><?= e($m[1]['unexcused']) ?></td>
                        <td><?= e($m[2]['total']) ?></td><td><?= e($m[2]['unexcused']) ?></td>
                        <td><strong><?= e($m[1]['total'] + $m[2]['total']) ?></strong></td><td><strong><?= e($m[1]['unexcused'] + $m[2]['unexcused']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="sign"><?= e(__('o130.curator_sign')) ?> ____________________ &nbsp;&nbsp;&nbsp; <?= e(__('o130.deputy')) ?> ____________________</div>
            <p class="basis"><?= e(__('o130.basis')) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></p>
        </section>
    <?php endif; ?>
</div>
