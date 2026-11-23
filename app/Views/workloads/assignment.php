<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $a workload line */
/** @var array<string, mixed>|null $line plan/fact of the line */
/** @var array<string, mixed>|null $teacher */
/** @var list<array<string, mixed>> $actuals */
/** @var bool $canManage */
/** @var bool $canRecord */
use App\Services\WorkloadService;

$h = static fn ($v): string => WorkloadService::hours($v);
$editable = $canManage && $a['plan_status'] === 'draft';
$subject = $a['subject_id'] !== null ? $a['subject_code'] . ' ' . localized(['name_ru' => $a['subject_name_ru'], 'name_kk' => $a['subject_name_kk']]) : '—';
$fact = $line['fact'] ?? 0;
$myUserId = (int) (auth_user()['id'] ?? 0);
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized(['name_ru' => $a['type_name_ru'], 'name_kk' => $a['type_name_kk']]) . ($a['group_code'] !== null ? ' · ' . $a['group_code'] : ''),
    'subtitle' => ($teacher !== null ? full_name($teacher, true) . ' · ' : '') . __('dashboard.semester', ['number' => $a['semester_number']]) . ' · ' . $subject,
    'back' => '/workload/teachers/' . $a['teacher_id'] . '?year=' . $a['academic_year_id'],
]) ?>

<div class="row g-3 mb-3">
    <?php foreach ([['workload.plan', $a['hours'], ''], ['workload.fact', $fact, ''], ['workload.remainder', (int) $a['hours'] - (float) $fact, (int) $a['hours'] - (float) $fact < 0 ? 'text-danger' : '']] as [$label, $value, $class]): ?>
        <div class="col-4"><div class="card h-100"><div class="card-body">
            <div class="small text-muted"><?= e(__($label)) ?></div>
            <div class="h4 fw-bold mb-0 <?= e($class) ?>"><?= e($h($value)) ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<?php if (error('status')): ?><div class="alert alert-danger small"><?= e(error('status')) ?></div><?php endif; ?>
<div class="alert alert-light border small">
    <i class="bi <?= $a['lesson_type'] !== null ? 'bi-journal-bookmark' : 'bi-pencil-square' ?> me-1" aria-hidden="true"></i>
    <?= e($a['lesson_type'] !== null ? __('workload.fact_journal_line') : __('workload.fact_manual_line')) ?>
    <?php if ($a['subgroup'] !== null): ?> · <?= e(__('schedule.subgroup_n', ['n' => $a['subgroup']])) ?><?php endif; ?>
    <?php if ($a['note']): ?> · <?= e($a['note']) ?><?php endif; ?>
</div>

<div class="row g-3">
    <?php if ($a['lesson_type'] === null): ?>
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('workload.facts')) ?></div>
                <?php if ($actuals === []): ?><div class="card-body small text-muted"><?= e(__('workload.no_facts')) ?></div><?php endif; ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($actuals as $x): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <span class="fw-semibold"><?= e(format_date($x['work_date'])) ?></span> · <?= e($h($x['hours'])) ?> <?= e(__('workload.h')) ?>
                                <?php if ($x['note']): ?><div class="small text-muted text-truncate"><?= e($x['note']) ?></div><?php endif; ?>
                                <div class="small text-muted"><?= e($x['last_name'] !== null ? full_name($x, true) : '') ?></div>
                            </div>
                            <?php if ($canRecord && (can('workload.manage') || (int) $x['created_by'] === $myUserId)): ?>
                                <form method="post" action="<?= e(url('/workload/actuals/' . $x['id'])) ?>" data-confirm="<?= e(__('workload.fact_delete_confirm')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($canRecord): ?>
                    <form method="post" action="<?= e(url('/workload/assignments/' . $a['id'] . '/actuals')) ?>" class="card-body border-top" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-6 col-md-4"><?= $this->partial('partials/field', ['name' => 'work_date', 'label' => __('workload.work_date'), 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true, 'attrs' => ['max' => date('Y-m-d')], 'idPrefix' => 'act_']) ?></div>
                            <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'hours', 'label' => __('workload.hours'), 'required' => true, 'attrs' => ['inputmode' => 'decimal', 'maxlength' => 6], 'idPrefix' => 'act_', 'ownsInput' => old('work_date') !== null]) ?></div>
                            <div class="col-12 col-md-5"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'attrs' => ['maxlength' => 255], 'idPrefix' => 'act_', 'ownsInput' => old('work_date') !== null]) ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('workload.add_fact')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($editable): ?>
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold"><?= e(__('workload.edit_line')) ?></div>
                <form method="post" action="<?= e(url('/workload/assignments/' . $a['id'])) ?>" class="card-body" novalidate>
                    <?= csrf_field() ?><input type="hidden" name="_method" value="PUT">
                    <div class="row g-2">
                        <div class="col-6"><?= $this->partial('partials/field', ['name' => 'hours', 'label' => __('workload.hours'), 'type' => 'number', 'value' => $a['hours'], 'required' => true, 'attrs' => ['min' => 1, 'max' => 2000], 'idPrefix' => 'ed_', 'ownsInput' => old('work_date') === null]) ?></div>
                        <div class="col-6"><?= $this->partial('partials/field', ['name' => 'subgroup', 'label' => __('fields.subgroup'), 'type' => 'select', 'options' => ['' => '—', 1 => '1', 2 => '2', 3 => '3', 4 => '4'], 'value' => $a['subgroup'] ?? '', 'idPrefix' => 'ed_', 'ownsInput' => old('work_date') === null]) ?></div>
                    </div>
                    <?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'value' => $a['note'] ?? '', 'attrs' => ['maxlength' => 255], 'idPrefix' => 'ed_', 'ownsInput' => old('work_date') === null]) ?>
                    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/workload/assignments/' . $a['id'])) ?>" class="card-footer bg-transparent" data-confirm="<?= e(__('workload.delete_line_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('workload.delete_line')) ?></button>
                </form>
            </div>
        </div>
    <?php elseif ($canManage): ?>
        <div class="col-12 col-lg-5"><div class="alert alert-light border small mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('workload.plan_locked')) ?></div></div>
    <?php endif; ?>
</div>
