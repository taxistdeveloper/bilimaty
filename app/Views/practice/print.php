<?php
/**
 * "График программы прохождения практики" on A4, the layout of the college template:
 * approval block of the deputy director, practice name, group, module, kind, dates, the table
 * (№, module/section/topic, date/time, place — the platform for distance learning), supervisor.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $assignment
 * @var list<array<string, mixed>> $entries
 */
$a = $assignment;
$teacher = full_name(['last_name' => $a['teacher_last_name'], 'first_name' => $a['teacher_first_name'], 'middle_name' => $a['teacher_middle_name']], true);
$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
$long = static function (?string $date) use ($months): string {
    if ($date === null) {
        return '« ___ » __________ 20___';
    }
    $t = strtotime($date);

    return locale() === 'ru' ? '« ' . date('j', $t) . ' » ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : format_date($date);
};
$approved = $a['status'] === 'approved';
?>
<div class="o130">
    <div style="margin-left: auto; width: 82mm; font-size: 10.5pt; line-height: 1.5">
        <div><strong><?= e(__('practice.approved_by')) ?></strong></div>
        <div><?= e(__('practice.deputy_upr')) ?></div>
        <div>______________________</div>
        <div style="font-size: 8.5pt"><?= e(__('practice.fio_sign')) ?></div>
        <div><?= e($approved && $a['decided_at'] ? $long(substr((string) $a['decided_at'], 0, 10)) : '«____»__________20____') ?></div>
    </div>

    <div class="form-title" style="margin-top: 8mm"><?= e(__('practice.print_title')) ?></div>
    <div style="text-align: center; line-height: 1.7; font-size: 11pt">
        <div><span style="border-bottom: 1px solid #000; padding: 0 4mm"><?= e($a['title']) ?></span></div>
        <div style="font-size: 8.5pt"><?= e(__('practice.name_hint')) ?></div>
        <div><?= e(__('practice.group_word')) ?> <span style="border-bottom: 1px solid #000; padding: 0 4mm"><?= e($a['group_code']) ?></span></div>
        <div style="display: flex; gap: 6mm; justify-content: center; margin-top: 2mm">
            <div><span style="border-bottom: 1px solid #000; padding: 0 3mm"><?= e($a['module_name'] ?: '—') ?></span><div style="font-size: 8.5pt"><?= e(__('practice.module_hint')) ?></div></div>
            <div><span style="border-bottom: 1px solid #000; padding: 0 3mm"><?= e(__('practice.types.' . $a['practice_type'])) ?></span><div style="font-size: 8.5pt"><?= e(__('practice.type_hint')) ?></div></div>
        </div>
        <div style="margin-top: 3mm"><?= e(__('practice.period')) ?> <?= e(__('practice.from')) ?> <?= e($long($a['first_date'])) ?> <?= e(__('practice.to')) ?> <?= e($long($a['last_date'])) ?> <?= e(__('practice.year_suffix')) ?></div>
    </div>

    <table style="margin-top: 5mm">
        <thead><tr>
            <th style="width: 10mm"><?= e(__('o130.n')) ?></th>
            <th><?= e(__('practice.col_topic')) ?></th>
            <th style="width: 38mm"><?= e(__('practice.col_datetime')) ?></th>
            <th style="width: 52mm"><?= e(__('practice.col_place_print')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($entries as $i => $e): ?>
            <tr>
                <td><?= e($i + 1) ?></td>
                <td class="l"><?= e($e['topic']) ?></td>
                <td><?= e($e['entry_date'] ? format_date($e['entry_date']) : '') ?><br><?= e(substr((string) $e['time_start'], 0, 5)) ?>–<?= e(substr((string) $e['time_end'], 0, 5)) ?></td>
                <td class="l"><?= $e['is_distance'] ? e(__('practice.distance_mark')) . ': ' : '' ?><?= e($e['place']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($entries === []): ?><?php for ($k = 1; $k <= 5; $k++): ?><tr><td><?= e($k) ?></td><td style="height: 9mm"></td><td></td><td></td></tr><?php endfor; ?><?php endif; ?>
        </tbody>
    </table>

    <div class="sign" style="margin-top: 10mm; display: flex; gap: 12mm">
        <div><?= e(__('practice.supervisor')) ?>: <span style="border-bottom: 1px solid #000; padding: 0 3mm"><?= e($teacher) ?></span></div>
        <div><?= e(__('practice.signature')) ?>: __________________</div>
    </div>
    <p class="basis"><?= e(__('practice.statuses.' . $a['status'])) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></p>
</div>
