<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $type */
/** @var array<string, mixed>|null $r request being edited */
/** @var array<string, mixed>|null $student */
/** @var array<string, string> $changes saved requested changes */
use App\Services\DocumentService;

$generator = (string) $type['generator'];
$oldChanges = old('changes', null);
$changeValue = static fn (string $f): string => (string) (is_array($oldChanges) ? ($oldChanges[$f] ?? '') : ($changes[$f] ?? ''));
$description = localized(['name_ru' => $type['description_ru'], 'name_kk' => $type['description_kk']]);
$inputTypes = ['birth_date' => 'date', 'email' => 'email', 'phone' => 'tel'];
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized($type),
    'subtitle' => $r !== null && $r['number'] !== null ? __('documents.request') . ' ' . $r['number'] : __('documents.new_request'),
    'back' => $r !== null ? '/documents/' . $r['id'] : '/documents',
]) ?>

<?php if ($r !== null && $r['status'] === 'needs_info'): ?>
    <div class="alert alert-warning small"><i class="bi bi-chat-left-dots me-1" aria-hidden="true"></i><?= e(__('documents.needs_info_hint')) ?></div>
<?php endif; ?>
<?php if (error('status')): ?><div class="alert alert-danger small"><?= e(error('status')) ?></div><?php endif; ?>

<form method="post" action="<?= e(url($r !== null ? '/documents/' . $r['id'] : '/documents')) ?>" enctype="multipart/form-data" class="card" novalidate>
    <?= csrf_field() ?>
    <?php if ($r !== null): ?><?= method_field('PUT') ?><?php else: ?><input type="hidden" name="type_id" value="<?= e($type['id']) ?>"><?php endif; ?>
    <div class="card-body">
        <?php if ($description !== ''): ?><p class="text-muted small"><?= e($description) ?></p><?php endif; ?>

        <?php if ($generator === 'personal_data'): ?>
            <p class="small"><?= e(__('documents.changes_hint')) ?></p>
            <?php $changesError = error('changes'); ?>
            <?php if ($changesError): ?><div class="alert alert-danger small py-2"><?= e($changesError) ?></div><?php endif; ?>
            <div class="row g-3 mb-3">
                <?php foreach (array_keys(DocumentService::PERSONAL_FIELDS) as $field): $err = error('changes.' . $field); $id = 'chg_' . $field; ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="<?= e($id) ?>"><?= e(__('fields.' . $field)) ?></label>
                        <input type="<?= e($inputTypes[$field] ?? 'text') ?>" class="form-control<?= $err ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="changes[<?= e($field) ?>]"
                               value="<?= e($changeValue($field)) ?>" maxlength="<?= $field === 'email' ? 190 : 100 ?>"<?= $err ? ' aria-describedby="' . e($id) . '_err"' : '' ?>>
                        <div class="form-text"><?= e(__('documents.current')) ?>: <?= e(($student[$field] ?? null) !== null && $student[$field] !== '' ? ($field === 'birth_date' ? format_date((string) $student[$field]) : (string) $student[$field]) : '—') ?></div>
                        <?php if ($err): ?><div id="<?= e($id) ?>_err" class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ((bool) $type['needs_purpose'] || in_array($generator, ['study_certificate', 'transcript'], true)): ?>
            <div class="row g-3">
                <div class="col-12 col-md-9"><?= $this->partial('partials/field', ['name' => 'purpose', 'label' => __('fields.purpose'), 'value' => $r['purpose'] ?? '', 'required' => (bool) $type['needs_purpose'], 'help' => __('documents.purpose_hint'), 'attrs' => ['maxlength' => 255]]) ?></div>
                <div class="col-6 col-md-3"><?= $this->partial('partials/field', ['name' => 'copies', 'label' => __('fields.copies'), 'type' => 'number', 'value' => $r['copies'] ?? 1, 'attrs' => ['min' => 1, 'max' => 5]]) ?></div>
            </div>
        <?php endif; ?>

        <?php $detailsError = error('details'); ?>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="details"><?= e(__($generator === 'none' ? 'fields.details' : 'documents.details_optional')) ?><?php if ($generator === 'none'): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?></label>
            <textarea class="form-control<?= $detailsError ? ' is-invalid' : '' ?>" id="details" name="details" rows="4" maxlength="5000"<?= $detailsError ? ' aria-describedby="details_err"' : '' ?>><?= e(old('details', $r['details'] ?? '')) ?></textarea>
            <?php if ($detailsError): ?><div id="details_err" class="invalid-feedback d-block"><?= e($detailsError) ?></div><?php endif; ?>
        </div>

        <?php if ((bool) $type['allow_attachment']): $fileError = error('attachment'); ?>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="attachment"><?= e(__('documents.attachment')) ?><?php if ($generator === 'personal_data'): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?></label>
                <input type="file" class="form-control<?= $fileError ? ' is-invalid' : '' ?>" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="attachment_help<?= $fileError ? ' attachment_err' : '' ?>">
                <div id="attachment_help" class="form-text"><?= e(__('documents.attachment_hint')) ?><?php if (($r['attachment_name'] ?? null) !== null): ?> <?= e(__('documents.attached')) ?>: <strong><?= e($r['attachment_name']) ?></strong><?php endif; ?></div>
                <?php if ($fileError): ?><div id="attachment_err" class="invalid-feedback d-block"><?= e($fileError) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
        <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i><?= e(__('documents.submit')) ?></button>
        <?php if ($r === null || $r['status'] === 'draft'): ?>
            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary"><?= e(__('documents.save_draft')) ?></button>
        <?php endif; ?>
    </div>
</form>
