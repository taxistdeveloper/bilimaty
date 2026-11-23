<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $plan */
/** @var array<int, list<array<string, mixed>>> $bySemester */
$college = App\Services\Settings::collegeName();
$grand = 0;
?>
<header class="doc-header">
    <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
    <h1><?= e(__('curricula.one')) ?>: <?= e($plan['title']) ?></h1>
    <div class="doc-meta">
        <?= e($plan['specialty_code'] . ' — ' . localized(['name_ru' => $plan['specialty_name_ru'], 'name_kk' => $plan['specialty_name_kk']])) ?>
        <?php if ($plan['qualification_code'] !== null): ?><br><?= e($plan['qualification_code'] . ' — ' . localized(['name_ru' => $plan['qualification_name_ru'], 'name_kk' => $plan['qualification_name_kk']])) ?><?php endif; ?>
        <br><?= e(__('curricula.intake')) ?>: <?= e($plan['admission_year']) ?> · <?= e(__('groups.forms.' . $plan['study_form'])) ?> · <?= e(__('curricula.bases.' . $plan['base_education'])) ?> · <?= e(__('curricula.statuses.' . $plan['status'])) ?>
    </div>
</header>
<?php foreach ($bySemester as $semester => $items): ?>
    <?php $sum = 0; ?>
    <h2><?= e(__('dashboard.semester', ['number' => $semester])) ?></h2>
    <table>
        <thead><tr>
            <th><?= e(__('curricula.module')) ?></th><th><?= e(__('curricula.subject')) ?></th>
            <th><?= e(__('curricula.hours_total_short')) ?></th><th><?= e(__('curricula.hours_theory_short')) ?></th>
            <th><?= e(__('curricula.hours_practice_short')) ?></th><th><?= e(__('curricula.hours_lab_short')) ?></th>
            <th><?= e(__('curricula.credits')) ?></th><th><?= e(__('curricula.control')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <?php $sum += (int) $item['hours_total']; ?>
            <tr>
                <td><?= e($item['module_code'] ?? '') ?></td>
                <td><?= e($item['subject_code'] . ' ' . localized($item)) ?></td>
                <td><?= e($item['hours_total']) ?></td><td><?= e($item['hours_theory']) ?></td>
                <td><?= e($item['hours_practice']) ?></td><td><?= e($item['hours_lab']) ?></td>
                <td><?= e($item['credits'] ?? '') ?></td><td><?= e(__('curricula.controls.' . $item['control_form'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><th colspan="2"><?= e(__('curricula.semester_total')) ?></th><th><?= e($sum) ?></th><th colspan="5"></th></tr>
        </tbody>
    </table>
    <?php $grand += $sum; ?>
<?php endforeach; ?>
<p><strong><?= e(__('curricula.plan_total')) ?>: <?= e($grand) ?> <?= e(__('curricula.hours_unit')) ?></strong></p>
<footer class="doc-footer"><?= e(__('calendar.generated', ['date' => date('d.m.Y H:i')])) ?></footer>
