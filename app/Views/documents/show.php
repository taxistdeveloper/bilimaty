<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $r */
/** @var array<string, mixed>|null $student */
/** @var array<string, string> $changes */
/** @var list<array<string, mixed>> $history */
/** @var array<string, mixed>|null $document generated certificate */
/** @var bool $own */
/** @var bool $process */
use App\Core\Auth;

$status = (string) $r['status'];
$typeName = localized(['name_ru' => $r['type_name_ru'], 'name_kk' => $r['type_name_kk']]);
$final = in_array($status, ['rejected', 'completed'], true);
$mine = $r['assigned_to'] !== null && (int) $r['assigned_to'] === (int) Auth::id();
$overdue = $r['due_date'] !== null && $r['due_date'] < date('Y-m-d') && in_array($status, ['submitted', 'in_review', 'needs_info'], true);
$actionForm = static function (string $action, string $label, string $class, string $icon, bool $comment, bool $commentRequired, ?string $confirm = null) use ($r): string {
    $html = '<form method="post" action="' . e(url('/documents/' . $r['id'] . '/status')) . '" class="mb-2"' . ($confirm !== null ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">';
    if ($comment) {
        $id = 'c_' . $action;
        $html .= '<label class="visually-hidden" for="' . e($id) . '">' . e(__('documents.comment')) . '</label><textarea class="form-control form-control-sm mb-1" id="' . e($id) . '" name="comment" rows="2" maxlength="1000"'
            . ($commentRequired ? ' required' : '') . ' placeholder="' . e(__($commentRequired ? 'documents.comment_required_ph' : 'documents.comment_optional_ph')) . '"></textarea>';
    }

    return $html . '<button type="submit" class="btn btn-sm ' . e($class) . ' w-100"><i class="bi ' . e($icon) . ' me-1" aria-hidden="true"></i>' . e($label) . '</button></form>';
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => $typeName,
    'subtitle' => ($r['number'] !== null ? __('documents.request') . ' ' . $r['number'] : __('documents.no_number')) . ($process ? ' · ' . full_name($r, true) . ' (' . ($r['group_code'] ?? '—') . ')' : ''),
    'back' => '/documents',
]) ?>

