<?php
/**
 * Parents of the student (student card): list, new parent account, link an existing one.
 *
 * @var App\Core\View $this
 * @var array<string, mixed> $student
 * @var list<array<string, mixed>> $guardians
 * @var bool $canManage
 */
use App\Services\GuardianService;

$guardianForm = (string) old('_guardian_form', '');
$hasErrors = errors() !== [];
$relations = [];
foreach (GuardianService::RELATIONS as $r) {
    $relations[$r] = __('guardians.relations.' . $r);
}
$full = count($guardians) >= GuardianService::MAX_PER_STUDENT;
?>
<div class="card mb-3" id="guardians">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-people me-1" aria-hidden="true"></i><?= e(__('guardians.title')) ?></span>
        <?php if ($canManage && !$full): ?>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#guardianNewModal" aria-label="<?= e(__('guardians.add')) ?>"><i class="bi bi-person-plus" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1"><?= e(__('guardians.add')) ?></span></button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#guardianLinkModal" aria-label="<?= e(__('guardians.link_existing')) ?>" title="<?= e(__('guardians.link_existing')) ?>"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($guardians === []): ?>
        <div class="card-body small text-muted"><?= e(__('guardians.none')) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($guardians as $g): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0 small">
                        <div class="fw-semibold"><?= e(full_name($g)) ?> <span class="badge text-bg-light"><?= e(__('guardians.relations.' . $g['relation'])) ?></span><?= (int) $g['is_active'] === 1 ? '' : ' <span class="badge app-badge-danger">' . e(__('common.inactive')) . '</span>' ?></div>
                        <div class="text-muted"><?= e(__('fields.login')) ?>: <span class="font-monospace"><?= e($g['login']) ?></span><?= $g['phone'] ? ' · ' . e($g['phone']) : '' ?><?= $g['email'] ? ' · ' . e($g['email']) : '' ?></div>
                        <div class="text-muted"><?= e(__('guardians.last_login')) ?>: <?= e($g['last_login_at'] !== null ? format_date((string) $g['last_login_at'], 'd.m.Y H:i') : __('guardians.never')) ?></div>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(url('/students/' . $student['id'] . '/guardians/' . $g['user_id'])) ?>" data-confirm="<?= e(__('guardians.unlink_confirm')) ?>">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('guardians.unlink')) ?>" title="<?= e(__('guardians.unlink')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="card-footer bg-transparent small text-muted"><?= e(__('guardians.hint')) ?></div>
</div>

<?php if ($canManage && !$full): ?>
    <div class="modal fade" id="guardianNewModal" tabindex="-1" aria-labelledby="guardianNewTitle" aria-hidden="true"<?= $hasErrors && $guardianForm === 'new' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog">
            <form method="post" action="<?= e(url('/students/' . $student['id'] . '/guardians')) ?>" class="modal-content" novalidate>
                <?= csrf_field() ?><input type="hidden" name="mode" value="new">
                <div class="modal-header"><h2 class="modal-title h5" id="guardianNewTitle"><?= e(__('guardians.add')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body">
                    <p class="small text-muted"><?= e(__('guardians.add_hint')) ?></p>
                    <?php $own = $guardianForm === 'new'; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'relation', 'label' => __('guardians.relation'), 'type' => 'select', 'required' => true, 'value' => 'mother', 'options' => $relations]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'last_name', 'label' => __('fields.last_name'), 'required' => true, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off']]) ?>
                    <div class="row g-2">
                        <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'first_name', 'label' => __('fields.first_name'), 'required' => true, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off']]) ?></div>
                        <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'middle_name', 'label' => __('fields.middle_name'), 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off']]) ?></div>
                        <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'phone', 'label' => __('fields.phone'), 'type' => 'tel', 'attrs' => ['maxlength' => 20, 'autocomplete' => 'off']]) ?></div>
                        <div class="col-6"><?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'email', 'label' => __('fields.email'), 'type' => 'email', 'attrs' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?></div>
                    </div>
                    <?= $this->partial('partials/field', ['idPrefix' => 'gn_', 'ownsInput' => $own, 'name' => 'locale', 'label' => __('fields.locale'), 'type' => 'select', 'required' => true, 'value' => 'kk', 'options' => ['kk' => 'Қазақша', 'ru' => 'Русский']]) ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('guardians.create_account')) ?></button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="guardianLinkModal" tabindex="-1" aria-labelledby="guardianLinkTitle" aria-hidden="true"<?= $hasErrors && $guardianForm === 'link' ? ' data-show-on-load' : '' ?>>
        <div class="modal-dialog">
            <form method="post" action="<?= e(url('/students/' . $student['id'] . '/guardians')) ?>" class="modal-content" novalidate>
                <?= csrf_field() ?><input type="hidden" name="mode" value="link">
                <div class="modal-header"><h2 class="modal-title h5" id="guardianLinkTitle"><?= e(__('guardians.link_existing')) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button></div>
                <div class="modal-body">
                    <p class="small text-muted"><?= e(__('guardians.link_hint')) ?></p>
                    <?php $own = $guardianForm === 'link'; ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'gl_', 'ownsInput' => $own, 'name' => 'parent_login', 'label' => __('guardians.parent_login'), 'required' => true, 'attrs' => ['maxlength' => 64, 'autocomplete' => 'off', 'spellcheck' => 'false']]) ?>
                    <?= $this->partial('partials/field', ['idPrefix' => 'gl_', 'ownsInput' => $own, 'name' => 'relation', 'label' => __('guardians.relation'), 'type' => 'select', 'required' => true, 'value' => 'father', 'options' => $relations]) ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button><button type="submit" class="btn btn-primary"><?= e(__('guardians.link')) ?></button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
