<?php
/** @var App\Core\View $this */
/** @var array<string, mixed>|null $survey */
/** @var list<array<string, mixed>> $questions */
/** @var array<int, string> $departments */
/** @var array<int, string> $groups */
$status = $survey['status'] ?? 'draft';
$draft = $status === 'draft';
$locked = !$draft; // audience, mode and start are fixed after publishing
$none = ['' => __('common.all')];
$audiences = ['students' => __('surveys.audiences.students'), 'teachers' => __('surveys.audiences.teachers'), 'all' => __('surveys.audiences.all')];
$anonymousOld = old('title_ru', null) !== null ? old('is_anonymous', '') !== '' : (bool) ($survey['is_anonymous'] ?? true);
$statusForm = static function (string $action, string $label, string $class, string $icon, ?string $confirm) use ($survey): string {
    return '<form method="post" action="' . e(url('/surveys/' . $survey['id'] . '/status')) . '"' . ($confirm !== null ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><button type="submit" class="btn btn-sm ' . e($class) . '"><i class="bi ' . e($icon) . ' me-1" aria-hidden="true"></i>' . e($label) . '</button></form>';
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => $survey !== null ? localized($survey, 'title') : __('surveys.create'),
    'subtitle' => $survey !== null ? __('surveys.statuses.' . $status) . ' · ' . __('surveys.responses') . ': ' . $survey['responses_count'] : __('surveys.create_hint'),
    'back' => '/surveys',
]) ?>

<?php if (error('status')): ?><div class="alert alert-danger small"><?= e(implode(' ', errors()['status'] ?? [])) ?></div><?php endif; ?>

<?php if ($survey !== null): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= e(url('/surveys/' . $survey['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1" aria-hidden="true"></i><?= e(__('surveys.preview')) ?></a>
        <?php if ($draft): ?>
            <?= $statusForm('publish', __('surveys.publish'), 'btn-success', 'bi-send', __('surveys.publish_confirm')) ?>
            <?= $statusForm('delete', __('surveys.delete'), 'btn-outline-danger', 'bi-trash', __('surveys.delete_confirm')) ?>
        <?php endif; ?>
        <?php if ($status === 'published'): ?><?= $statusForm('close', __('surveys.close'), 'btn-outline-warning', 'bi-stop-circle', __('surveys.close_confirm')) ?><?php endif; ?>
        <?php if ($status !== 'draft'): ?><a href="<?= e(url('/surveys/' . $survey['id'] . '/results')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart me-1" aria-hidden="true"></i><?= e(__('surveys.results')) ?></a><?php endif; ?>
        <?= $statusForm('duplicate', __('surveys.duplicate'), 'btn-outline-secondary', 'bi-copy', null) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url($survey !== null ? '/surveys/' . $survey['id'] : '/surveys')) ?>" class="card mb-3" novalidate>
    <?= csrf_field() ?>
    <?php if ($survey !== null): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="card-header fw-semibold"><?= e(__('surveys.settings')) ?></div>
    <fieldset class="card-body"<?= $status === 'closed' ? ' disabled' : '' ?>>
        <div class="row g-2">
            <div class="col-12 col-md-6"><?= $this->partial('partials/field', ['name' => 'title_ru', 'label' => __('fields.title_ru'), 'value' => $survey['title_ru'] ?? '', 'required' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
            <div class="col-12 col-md-6"><?= $this->partial('partials/field', ['name' => 'title_kk', 'label' => __('fields.title_kk'), 'value' => $survey['title_kk'] ?? '', 'attrs' => ['maxlength' => 255]]) ?></div>
            <?php foreach (['description_ru', 'description_kk'] as $d): ?>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label fw-semibold" for="f_<?= e($d) ?>"><?= e(__('fields.' . $d)) ?></label>
                    <textarea class="form-control" id="f_<?= e($d) ?>" name="<?= e($d) ?>" rows="3" maxlength="5000"><?= e(old($d, $survey[$d] ?? '')) ?></textarea>
                </div>
            <?php endforeach; ?>
            <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'starts_on', 'label' => __('fields.starts_on'), 'type' => 'date', 'value' => $survey['starts_on'] ?? date('Y-m-d'), 'required' => true, 'attrs' => ['disabled' => $locked]]) ?></div>
            <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'ends_on', 'label' => __('fields.ends_on'), 'type' => 'date', 'value' => $survey['ends_on'] ?? date('Y-m-d', strtotime('+14 days')), 'required' => true]) ?></div>
            <div class="col-12 col-md-6 d-flex align-items-end pb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="f_is_anonymous" name="is_anonymous" value="1"<?= $anonymousOld ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?> aria-describedby="anonHelp">
                    <label class="form-check-label fw-semibold" for="f_is_anonymous"><?= e(__('surveys.anonymous_mode')) ?></label>
                    <div id="anonHelp" class="form-text"><?= e(__('surveys.anonymous_hint')) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-3"><?= $this->partial('partials/field', ['name' => 'audience', 'label' => __('surveys.audience'), 'type' => 'select', 'options' => $audiences, 'value' => $survey['audience'] ?? 'students', 'attrs' => ['disabled' => $locked]]) ?></div>
            <div class="col-12 col-md-3"><?= $this->partial('partials/field', ['name' => 'department_id', 'label' => __('fields.department_id'), 'type' => 'select', 'options' => $none + $departments, 'value' => $survey['department_id'] ?? '', 'attrs' => ['disabled' => $locked]]) ?></div>
            <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'course', 'label' => __('fields.course'), 'type' => 'select', 'options' => $none + [1 => '1', 2 => '2', 3 => '3', 4 => '4'], 'value' => $survey['course'] ?? '', 'attrs' => ['disabled' => $locked]]) ?></div>
            <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'group_id', 'label' => __('fields.group_id'), 'type' => 'select', 'options' => $none + $groups, 'value' => $survey['group_id'] ?? '', 'attrs' => ['disabled' => $locked]]) ?></div>
        </div>
        <p class="form-text mt-0"><?= e(__($locked ? 'surveys.locked_hint' : 'surveys.audience_hint')) ?></p>
        <?php if ($status !== 'closed'): ?><button type="submit" class="btn btn-primary"><?= e($survey === null ? __('surveys.create_and_add') : __('common.save')) ?></button><?php endif; ?>
    </fieldset>