<?php foreach (['status', 'comment'] as $key): if (error($key)): ?><div class="alert alert-danger small"><?= e(error($key)) ?></div><?php endif; endforeach; ?>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                    <?= $this->partial('documents/_status', ['status' => $status]) ?>
                    <?php if ($r['submitted_at'] !== null): ?><span class="small text-muted"><?= e(__('documents.submitted_at')) ?>: <?= e(format_date((string) $r['submitted_at'], 'd.m.Y H:i')) ?></span><?php endif; ?>
                    <?php if ($r['due_date'] !== null && in_array($status, ['submitted', 'in_review', 'needs_info'], true)): ?><span class="small<?= $overdue ? ' text-danger fw-semibold' : ' text-muted' ?>"><?= e(__('documents.due_date')) ?>: <?= e(format_date((string) $r['due_date'])) ?></span><?php endif; ?>
                    <?php if ($r['assigned_to'] !== null): ?><span class="small text-muted"><?= e(__('documents.assignee')) ?>: <?= e(full_name(['last_name' => $r['assignee_last_name'], 'first_name' => $r['assignee_first_name'], 'middle_name' => $r['assignee_middle_name']], true)) ?></span><?php endif; ?>
                </div>
                <dl class="row small mb-0">
                    <?php if ($process && $student !== null): ?>
                        <dt class="col-sm-4 text-muted fw-normal"><?= e(__('journal.student')) ?></dt>
                        <dd class="col-sm-8"><?php if (can('students.view')): ?><a href="<?= e(url('/students/' . $student['id'])) ?>" class="text-decoration-none"><?= e(full_name($student)) ?></a><?php else: ?><?= e(full_name($student)) ?><?php endif; ?>
                            · <?= e($student['student_number']) ?> · <?= e($student['group_code'] ?? '—') ?> · <?= e(__('students.statuses.' . $student['status'])) ?></dd>
                    <?php endif; ?>
                    <?php if ($r['purpose'] !== null): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.purpose')) ?></dt><dd class="col-sm-8"><?= e($r['purpose']) ?></dd><?php endif; ?>
                    <?php if (in_array($r['generator'], ['study_certificate', 'transcript'], true)): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.copies')) ?></dt><dd class="col-sm-8"><?= e($r['copies']) ?></dd><?php endif; ?>
                    <?php if ($r['details'] !== null): ?><dt class="col-sm-4 text-muted fw-normal"><?= e(__('fields.details')) ?></dt><dd class="col-sm-8" style="white-space: pre-line"><?= e($r['details']) ?></dd><?php endif; ?>
                    <?php if ($r['attachment_path'] !== null): ?>
                        <dt class="col-sm-4 text-muted fw-normal"><?= e(__('documents.attachment')) ?></dt>
                        <dd class="col-sm-8"><a href="<?= e(url('/documents/' . $r['id'] . '/attachment')) ?>" class="text-decoration-none"><i class="bi bi-paperclip me-1" aria-hidden="true"></i><?= e($r['attachment_name']) ?></a> <span class="text-muted">(<?= e(number_format(max(1, (int) ceil((int) $r['attachment_size'] / 1024)), 0, '.', ' ')) ?> KB)</span></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($changes !== []): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('documents.requested_changes')) ?></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th><?= e(__('documents.field')) ?></th><th><?= e(__('documents.current')) ?></th><th><?= e(__('documents.new_value')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($changes as $field => $value): ?>
                            <tr><td><?= e(__('fields.' . $field)) ?></td><td class="text-muted"><?= e($status === 'approved' || $status === 'completed' ? '—' : (string) ($student[$field] ?? '—')) ?></td><td class="fw-semibold"><?= e($value) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array($status, ['approved', 'completed', 'rejected'], true)): ?>
            <div class="card mb-3 border-<?= $status === 'rejected' ? 'danger' : 'success' ?>">
                <div class="card-body">
                    <div class="fw-semibold mb-1"><?= e(__('documents.decision')) ?>: <?= e(__('documents.statuses.' . ($status === 'rejected' ? 'rejected' : 'approved'))) ?><?= $r['decided_at'] !== null ? ' · ' . e(format_date((string) $r['decided_at'], 'd.m.Y H:i')) : '' ?></div>
                    <?php if ($r['decision_comment'] !== null): ?><p class="small mb-2" style="white-space: pre-line"><?= e($r['decision_comment']) ?></p><?php endif; ?>
                    <?php if ($document !== null): ?>
                        <a href="<?= e(url('/documents/' . $r['id'] . '/certificate')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i><?= e(__('documents.certificate')) ?> <?= e($document['number']) ?></a>
                        <?php if ($document['status'] === 'revoked'): ?><span class="badge app-badge-danger ms-2"><?= e(__('transcripts.statuses.revoked')) ?></span><?php endif; ?>
                    <?php elseif ($r['transcript_id'] !== null): ?>
                        <a href="<?= e(url('/transcripts/' . $r['transcript_id'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i><?= e(__('transcripts.document')) ?></a>
                    <?php elseif ($r['generator'] === 'personal_data' && $status !== 'rejected'): ?>
                        <p class="small text-success mb-0"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= e(__('documents.changes_applied')) ?></p>
                    <?php endif; ?>
                    <?php if ($status === 'approved' && $own): ?><p class="small text-muted mt-2 mb-0"><?= e(__('documents.approved_hint')) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" id="history">
            <div class="card-header fw-semibold"><?= e(__('documents.history')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($history as $h): ?>
                    <li class="list-group-item<?= (int) $h['is_internal'] === 1 ? ' bg-warning-subtle' : '' ?>">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <span>
                                <?php if ($h['kind'] === 'status'): ?><i class="bi bi-arrow-right-circle me-1 text-primary" aria-hidden="true"></i><?= e($h['from_status'] !== null ? __('documents.statuses.' . $h['from_status']) . ' → ' : '') ?><strong><?= e(__('documents.statuses.' . $h['to_status'])) ?></strong>
                                <?php else: ?><i class="bi bi-chat-left-text me-1 text-secondary" aria-hidden="true"></i><?= e(__((int) $h['is_internal'] === 1 ? 'documents.internal_note' : 'documents.comment')) ?><?php endif; ?>
                            </span>
                            <span class="text-muted"><?= e($h['last_name'] !== null ? full_name($h, true) . ' · ' : '') ?><?= e(format_date((string) $h['created_at'], 'd.m.Y H:i')) ?></span>
                        </div>
                        <?php if ($h['comment'] !== null): ?><div class="mt-1" style="white-space: pre-line"><?= e($h['comment']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($status !== 'draft' && !$final): ?>
                <form method="post" action="<?= e(url('/documents/' . $r['id'] . '/comments')) ?>" class="card-body border-top">
                    <?= csrf_field() ?>
                    <label class="form-label small fw-semibold" for="newComment"><?= e(__('documents.add_comment')) ?></label>
                    <textarea class="form-control mb-2" id="newComment" name="comment" rows="2" maxlength="2000" required></textarea>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('documents.send')) ?></button>
                        <?php if ($process && !$own): ?>
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="internal" name="internal" value="1"><label class="form-check-label small" for="internal"><?= e(__('documents.internal_hint')) ?></label></div>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <?php if ($own && in_array($status, ['draft', 'needs_info'], true)): ?>
            <div class="card mb-3"><div class="card-body">
                <a href="<?= e(url('/documents/' . $r['id'] . '/edit')) ?>" class="btn btn-primary btn-sm w-100 mb-2"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__($status === 'needs_info' ? 'documents.edit_and_resubmit' : 'documents.edit')) ?></a>
                <?php if ($status === 'draft'): ?>
                    <?= $actionForm('submit', __('documents.submit'), 'btn-outline-primary', 'bi-send', false, false) ?>
                    <form method="post" action="<?= e(url('/documents/' . $r['id'])) ?>" data-confirm="<?= e(__('documents.delete_confirm')) ?>">
                        <?= csrf_field() ?><?= method_field('DELETE') ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('documents.delete_draft')) ?></button>
                    </form>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>

        <?php if ($process && $status !== 'draft' && !$final): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><?= e(__('documents.processing')) ?></div>
                <div class="card-body">
                    <?php if ($status === 'submitted' || ($status === 'in_review' && !$mine)): ?>
                        <?= $actionForm('take', __('documents.take'), 'btn-primary', 'bi-person-check', false, false) ?>
                    <?php endif; ?>
                    <?php if ($status === 'in_review'): ?>
                        <?= $actionForm('approve', __('documents.approve'), 'btn-success', 'bi-check2-circle', true, false, __('documents.approve_confirm.' . $r['generator'])) ?>
                        <?= $actionForm('request_info', __('documents.request_info'), 'btn-outline-warning', 'bi-question-circle', true, true) ?>
                    <?php endif; ?>
                    <?php if (in_array($status, ['submitted', 'in_review', 'needs_info'], true)): ?>
                        <?= $actionForm('reject', __('documents.reject'), 'btn-outline-danger', 'bi-x-circle', true, true, __('documents.reject_confirm')) ?>
                    <?php endif; ?>
                    <?php if ($status === 'approved'): ?>
                        <?= $actionForm('complete', __('documents.complete'), 'btn-primary', 'bi-box-arrow-up-right', false, false) ?>
                        <p class="small text-muted mb-0"><?= e(__('documents.complete_hint')) ?></p>
                    <?php endif; ?>
                    <?php if ($status === 'needs_info'): ?><p class="small text-muted mb-0"><?= e(__('documents.waiting_student')) ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
