<?php
/**
 * @var App\Core\View $this
 * @var string $date
 * @var list<array<string, mixed>> $rows
 */
use App\Services\PracticeService;

$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));
?>
<?= $this->partial('partials/page-header', ['heading' => __('practice.day_title'), 'subtitle' => __('practice.day_hint'), 'back' => '/practice']) ?>

<div class="card mb-3">
    <form method="get" action="<?= e(url('/practice/day')) ?>" class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small text-muted mb-1" for="date"><?= e(__('practice.date')) ?></label>
            <input type="date" id="date" name="date" class="form-control" value="<?= e($date) ?>" required>
        </div>
        <div class="col-6 col-md-2"><button type="submit" class="btn btn-primary w-100"><?= e(__('practice.show')) ?></button></div>
        <div class="col-6 col-md-6 d-flex gap-2 justify-content-md-end">
            <a class="btn btn-outline-secondary" href="<?= e(url('/practice/day?date=' . $prev)) ?>" aria-label="<?= e(format_date($prev)) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <a class="btn btn-outline-secondary" href="<?= e(url('/practice/day')) ?>"><?= e(__('common.today')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= e(url('/practice/day?date=' . $next)) ?>" aria-label="<?= e(format_date($next)) ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header fw-semibold"><?= e(__('practice.day_title')) ?>: <?= e(format_date($date)) ?></div>
    <?php if ($rows === []): ?>
        <div class="app-empty"><i class="bi bi-calendar2-x" aria-hidden="true"></i><p class="mb-0"><?= e(__('practice.day_empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light"><tr>
                    <th><?= e(__('practice.time')) ?></th><th><?= e(__('practice.f_title')) ?></th><th><?= e(__('practice.supervisor_short')) ?></th>
                    <th><?= e(__('practice.group_short')) ?></th><th><?= e(__('practice.col_place')) ?></th><th class="text-end"><?= e(__('practice.col_hours')) ?></th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap fw-semibold"><?= e(substr((string) $r['time_start'], 0, 5)) ?>–<?= e(substr((string) $r['time_end'], 0, 5)) ?></td>
                        <td><?= e($r['title']) ?><div class="small text-muted"><?= e($r['topic']) ?></div></td>
                        <td class="text-nowrap"><?= e(full_name(['last_name' => $r['teacher_last_name'], 'first_name' => $r['teacher_first_name'], 'middle_name' => $r['teacher_middle_name']], true)) ?></td>
                        <td class="text-nowrap"><?= e($r['group_code']) ?></td>
                        <td><?php if ($r['is_distance']): ?><span class="badge text-bg-info me-1"><?= e(__('practice.distance_mark')) ?></span><?php endif; ?><?= e($r['place']) ?></td>
                        <td class="text-end"><?= e(PracticeService::fmt(PracticeService::hours((string) $r['time_start'], (string) $r['time_end']))) ?></td>
                        <td class="text-end"><a href="<?= e(url('/practice/' . $r['assignment_id'])) ?>" class="btn btn-sm btn-outline-primary"><?= e(__('practice.schedule_link')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top small text-muted"><?= e(__('practice.total')) ?>: <?= e(count($rows)) ?></div>
    <?php endif; ?>
</div>
