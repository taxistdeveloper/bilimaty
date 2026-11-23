<?php
/** @var App\Core\View $this */
/** @var list<array{journal: array<string, mixed>, result: array<string, mixed>, scheme: ?array}> $items */
?>
<?= $this->partial('partials/page-header', ['heading' => __('journal.my_title'), 'subtitle' => __('journal.my_subtitle')]) ?>

<?php if ($items === []): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-journal" aria-hidden="true"></i><p class="mb-0"><?= e(__('journal.my_empty')) ?></p></div></div>
<?php endif; ?>
<?= $this->partial('grades/_results', ['items' => $items, 'linkBase' => '/grades']) ?>
