<?php
/** @var App\Core\View $this */
/** @var list<array{name: string, icon: string, count: int}> $cards */
?>
<?= $this->partial('partials/page-header', ['heading' => __('nav.dictionaries'), 'subtitle' => __('dictionaries.hub_subtitle')]) ?>

<div class="row g-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="<?= e(url('/dictionaries/' . $card['name'])) ?>" class="card app-link-card h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="app-stat__icon app-stat__icon--primary"><i class="bi <?= e($card['icon']) ?>" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-body"><?= e(__('dictionaries.' . $card['name'] . '.title')) ?></div>
                        <div class="small text-muted"><?= e(__('common.total', ['count' => $card['count']])) ?></div>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted" aria-hidden="true"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
