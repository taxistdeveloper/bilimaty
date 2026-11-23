<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $journal */
/** @var list<array<string, mixed>> $students */
/** @var int|null $studentId */
/** @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int} $result */
$display = static function (?string $value, string $field): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return match ($field) {
        'attendance' => __('journal.attendance_values.' . $value),
        default => $value,
    };
};
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('journal.history'),
    'subtitle' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]) . ' · ' . $journal['group_code'],
    'back' => '/journal/' . $journal['id'],
]) ?>

<div class="card">
    <form method="get" action="<?= e(url('/journal/' . $journal['id'] . '/history')) ?>" class="card-body border-bottom row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label small text-muted mb-1" for="student"><?= e(__('audit.user')) ?></label>
            <select class="form-select" id="student" name="student">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach ($students as $s): ?><option value="<?= e($s['id']) ?>"<?= $studentId === (int) $s['id'] ? ' selected' : '' ?>><?= e(full_name($s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('common.filter')) ?></button></div>
    </form>
    <?php if ($result['items'] === []): ?>
        <div class="app-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><p class="mb-0"><?= e(__('journal.history_empty')) ?></p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('audit.when')) ?></th>
                    <th scope="col"><?= e(__('journal.student')) ?></th>
                    <th scope="col"><?= e(__('journal.what')) ?></th>
                    <th scope="col"><?= e(__('audit.old')) ?> → <?= e(__('audit.new')) ?></th>
                    <th scope="col"><?= e(__('journal.author')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $h): ?>
                    <tr>
                        <td class="small text-nowrap"><?= e(format_date($h['changed_at'], 'd.m.Y H:i:s')) ?></td>
                        <td class="small"><?= e($h['last_name'] !== null ? full_name($h, true) : '—') ?></td>
                        <td class="small">
                            <?= e(__('journal.fields.' . $h['field'])) ?>
                            <?php if ($h['lesson_date'] !== null): ?><span class="text-muted">· <?= e(format_date($h['lesson_date'], 'd.m')) ?> (<?= e($h['period_number']) ?>)</span><?php endif; ?>
                            <?php if ($h['short_ru'] !== null): ?><span class="text-muted">· <?= e(localized($h, 'short')) ?></span><?php endif; ?>
                        </td>
                        <td class="small"><span class="text-muted"><?= e($display($h['old_value'], (string) $h['field'])) ?></span> → <strong><?= e($display($h['new_value'], (string) $h['field'])) ?></strong></td>
                        <td class="small"><?= e($h['author_last_name'] !== null ? full_name(['last_name' => $h['author_last_name'], 'first_name' => $h['author_first_name'], 'middle_name' => $h['author_middle_name']], true) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $this->partial('partials/pagination', ['result' => $result, 'filters' => ['student' => $studentId], 'path' => '/journal/' . $journal['id'] . '/history']) ?>
</div>
