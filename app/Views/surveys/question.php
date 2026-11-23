<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $survey */
/** @var array<string, mixed> $q */
?>
<?= $this->partial('partials/page-header', ['heading' => __('surveys.edit_question'), 'subtitle' => localized($survey, 'title'), 'back' => '/surveys/' . $survey['id'] . '/edit#questions']) ?>
<?php if (error('status')): ?><div class="alert alert-danger small"><?= e(error('status')) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/questions/' . $q['id'])) ?>" class="card" novalidate>
    <?= csrf_field() ?><?= method_field('PUT') ?>
    <div class="card-body">
        <?= $this->partial('surveys/_question_fields', ['q' => $q]) ?>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </div>
</form>
<?php $this->start('scripts'); ?>
<script src="<?= e(asset('assets/js/surveys.js')) ?>"></script>
<?php $this->stop(); ?>
