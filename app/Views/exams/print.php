<?php
/**
 * "Экзаменационная ведомость (для промежуточной аттестации обучающихся)" by Order No. 130
 * (MES RK, 06.04.2020, Appendix 8). The grade goes to the "письменно" or "устно" column by the exam
 * form and always to "общая". Ticket numbers and times are filled in by hand.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $exam
 * @var list<array<string, mixed>> $rows
 * @var array<string, mixed>|null $scheme
 * @var array<string, mixed> $group
 */
use App\Services\JournalService;
use App\Services\Order130;

$college = App\Services\Settings::collegeName();
$examiner = full_name(['last_name' => $exam['examiner_last_name'], 'first_name' => $exam['examiner_first_name'], 'middle_name' => $exam['examiner_middle_name']]);
$subject = localized(['name_ru' => $exam['subject_name_ru'], 'name_kk' => $exam['subject_name_kk']]);
$oral = $exam['exam_form'] === 'oral';
$start = $exam['start_time'] !== null ? substr((string) $exam['start_time'], 0, 5) : '______';
?>
<div class="o130">
    <div class="form-title" style="margin-bottom: 0"><?= e(__('o130.exam_title')) ?> № <?= e($exam['statement_number']) ?></div>
    <div class="side"><?= e(__('o130.exam_sub')) ?></div>
    <div class="side" style="margin: 3mm 0"><span style="border-bottom: 1px solid #000; padding: 0 4mm"><?= e($college !== '' ? $college : str_repeat(' ', 60)) ?></span></div>
    <div class="fields" style="line-height: 2">
        <?= e(__('o130.by_subject')) ?> <span><?= e(trim($exam['subject_code'] . ' ' . $subject)) ?></span>
        <span><?= e($exam['course']) ?></span> <?= e(__('o130.course_n')) ?>
        <span><?= e($exam['group_code']) ?></span> <?= e(__('o130.group_n')) ?><br>
        <?= e(__('o130.specialty')) ?> <span><?= e(Order130::specialty($group)) ?></span><br>
        <?= e(__('o130.examiner')) ?> <span><?= e($examiner) ?></span>
        · <?= e(format_date($exam['exam_date'])) ?> · <?= e(localized(['name_ru' => $exam['type_name_ru'], 'name_kk' => $exam['type_name_kk']])) ?>
        <?php if ($scheme !== null): ?> · <?= e(__('grading.scale')) ?> <?= e(JournalService::normalize($scheme['grade_min'])) ?>–<?= e(JournalService::normalize($scheme['grade_max'])) ?><?php endif; ?>
    </div>
    <table>
        <thead>
        <tr>
            <th rowspan="2" style="width: 9mm"><?= e(__('o130.n')) ?></th>
            <th rowspan="2" style="width: 22mm"><?= e(__('o130.ticket')) ?></th>
            <th rowspan="2"><?= e(__('o130.examinee')) ?></th>
            <th colspan="3"><?= e(__('o130.marks')) ?></th>
            <th rowspan="2" style="width: 28mm"><?= e(__('o130.examiner_sign')) ?></th>
        </tr>
        <tr><th style="width: 18mm"><?= e(__('o130.written')) ?></th><th style="width: 18mm"><?= e(__('o130.oral')) ?></th><th style="width: 22mm"><?= e(__('o130.overall')) ?></th></tr>
        </thead>
        <tbody>
        <?php $i = 0; foreach ($rows as $r): if (!$r['member_on_exam']) { continue; } $i++;
            $mark = $r['admission'] === 'not_admitted' ? __('exams.admission_values.not_admitted')
                : ($r['attendance'] !== null && $r['attendance'] !== 'present' ? __('exams.attendance_values.' . $r['attendance']) : (JournalService::normalize($r['value']) ?? ''));
            $graded = $r['admission'] !== 'not_admitted' && ($r['attendance'] === null || $r['attendance'] === 'present') && $mark !== ''; ?>
            <tr>
                <td><?= e($i) ?></td>
                <td></td>
                <td class="l"><?= e(full_name($r)) ?></td>
                <td><?= e($graded && !$oral ? $mark : '') ?></td>
                <td><?= e($graded && $oral ? $mark : '') ?></td>
                <td><strong><?= e($mark) ?></strong></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="sign" style="line-height: 2">
        <?= e(__('o130.time')) ?>:<br>
        <?= e(__('o130.time_written')) ?>: <?= e(__('o130.start')) ?> <?= e($oral ? '______' : $start) ?> <?= e(__('o130.end')) ?> ______ &nbsp;&nbsp;
        <?= e(__('o130.time_oral')) ?>: <?= e(__('o130.start')) ?> <?= e($oral ? $start : '______') ?> <?= e(__('o130.end')) ?> ______<br>
        <?= e(__('o130.total_time')) ?> ____ <?= e(__('o130.hm')) ?><br>
        <?= e(__('o130.examiner_sign')) ?> ____________________ (<?= e($examiner) ?>)
    </div>
    <p class="basis"><?= e(__('exams.statuses.' . $exam['status'])) ?> · <?= e(__('o130.basis')) ?> · <?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></p>
</div>
