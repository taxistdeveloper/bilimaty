<?php
/** @var App\Core\View $this */
/** @var string $type */
/** @var array{created: list<array<string, string>>, failed: list<array{line: int, errors: list<string>}>} $result */
?>
<?= $this->partial('partials/page-header', ['heading' => __('import.result'), 'subtitle' => __('import.title_' . $type), 'back' => '/' . $type]) ?>

<div class="alert <?= $result['failed'] === [] ? 'alert-success' : 'alert-warning' ?>">
    <?= e(__('import.created_n', ['n' => count($result['created'])])) ?><?= $result['failed'] !== [] ? ' ' . e(__('import.failed_n', ['n' => count($result['failed'])])) : '' ?>
</div>

<?php if ($result['created'] !== []): ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('import.credentials')) ?></span>
            <form method="post" action="<?= e(url('/import/' . $type . '/result/download')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('import.download_credentials')) ?></button>
            </form>
        </div>
        <div class="card-body small text-muted pb-0"><?= e(__('import.credentials_hint')) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 app-table">
                <thead class="table-light"><tr><th><?= e(__('users.name')) ?></th><th><?= e(__($type === 'students' ? 'fields.group_id' : 'fields.department_id')) ?></th><th><?= e(__('fields.login')) ?></th><th><?= e(__('import.temp_password')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($result['created'] as $c): ?>
                    <tr><td><?= e($c['name']) ?></td><td><?= e($c['extra']) ?></td><td class="font-monospace"><?= e($c['login']) ?></td><td class="font-monospace"><?= e($c['password']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($result['failed'] !== []): ?>
    <div class="card">
        <div class="card-header fw-semibold text-danger"><?= e(__('import.not_imported')) ?></div>
        <ul class="list-group list-group-flush small">
            <?php foreach ($result['failed'] as $f): ?><li class="list-group-item"><?= e(__('import.line', ['n' => $f['line']])) ?>: <?= e(implode(' ', $f['errors'])) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
