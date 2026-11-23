<?php
/** @var App\Core\View $this */
/** @var string $type students|teachers */
/** @var array<string, list<string>> $columns field => accepted headings */
$required = $type === 'students' ? ['last_name', 'first_name', 'group'] : ['last_name', 'first_name'];
$fileError = error('file');
?>
<?= $this->partial('partials/page-header', ['heading' => __('import.title_' . $type), 'subtitle' => __('import.subtitle'), 'back' => '/' . $type]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <form method="post" action="<?= e(url('/import/' . $type)) ?>" enctype="multipart/form-data" class="card" novalidate>
            <?= csrf_field() ?>
            <div class="card-body">
                <ol class="small ps-3 mb-3">
                    <li><?= e(__('import.step1')) ?> <a href="<?= e(url('/import/' . $type . '/template')) ?>"><?= e(__('import.template')) ?></a>.</li>
                    <li><?= e(__('import.step2')) ?></li>
                    <li><?= e(__('import.step3')) ?></li>
                </ol>
                <label class="form-label fw-semibold" for="file"><?= e(__('import.file')) ?> <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="file" class="form-control<?= $fileError ? ' is-invalid' : '' ?>" id="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required aria-describedby="fileHelp<?= $fileError ? ' fileErr' : '' ?>">
                <div id="fileHelp" class="form-text"><?= e(__('import.file_hint', ['max' => App\Helpers\SpreadsheetReader::MAX_ROWS])) ?></div>
                <?php if ($fileError): ?><div id="fileErr" class="invalid-feedback d-block"><?= e($fileError) ?></div><?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <button type="submit" class="btn btn-primary" data-loading-text="<?= e(__('common.loading')) ?>"><i class="bi bi-search me-1" aria-hidden="true"></i><?= e(__('import.check')) ?></button>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(__('import.columns')) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small align-middle">
                    <thead class="table-light"><tr><th><?= e(__('import.heading')) ?></th><th><?= e(__('import.also')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($columns as $field => $names): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(App\Services\ImportService::heading($names[0])) ?><?= in_array($field, $required, true) ? ' <span class="text-danger">*</span>' : '' ?></td>
                            <td class="text-muted"><?= e(implode(', ', array_slice($names, 1))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent small text-muted"><?= e(__('import.values_hint_' . $type)) ?></div>
        </div>
    </div>
</div>
