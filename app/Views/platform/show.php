<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $college */
/** @var string $state */
/** @var array<string, mixed> $stats */
/** @var list<array<string, mixed>> $invoices */
/** @var list<array<string, mixed>> $audit */
/** @var array{login: string, password: string}|null $tempPassword */
/** @var string $url */
/** @var list<array{name: string, size: int, time: int}> $backups */
$c = $college;
$archived = $c['status'] === 'archived';
$dbDeleted = $c['db_deleted_at'] !== null;
$money = static fn (float|string $v): string => number_format((float) $v, 2, ',', ' ') . ' ₸';
$invoiceClass = ['issued' => 'app-badge-warning', 'paid' => 'app-badge-success', 'cancelled' => 'text-bg-light'];
$today = date('Y-m-d');
$suspended = $c['status'] === 'suspended';
?>
<?= $this->partial('partials/page-header', [
    'heading' => $c['name'],
    'subtitle' => '/' . $c['code'] . ($c['city'] ? ' · ' . $c['city'] : ''),
    'back' => '/',
    'actions' => $dbDeleted ? '' : ('<a href="' . e(url('/colleges/' . $c['id'] . '/users')) . '" class="btn btn-outline-secondary"><i class="bi bi-people me-1" aria-hidden="true"></i>' . e(__('platform.users_title')) . '</a>'
        . (!$archived && $c['status'] !== 'suspended' ? '<form method="post" action="' . e(url('/colleges/' . $c['id'] . '/impersonate')) . '" target="_blank" data-confirm="' . e(__('platform.impersonate_confirm')) . '">' . csrf_field()
            . '<button type="submit" class="btn btn-outline-dark"><i class="bi bi-incognito me-1" aria-hidden="true"></i>' . e(__('platform.impersonate')) . '</button></form>' : '')
        . '<a href="' . e(url('/colleges/' . $c['id'] . '/edit')) . '" class="btn btn-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . e(__('common.edit')) . '</a>'),
]) ?>
<?= $this->partial('partials/temp-password', ['tempPassword' => $tempPassword]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold"><?= e(__('platform.section_subscription')) ?></span><?= $this->partial('platform/_state', ['state' => $state]) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.paid_until')) ?></span><strong><?= e(format_date($c['paid_until'])) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.readonly_from')) ?></span><span><?= e(App\Core\Tenant::graceEnd($c) !== null ? format_date(date('Y-m-d', strtotime(App\Core\Tenant::graceEnd($c) . ' +1 day'))) : '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.plan')) ?></span><span class="text-end"><?= e($c['plan_name'] ?? __('platform.no_plan')) ?><?php if ($c['max_students'] !== null): ?><span class="d-block text-muted"><?= e(__('platform.up_to_students', ['n' => $c['max_students']])) ?></span><?php endif; ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.monthly_price')) ?></span><span><?= e($money($c['monthly_price'])) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.admin_login')) ?></span><span><?= e($c['admin_login'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.address')) ?></span><span class="text-break text-end"><?= e($url) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.contact')) ?></span><span class="text-end"><?= e(trim(($c['contact_name'] ?? '') . ' ' . ($c['contact_phone'] ?? '')) ?: '—') ?></span></li>
            </ul>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php if ($c['admin_login']): ?>
                    <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/admin-password')) ?>" data-confirm="<?= e(__('platform.admin_password_confirm', ['login' => $c['admin_login']])) ?>"><?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-key me-1" aria-hidden="true"></i><?= e(__('platform.admin_password')) ?></button></form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/status')) ?>" data-confirm="<?= e(__($suspended ? 'platform.resume_confirm' : 'platform.suspend_confirm')) ?>"<?= $suspended ? '' : ' data-confirm-danger' ?>><?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $suspended ? 'active' : 'suspended' ?>">
                    <button type="submit" class="btn btn-sm <?= $suspended ? 'btn-success' : 'btn-outline-danger' ?>"><?= e(__($suspended ? 'platform.resume' : 'platform.suspend')) ?></button></form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('platform.usage')) ?></div>
            <?php if ($stats['error'] !== null): ?>
                <div class="card-body small text-danger"><?= e(__('platform.db_unavailable')) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach (['students', 'teachers', 'groups', 'users', 'active_users_30', 'logins_30', 'grades_30', 'documents_30'] as $k): ?>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.' . $k)) ?></span><span><?= e($stats[$k]) ?></span></li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.last_login')) ?></span><span><?= e($stats['last_login'] ? format_date($stats['last_login'], 'd.m.Y H:i') : '—') ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted"><?= e(__('platform.size')) ?></span><span><?= e($stats['size_mb']) ?> MB</span></li>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('platform.backups')) ?></div>
            <?php if ($backups === []): ?>
                <div class="card-body small text-muted pb-0"><?= e(__('platform.no_backups')) ?></div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach (array_slice($backups, 0, 5) as $b): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <a href="<?= e(url('/colleges/' . $c['id'] . '/backups/' . substr($b['name'], 0, -7) . '/download')) ?>" class="text-decoration-none text-break"><i class="bi bi-download me-1" aria-hidden="true"></i><?= e(date('d.m.Y H:i', $b['time'])) ?></a>
                            <span class="text-muted text-nowrap"><?= e(number_format($b['size'] / 1024, 0, ',', ' ')) ?> KB</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php if (!$dbDeleted): ?>
                    <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/backups')) ?>"><?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary" data-loading-text="<?= e(__('common.loading')) ?>"><i class="bi bi-database-down me-1" aria-hidden="true"></i><?= e(__('platform.backup_create')) ?></button></form>
                <?php endif; ?>
                <?php if (!$dbDeleted): ?>
                    <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/archive')) ?>" data-confirm="<?= e(__($archived ? 'platform.restore_confirm' : 'platform.archive_confirm')) ?>"<?= $archived ? '' : ' data-confirm-danger' ?>><?= csrf_field() ?>
                        <input type="hidden" name="archive" value="<?= $archived ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm <?= $archived ? 'btn-success' : 'btn-outline-secondary' ?>"><i class="bi bi-archive me-1" aria-hidden="true"></i><?= e(__($archived ? 'platform.restore' : 'platform.archive_do')) ?></button></form>
                <?php endif; ?>
            </div>
            <?php if ($archived && !$dbDeleted): ?>
                <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/delete-database')) ?>" class="card-body border-top" data-confirm="<?= e(__('platform.delete_db_confirm')) ?>" data-confirm-danger>
                    <?= csrf_field() ?>
                    <p class="small text-danger mb-2"><?= e(__('platform.delete_db_hint', ['code' => $c['code']])) ?></p>
                    <div class="input-group input-group-sm">
                        <label class="visually-hidden" for="confirm"><?= e(__('platform.code')) ?></label>
                        <input type="text" class="form-control" id="confirm" name="confirm" placeholder="<?= e($c['code']) ?>" autocomplete="off" required>
                        <button type="submit" class="btn btn-danger"><?= e(__('platform.delete_db')) ?></button>
                    </div>
                </form>
            <?php elseif ($dbDeleted): ?>
                <div class="card-body border-top small text-danger"><?= e(__('platform.db_deleted_at', ['date' => format_date((string) $c['db_deleted_at'], 'd.m.Y H:i')])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('platform.invoices')) ?></div>
            <?php if ($invoices === []): ?>
                <div class="card-body small text-muted"><?= e(__('platform.no_invoices')) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 app-table small">
                        <thead class="table-light"><tr>
                            <th scope="col"><?= e(__('platform.invoice')) ?></th><th scope="col"><?= e(__('platform.months')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('platform.amount')) ?></th><th scope="col"><?= e(__('common.status')) ?></th><th scope="col"></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($invoices as $i): ?>
                            <tr>
                                <td><a href="<?= e(url('/invoices/' . $i['id'])) ?>" class="fw-semibold text-decoration-none text-nowrap"><?= e($i['number']) ?></a><div class="text-muted"><?= e(format_date($i['issued_on'])) ?></div></td>
                                <td><?= e($i['months']) ?><?php if ($i['period_from']): ?><div class="text-muted text-nowrap"><?= e(format_date($i['period_from'])) ?>–<?= e(format_date($i['period_to'])) ?></div><?php endif; ?></td>
                                <td class="text-end text-nowrap"><?= e($money($i['amount'])) ?></td>
                                <td><span class="badge <?= e($invoiceClass[$i['status']] ?? '') ?>"><?= e(__('platform.invoice_statuses.' . $i['status'])) ?></span>
                                    <?php if ($i['paid_on']): ?><div class="text-muted"><?= e(format_date($i['paid_on'])) ?> · <?= e(__('platform.methods.' . $i['method'])) ?></div><?php endif; ?></td>
                                <td class="text-end">
                                    <?php if ($i['status'] === 'issued'): ?>
                                        <form method="post" action="<?= e(url('/invoices/' . $i['id'] . '/pay')) ?>" class="d-flex gap-1 justify-content-end flex-wrap">
                                            <?= csrf_field() ?>
                                            <label class="visually-hidden" for="pd<?= e($i['id']) ?>"><?= e(__('platform.paid_on')) ?></label>
                                            <input type="date" class="form-control form-control-sm" style="max-width: 150px" id="pd<?= e($i['id']) ?>" name="paid_on" value="<?= e($today) ?>" required>
                                            <label class="visually-hidden" for="pm<?= e($i['id']) ?>"><?= e(__('platform.method')) ?></label>
                                            <select class="form-select form-select-sm" style="max-width: 130px" id="pm<?= e($i['id']) ?>" name="method">
                                                <?php foreach (App\Platform\PlatformService::METHODS as $m): ?><option value="<?= e($m) ?>"><?= e(__('platform.methods.' . $m)) ?></option><?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-success"><?= e(__('platform.mark_paid')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><?= e(__('platform.new_invoice')) ?></div>
            <form method="post" action="<?= e(url('/colleges/' . $c['id'] . '/invoices')) ?>" novalidate class="card-body row">
                <?= csrf_field() ?>
                <div class="col-md-3"><?= $this->partial('partials/field', ['name' => 'months', 'label' => __('platform.months'), 'type' => 'number', 'value' => '12', 'required' => true, 'attrs' => ['min' => 1, 'max' => 36]]) ?></div>
                <div class="col-md-4"><?= $this->partial('partials/field', ['name' => 'amount', 'label' => __('platform.amount'), 'help' => __('platform.amount_hint', ['price' => $money($c['monthly_price'])]), 'attrs' => ['inputmode' => 'decimal']]) ?></div>
                <div class="col-md-5"><?= $this->partial('partials/field', ['name' => 'issued_on', 'label' => __('platform.issued_on'), 'type' => 'date', 'value' => $today, 'required' => true]) ?></div>
                <div class="col-12"><?= $this->partial('partials/field', ['name' => 'note', 'label' => __('fields.note'), 'attrs' => ['maxlength' => 500]]) ?></div>
                <div class="col-12"><button type="submit" class="btn btn-primary"><?= e(__('platform.issue_invoice')) ?></button>
                    <span class="small text-muted ms-2"><?= e(__('platform.invoice_flow')) ?></span></div>
            </form>
        </div>
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('platform.history')) ?></div>
            <?php if ($audit === []): ?>
                <div class="card-body small text-muted">—</div>
            <?php else: ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($audit as $a): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2"><span><?= e(__('platform.audit.' . str_replace('.', '_', $a['action']))) ?></span>
                            <span class="text-muted text-nowrap"><?= e(format_date($a['created_at'], 'd.m.Y H:i')) ?> · <?= e($a['admin_name'] ?? '—') ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
