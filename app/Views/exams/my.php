<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $exams */
?>
<?= $this->partial('partials/page-header', ['heading' => __('exams.my_title'), 'subtitle' => __('exams.my_subtitle')]) ?>
<?= $this->partial('exams/_student_list', ['exams' => $exams]) ?>
