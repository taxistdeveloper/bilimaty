<?php /** Shared confirmation dialog used by forms with data-confirm="..." (see app.js). */ ?>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="confirmModalTitle"><?= e(__('common.confirm')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body" data-confirm-text></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="button" class="btn btn-primary" data-confirm-ok><?= e(__('common.yes')) ?></button>
            </div>
        </div>
    </div>
</div>
