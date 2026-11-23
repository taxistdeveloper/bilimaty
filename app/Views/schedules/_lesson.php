<?php
/**
 * One lesson card in the grid / day list.
 *
 * @var array<string, mixed> $o occurrence
 * @var string $mode group|teacher|classroom — the dimension already shown in the page title
 * @var string $statusClass
 * @var string $payload JSON for the occurrence modal
 * @var int|null $entityId id of the teacher/group/classroom whose schedule is shown
 */
$subject = localized(['name_ru' => $o['subject_name_ru'], 'name_kk' => $o['subject_name_kk']]);
$details = [];
if ($mode !== 'group') {
    $details[] = $o['group_code'] . ($o['subgroup'] !== null ? '/' . $o['subgroup'] : '');
} elseif ($o['subgroup'] !== null) {
    $details[] = __('schedule.subgroup_n', ['n' => $o['subgroup']]);
}
if ($mode !== 'teacher') {
    $details[] = $o['effective_teacher_name'];
}
if ($mode !== 'classroom') {
    $details[] = __('schedule.room') . ' ' . $o['effective_classroom_label'];
}
$note = match ($o['status']) {
    'modified' => match (true) {
        $mode !== 'teacher' || (int) $o['effective_teacher_id'] === (int) $o['teacher_id'] => __('schedule.statuses.modified'),
        // The page shows the substitute's schedule: name whom they replace.
        (int) ($entityId ?? 0) === (int) $o['effective_teacher_id'] => __('schedule.substitutes_for', ['teacher' => full_name(['last_name' => $o['teacher_last_name'], 'first_name' => $o['teacher_first_name'], 'middle_name' => $o['teacher_middle_name']], true)]),
        default => __('schedule.replaced_by', ['teacher' => $o['effective_teacher_name']]),
    },
    'cancelled' => __('schedule.statuses.cancelled'),
    'moved_out' => __('schedule.moved_to', ['date' => format_date((string) $o['change']['new_date'], 'd.m'), 'n' => $o['change']['new_period_number']]),
    'moved_in' => __('schedule.moved_from', ['date' => format_date((string) $o['original_date'], 'd.m')]),
    'suspended' => localized($o['suspension'], 'title'),
    default => null,
};
?>
<button type="button" class="app-lesson app-lesson--<?= e($o['lesson_type']) ?><?= e($statusClass) ?>" data-occurrence="<?= e($payload) ?>">
    <span class="app-lesson__subject"><?= e($subject) ?></span>
    <span class="app-lesson__meta"><?= e(implode(' · ', $details)) ?></span>
    <span class="app-lesson__tags">
        <span class="app-lesson__type"><?= e(__('schedule.types_short.' . $o['lesson_type'])) ?></span>
        <?php if ($o['week_parity'] !== 'every'): ?><span class="app-lesson__type"><?= e(__('schedule.parities_short.' . $o['week_parity'])) ?></span><?php endif; ?>
        <?php if ($note !== null): ?><span class="app-lesson__note"><?= e($note) ?></span><?php endif; ?>
    </span>
</button>
