<?php
/**
 * One editable row of the practice schedule (also rendered into a <template> with $i = "__i__").
 *
 * @var int|string $i
 * @var array{topic: string, date: string, time_start: string, time_end: string, place: string, is_distance: bool} $r
 */
$n = 'entries[' . $i . ']';
?>
<tr data-row>
    <td class="text-muted small" data-row-number><?= is_int($i) ? e($i + 1) : '' ?></td>
    <td><textarea name="<?= e($n) ?>[topic]" rows="2" class="form-control form-control-sm" maxlength="500" aria-label="<?= e(__('practice.col_topic')) ?>"><?= e($r['topic']) ?></textarea></td>
    <td><input type="date" name="<?= e($n) ?>[date]" value="<?= e($r['date']) ?>" class="form-control form-control-sm" data-date aria-label="<?= e(__('practice.col_date')) ?>"></td>
    <td><input type="time" name="<?= e($n) ?>[time_start]" value="<?= e($r['time_start']) ?>" class="form-control form-control-sm" data-start step="300" aria-label="<?= e(__('practice.col_start')) ?>"></td>
    <td><input type="time" name="<?= e($n) ?>[time_end]" value="<?= e($r['time_end']) ?>" class="form-control form-control-sm" data-end step="300" aria-label="<?= e(__('practice.col_end')) ?>"></td>
    <td class="small"><strong data-hours>0</strong><div class="text-danger" data-hours-note></div></td>
    <td><input type="text" name="<?= e($n) ?>[place]" value="<?= e($r['place']) ?>" class="form-control form-control-sm" maxlength="255" placeholder="<?= e(__('practice.place_hint')) ?>" aria-label="<?= e(__('practice.col_place')) ?>"></td>
    <td class="text-center"><input type="checkbox" name="<?= e($n) ?>[is_distance]" value="1" class="form-check-input"<?= $r['is_distance'] ? ' checked' : '' ?> aria-label="<?= e(__('practice.col_distance')) ?>"></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-row title="<?= e(__('practice.remove_row')) ?>" aria-label="<?= e(__('practice.remove_row')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
</tr>
