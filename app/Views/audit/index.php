<?php
/** @var App\Core\View $this */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
/** @var array{action: string, user: string, from: string, to: string} $filters */
/** @var list<string> $actions */
$label = static function (string $action): string {
    $key = 'audit_actions.' . str_replace('.', '_', $action);
    $text = __($key);

    return $text === $key ? $action : $text;
};
$danger = ['auth.login_failed', 'auth.login_locked', 'users.deactivated', 'roles.deleted', 'profile.password_change_failed'];
$pretty = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '—';
    }
    $decoded = json_decode($json, true);

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $json;
};
?>
<?= $this->partial('partials/page-header', ['heading' => __('audit.title')]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/audit')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label small text-muted mb-1" for="action"><?= e(__('audit.action')) ?></label>
            <select class="form-select" id="action" name="action">
                <option value=""><?= e(__('audit.all_actions')) ?></option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= e($label($action)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label small text-muted mb-1" for="user"><?= e(__('audit.user')) ?></label>
            <input type="search" class="form-control" id="user" name="user" value="<?= e($filters['user']) ?>" placeholder="<?= e(__('audit.user_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small text-muted mb-1" for="from"><?= e(__('audit.date_from')) ?></label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small text-muted mb-1" for="to"><?= e(__('audit.date_to')) ?></label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/audit')) ?>" class="btn btn-light" aria-label="<?= e(__('common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
        </div>
    </form>

    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-shield-check" aria-hidden="true"></i><p class="mb-0"><?= e(__('audit.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('audit.when')) ?></th>
                    <th scope="col"><?= e(__('audit.action')) ?></th>
                    <th scope="col"><?= e(__('audit.user')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('audit.object')) ?></th>
                    <th scope="col" class="d-none d-lg-table-cell"><?= e(__('audit.ip')) ?></th>
                    <th scope="col" class="text-end"><span class="visually-hidden"><?= e(__('audit.details')) ?></span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <?php $hasDetails = $row['old_values'] !== null || $row['new_values'] !== null; ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(format_date($row['created_at'], 'd.m.Y H:i:s')) ?></td>
                        <td>
                            <span class="app-dot <?= in_array($row['action'], $danger, true) ? 'app-dot--danger' : 'app-dot--success' ?>" aria-hidden="true"></span>
                            <?= e($label((string) $row['action'])) ?>
                        </td>
                        <td class="small">
                            <?= e($row['login'] !== null ? full_name($row, true) : __('audit.system')) ?>
                            <?php if ($row['login'] !== null): ?><div class="text-muted"><?= e($row['login']) ?></div><?php endif; ?>
                        </td>
                        <td class="small text-muted d-none d-md-table-cell"><?= e($row['entity_type'] ? $row['entity_type'] . ($row['entity_id'] ? ' #' . $row['entity_id'] : '') : '—') ?></td>
                        <td class="small text-muted d-none d-lg-table-cell"><?= e($row['ip_address'] ?? '—') ?></td>
                        <td class="text-end">
                            <?php if ($hasDetails): ?>
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#audit<?= e($row['id']) ?>"
                                        aria-expanded="false" aria-controls="audit<?= e($row['id']) ?>" aria-label="<?= e(__('audit.details')) ?>">
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($hasDetails): ?>
                        <tr class="collapse" id="audit<?= e($row['id']) ?>">
                            <td colspan="6" class="bg-body-tertiary">
                                <div class="row g-3 small">
                                    <div class="col-md-6"><div class="fw-semibold mb-1"><?= e(__('audit.old')) ?></div><pre class="app-json mb-0"><?= e($pretty($row['old_values'])) ?></pre></div>
                                    <div class="col-md-6"><div class="fw-semibold mb-1"><?= e(__('audit.new')) ?></div><pre class="app-json mb-0"><?= e($pretty($row['new_values'])) ?></pre></div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => $filters, 'path' => '/audit']) ?>
</div>
