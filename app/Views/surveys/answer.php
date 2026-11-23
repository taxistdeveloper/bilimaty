<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $survey */
/** @var list<array<string, mixed>> $questions */
/** @var string|null $reason why the current user cannot answer (null = can) */
/** @var bool $staff */
$canAnswer = $reason === null;
$old = old('answers', []);
$old = is_array($old) ? $old : [];
$description = localized($survey, 'description');
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized($survey, 'title'),
    'subtitle' => __('surveys.until', ['date' => format_date((string) $survey['ends_on'])]),
    'back' => $staff && can('surveys.manage') ? '/surveys/' . $survey['id'] . '/edit' : '/surveys',
]) ?>

<?php if (!$canAnswer): ?>
    <div class="alert <?= $reason === 'surveys.already_answered' ? 'alert-success' : 'alert-info' ?> small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__($reason)) ?><?= $staff && $reason !== 'surveys.already_answered' ? ' ' . e(__('surveys.preview_note')) : '' ?></div>
<?php endif; ?>
<?php if (error('answers')): ?><div class="alert alert-danger small"><?= e(error('answers')) ?></div><?php endif; ?>
<?php if ($description !== ''): ?><p style="white-space: pre-line"><?= e($description) ?></p><?php endif; ?>
<p class="small text-muted"><i class="bi <?= (bool) $survey['is_anonymous'] ? 'bi-incognito' : 'bi-person' ?> me-1" aria-hidden="true"></i><?= e(__((bool) $survey['is_anonymous'] ? 'surveys.anonymous_note' : 'surveys.named_note')) ?></p>

<form method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/answers')) ?>" novalidate>
    <?= csrf_field() ?>
    <fieldset<?= $canAnswer ? '' : ' disabled' ?>>
        <?php foreach ($questions as $i => $q):
            $qid = (int) $q['id'];
            $err = error('q' . $qid);
            $name = 'answers[' . $qid . ']';
            $value = $old[$qid] ?? null;
            $legendId = 'ql' . $qid; ?>
            <div class="card mb-3<?= $err ? ' border-danger' : '' ?>">
                <div class="card-body" role="group" aria-labelledby="<?= e($legendId) ?>">
                    <div id="<?= e($legendId) ?>" class="fw-semibold mb-2"><?= e(($i + 1) . '. ' . localized($q, 'text')) ?><?php if ((bool) $q['is_required']): ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= e(__('surveys.required')) ?></span><?php endif; ?></div>
                    <?php if ($q['type'] === 'single' || $q['type'] === 'multiple'): ?>
                        <?php foreach ($q['options'] as $o): $oid = 'o' . $o['id']; $checked = $q['type'] === 'single' ? (string) $value === (string) $o['id'] : in_array((string) $o['id'], array_map('strval', (array) $value), true); ?>
                            <div class="form-check">
                                <input class="form-check-input" type="<?= $q['type'] === 'single' ? 'radio' : 'checkbox' ?>" id="<?= e($oid) ?>" name="<?= e($name . ($q['type'] === 'multiple' ? '[]' : '')) ?>" value="<?= e($o['id']) ?>"<?= $checked ? ' checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($oid) ?>"><?= e(localized($o, 'label')) ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($q['type'] === 'scale'): ?>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-muted"><?= e(localized($q, 'min_label')) ?></span>
                            <div class="btn-group flex-wrap" role="radiogroup" aria-labelledby="<?= e($legendId) ?>">
                                <?php for ($v = (int) $q['scale_min']; $v <= (int) $q['scale_max']; $v++): $vid = 's' . $qid . '_' . $v; ?>
                                    <input type="radio" class="btn-check" name="<?= e($name) ?>" id="<?= e($vid) ?>" value="<?= e($v) ?>" autocomplete="off"<?= (string) $value === (string) $v ? ' checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="<?= e($vid) ?>"><?= e($v) ?></label>
                                <?php endfor; ?>
                            </div>
                            <span class="small text-muted"><?= e(localized($q, 'max_label')) ?></span>
                        </div>
                    <?php else: ?>
                        <label class="visually-hidden" for="t<?= e($qid) ?>"><?= e(localized($q, 'text')) ?></label>
                        <textarea class="form-control" id="t<?= e($qid) ?>" name="<?= e($name) ?>" rows="3" maxlength="2000"><?= e(is_string($value) ? $value : '') ?></textarea>
                    <?php endif; ?>
                    <?php if ($err): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($canAnswer): ?><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i><?= e(__('surveys.send')) ?></button><?php endif; ?>
    </fieldset>
</form>
