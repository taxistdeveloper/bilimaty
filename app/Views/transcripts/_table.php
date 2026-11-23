<?php
/**
 * Transcript items grouped by year/semester (shared by preview and the issued document).
 *
 * @var list<array<string, mixed>> $items
 * @var bool $showLetter
 * @var string $tableClass
 */
use App\Services\JournalService;

$n = static fn ($v): string => JournalService::normalize($v) ?? '—';
$codes = [];
foreach ($items as $item) {
    foreach (array_keys((array) $item['components']) as $code) {
        if ($code !== 'current' && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
}
$order = ['rating1' => 1, 'rating2' => 2, 'exam' => 3];
usort($codes, static fn (string $a, string $b): int => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
$groups = [];
foreach ($items as $item) {
    $groups[str_replace('-', '–', (string) $item['academic_year']) . ' · ' . __('dashboard.semester', ['number' => $item['semester_number']])][] = $item;
}
?>
<?php foreach ($groups as $label => $list): ?>
    <h2 class="<?= e($tableClass === 'doc' ? '' : 'h6 fw-bold mt-3') ?>"><?= e($label) ?></h2>
    <?php if ($tableClass !== 'doc'): ?><div class="table-responsive"><?php endif; ?>
    <table class="<?= e($tableClass === 'doc' ? '' : 'table table-sm table-bordered align-middle app-table bg-white') ?>">
        <thead>
        <tr>
            <th><?= e(__('curricula.subject')) ?></th>
            <th><?= e(__('transcripts.hours')) ?></th>
            <th><?= e(__('curricula.credits')) ?></th>
            <?php foreach ($codes as $code): ?><th><?= e(__('transcripts.components.' . $code)) ?></th><?php endforeach; ?>
            <th><?= e(__('journal.final')) ?></th>
            <?php if ($showLetter): ?><th><?= e(__('transcripts.letter')) ?></th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $item): ?>
            <tr>
                <td><?= e($item['subject_code']) ?> <?= e(localized(['name_ru' => $item['subject_name_ru'], 'name_kk' => $item['subject_name_kk']])) ?></td>
                <td><?= e($item['hours'] ?? '—') ?><?= ($item['hours_source'] ?? null) === 'conducted' ? '*' : '' ?></td>
                <td><?= e($item['credits'] !== null ? $n($item['credits']) : '—') ?></td>
                <?php foreach ($codes as $code): ?><td><?= e($n($item['components'][$code] ?? null)) ?></td><?php endforeach; ?>
                <td><strong><?= e($n($item['final'])) ?></strong></td>
                <?php if ($showLetter): ?><td><?= e($item['letter'] ?? '—') ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($tableClass !== 'doc'): ?></div><?php endif; ?>
<?php endforeach; ?>
