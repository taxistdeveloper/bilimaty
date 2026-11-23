<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $issued */
/** @var string $q */
?>
<?= $this->partial('partials/page-header', ['heading' => __('transcripts.title'), 'subtitle' => __('transcripts.subtitle')]) ?>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e(__('transcripts.how_to_issue')) ?></div>

<div class="card">
    <form method="get" action="<?= e(url('/transcripts')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <label class="form-label small text-muted mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('transcripts.search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i><?= e(__('common.search')) ?></button></div>
    </form>
    <?php if ($issued === []): ?>
        <div class="app-empty"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><p class="mb-0"><?= e(__('transcripts.empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light"><tr><th scope="col"><?= e(__('transcripts.number')) ?></th><th scope="col"><?= e(__('journal.student')) ?></th><th scope="col"><?= e(__('fields.group_id')) ?></th><th scope="col"><?= e(__('transcripts.issued_at')) ?></th><th scope="col"><?= e(__('common.status')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($issued as $t): ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?= e(url('/transcripts/' . $t['id'])) ?>" class="fw-semibold text-decoration-none" target="_blank" rel="noopener"><?= e($t['number']) ?></a></td>
                        <td><a href="<?= e(url('/students/' . $t['student_id'] . '/transcript')) ?>" class="text-decoration-none"><?= e(full_name($t, true)) ?></a><div class="small text-muted"><?= e($t['student_number']) ?></div></td>
                        <td class="text-nowrap"><?= e($t['group_code'] ?? '—') ?></td>
                        <td class="small"><?= e(format_date($t['issued_at'], 'd.m.Y H:i')) ?></td>
                        <td><span class="badge <?= $t['status'] === 'issued' ? 'app-badge-success' : 'app-badge-danger' ?>"><?= e(__('transcripts.statuses.' . $t['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
