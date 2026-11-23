<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $children */
?>
<?= $this->partial('partials/page-header', ['heading' => __('children.title'), 'subtitle' => __('children.subtitle')]) ?>

<?php if ($children === []): ?>
    <div class="card"><div class="app-empty">
        <img src="<?= e(asset('assets/images/mascot-tablet.png')) ?>" alt="" class="app-error-mascot mb-2" width="129" height="144">
        <p class="mb-0"><?= e(__('children.none')) ?></p>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($children as $c): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?= e(url('/children/' . $c['id'])) ?>" class="card h-100 text-decoration-none text-reset app-card-link">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <span class="app-avatar app-avatar--lg" aria-hidden="true"><?= e(mb_substr((string) $c['first_name'], 0, 1) . mb_substr((string) $c['last_name'], 0, 1)) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= e(full_name($c)) ?></div>
                            <div class="small text-muted"><?= e($c['group_code'] ?? '—') ?><?= $c['course'] !== null ? ' · ' . e(__('groups.course_n', ['n' => $c['course']])) : '' ?></div>
                            <div class="small text-muted text-truncate"><?= e(localized(['name_ru' => $c['specialty_name_ru'], 'name_kk' => $c['specialty_name_kk']])) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
