<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $items */
/** @var list<array{id: int, name: string}> $colleges */
$today = date('Y-m-d');
$collegeOptions = ['' => __('platform.all_colleges')];
foreach ($colleges as $c) {
    $collegeOptions[$c['id']] = $c['name'];
}
?>
<?= $this->partial('partials/page-header', ['heading' => __('platform.announcements'), 'subtitle' => __('platform.announcements_hint')]) ?>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card">
            <?php if ($items === []): ?>
                <div class="app-empty"><i class="bi bi-megaphone" aria-hidden="true"></i><p class="mb-0"><?= e(__('platform.no_announcements')) ?></p></div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($items as $a): $live = $a['starts_on'] <= $today && ($a['ends_on'] === null || $a['ends_on'] >= $today); ?>
                        <li class="list-group-item d-flex justify-content-between gap-3">
                            <div class="min-w-0">
                                <div class="fw-semibold"><?php if ($a['level'] === 'warning'): ?><i class="bi bi-exclamation-triangle text-warning me-1" aria-hidden="true"></i><?php endif; ?><?= e($a['title']) ?></div>
                                <?php if ($a['body']): ?><div class="small" style="white-space: pre-line"><?= e($a['body']) ?></div><?php endif; ?>
                                <div class="small text-muted mt-1"><?= e($a['college_name'] ?? __('platform.all_colleges')) ?> · <?= e(__('platform.audiences.' . $a['audience'])) ?> · <?= e(format_date($a['starts_on'])) ?>–<?= e($a['ends_on'] ? format_date($a['ends_on']) : '…') ?>
                                    <?= $live ? '<span class="badge app-badge-success ms-1">' . e(__('platform.live')) . '</span>' : '' ?></div>
                            </div>
                            <form method="post" action="<?= e(url('/announcements/' . $a['id'])) ?>" data-confirm="<?= e(__('platform.announcement_delete_confirm')) ?>" data-confirm-danger><?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button type="submit" class="btn btn-sm btn-link text-danger" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('platform.announcement_new')) ?></div>
            <form method="post" action="<?= e(url('/announcements')) ?>" novalidate class="card-body">
                <?= csrf_field() ?>
                <?= $this->partial('partials/field', ['name' => 'title', 'label' => __('fields.title'), 'required' => true, 'attrs' => ['maxlength' => 200]]) ?>
                <div class="mb-3"><label class="form-label fw-semibold" for="f_body"><?= e(__('platform.announcement_body')) ?></label>
                    <textarea class="form-control" id="f_body" name="body" rows="4" maxlength="2000"><?= e(old('body')) ?></textarea></div>
                <?= $this->partial('partials/field', ['name' => 'college_id', 'label' => __('platform.college'), 'type' => 'select', 'options' => $collegeOptions]) ?>
                <?= $this->partial('partials/field', ['name' => 'audience', 'label' => __('platform.audience'), 'type' => 'select', 'value' => 'admins', 'options' => ['admins' => __('platform.audiences.admins'), 'all' => __('platform.audiences.all')], 'required' => true]) ?>
                <?= $this->partial('partials/field', ['name' => 'level', 'label' => __('platform.level'), 'type' => 'select', 'value' => 'info', 'options' => ['info' => __('platform.levels.info'), 'warning' => __('platform.levels.warning')], 'required' => true]) ?>
                <div class="row">
                    <div class="col-6"><?= $this->partial('partials/field', ['name' => 'starts_on', 'label' => __('fields.starts_on'), 'type' => 'date', 'value' => $today, 'required' => true]) ?></div>
                    <div class="col-6"><?= $this->partial('partials/field', ['name' => 'ends_on', 'label' => __('fields.ends_on'), 'type' => 'date']) ?></div>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('platform.publish')) ?></button>
            </form>
        </div>
    </div>
</div>
