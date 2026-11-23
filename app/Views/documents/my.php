<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $requests */
/** @var list<array<string, mixed>> $types */
$icons = ['study_certificate' => 'bi-file-earmark-person', 'transcript' => 'bi-file-earmark-text', 'personal_data' => 'bi-person-vcard', 'none' => 'bi-envelope'];
?>
<?= $this->partial('partials/page-header', ['heading' => __('documents.my_title'), 'subtitle' => __('documents.my_subtitle')]) ?>

<h2 class="h6 fw-bold mb-2"><?= e(__('documents.new_request')) ?></h2>
<div class="row g-3 mb-4">
    <?php foreach ($types as $type): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <a href="<?= e(url('/documents/create?type=' . $type['id'])) ?>" class="card h-100 text-decoration-none text-reset app-card-link">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1"><i class="bi <?= e($icons[$type['generator']] ?? 'bi-envelope') ?> text-primary fs-5" aria-hidden="true"></i><span class="fw-semibold"><?= e(localized($type)) ?></span></div>
                    <?php if (localized(['name_ru' => $type['description_ru'], 'name_kk' => $type['description_kk']]) !== ''): ?><p class="small text-muted mb-0"><?= e(localized(['name_ru' => $type['description_ru'], 'name_kk' => $type['description_kk']])) ?></p><?php endif; ?>
                    <?php if ($type['processing_days'] !== null): ?><div class="small text-muted mt-1"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= e(__('documents.term_days', ['days' => $type['processing_days']])) ?></div><?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h6 fw-bold mb-2"><?= e(__('documents.my_requests')) ?></h2>
<?php if ($requests === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-envelope-paper" aria-hidden="true"></i><p class="mb-0"><?= e(__('documents.empty_my')) ?></p></div></div>
<?php else: ?>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($requests as $r): ?>
                <a href="<?= e(url('/documents/' . $r['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div class="min-w-0">
                        <div class="fw-semibold"><?= e(localized(['name_ru' => $r['type_name_ru'], 'name_kk' => $r['type_name_kk']])) ?></div>
                        <div class="small text-muted"><?= e($r['number'] ?? __('documents.no_number')) ?> · <?= e(format_date((string) ($r['submitted_at'] ?? $r['created_at']), 'd.m.Y')) ?></div>
                    </div>
                    <?= $this->partial('documents/_status', ['status' => $r['status']]) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