</form>

<?php if ($survey !== null): ?>
    <div class="card mb-3" id="questions">
        <div class="card-header fw-semibold"><?= e(__('surveys.questions')) ?> (<?= e(count($questions)) ?>)</div>
        <?php if ($questions === []): ?><div class="card-body small text-muted"><?= e(__('surveys.no_questions')) ?></div><?php endif; ?>
        <ol class="list-group list-group-flush list-group-numbered">
            <?php foreach ($questions as $i => $q): ?>
                <li class="list-group-item d-flex gap-2 align-items-start">
                    <div class="flex-grow-1 min-w-0 ms-1">
                        <div class="fw-semibold"><?= e(localized($q, 'text')) ?><?= (bool) $q['is_required'] ? ' <span class="text-danger" title="' . e(__('surveys.required')) . '">*</span>' : '' ?></div>
                        <div class="small text-muted">
                            <span class="badge app-badge-primary"><?= e(__('surveys.types.' . $q['type'])) ?></span>
                            <?php if ($q['type'] === 'scale'): ?> <?= e($q['scale_min']) ?>–<?= e($q['scale_max']) ?><?php endif; ?>
                            <?php if ($q['options'] !== []): ?> <?= e(implode(' · ', array_map(static fn (array $o): string => localized($o, 'label'), $q['options']))) ?><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($draft): ?>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <?php foreach (['up' => 'bi-arrow-up', 'down' => 'bi-arrow-down'] as $dir => $icon): ?>
                                <form method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/questions/' . $q['id'] . '/move')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="direction" value="<?= e($dir) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="<?= e(__('surveys.move_' . $dir)) ?>"<?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($questions) - 1) ? ' disabled' : '' ?>><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></button>
                                </form>
                            <?php endforeach; ?>
                            <a href="<?= e(url('/surveys/' . $survey['id'] . '/questions/' . $q['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary" aria-label="<?= e(__('surveys.edit_question')) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                            <form method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/questions/' . $q['id'])) ?>" data-confirm="<?= e(__('surveys.delete_question_confirm')) ?>">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <?php if ($draft): ?>
        <form method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/questions')) ?>" class="card" novalidate>
            <?= csrf_field() ?>
            <div class="card-header fw-semibold"><?= e(__('surveys.add_question')) ?></div>
            <div class="card-body">
                <?= $this->partial('surveys/_question_fields', ['q' => null]) ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('surveys.add_question')) ?></button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-light border small"><i class="bi bi-lock me-1" aria-hidden="true"></i><?= e(__('surveys.questions_locked')) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php $this->start('scripts'); ?>
<script src="<?= e(asset('assets/js/surveys.js')) ?>"></script>
<?php $this->stop(); ?>
