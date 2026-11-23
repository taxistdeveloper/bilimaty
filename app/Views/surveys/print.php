<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $survey */
/** @var array<string, mixed> $results */
/** @var array<string, mixed> $compare */
$college = App\Services\Settings::collegeName();
?>
<style>.bar { display: inline-block; height: 8px; background: #2563eb; border-radius: 2px; vertical-align: middle; } .q { break-inside: avoid; margin-bottom: 5mm; }</style>
<header class="doc-header">
    <?php if ($college !== ''): ?><div class="doc-org"><?= e($college) ?></div><?php endif; ?>
    <h1><?= e(__('surveys.results')) ?>: <?= e(localized($survey, 'title')) ?></h1>
    <div class="doc-meta">
        <?= e(format_date((string) $survey['starts_on'])) ?> — <?= e(format_date((string) $survey['ends_on'])) ?> ·
        <?= e(__((bool) $survey['is_anonymous'] ? 'surveys.anonymous' : 'surveys.named')) ?> ·
        <?= e(__('surveys.participants')) ?>: <?= e($results['responses']) ?> / <?= e($results['audience']) ?> (<?= e($results['rate'] ?? '—') ?>%)
    </div>
</header>
<?php foreach ($results['questions'] as $i => $q): ?>
    <div class="q">
        <h2><?= e(($i + 1) . '. ' . localized($q, 'text')) ?></h2>
        <?php if ($q['type'] === 'text'): ?>
            <ul><?php foreach (array_slice($q['texts'], 0, 50) as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
        <?php else:
            $rows = $q['type'] === 'scale'
                ? array_map(static fn ($v, $n) => [(string) $v, $n], array_keys($q['distribution']), $q['distribution'])
                : array_map(static fn (array $o) => [localized($o, 'label'), (int) $o['count']], $q['options']);
            $base = max(1, (int) $q['answered']); ?>
            <table>
                <tbody>
                <?php foreach ($rows as [$label, $n]): $pct = round($n / $base * 100, 1); ?>
                    <tr><td style="width: 45%"><?= e($label) ?></td><td style="width: 10%; text-align: right"><?= e($n) ?></td><td style="width: 10%; text-align: right"><?= e($pct) ?>%</td><td><span class="bar" style="width: <?= e(min(100, $pct)) ?>%"></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($q['type'] === 'scale'): ?><p><?= e(__('surveys.average')) ?>: <strong><?= e($q['average'] ?? '—') ?></strong></p><?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php $scale = array_values(array_filter($compare['questions'], static fn (array $q): bool => $q['type'] === 'scale')); ?>
<?php if (count($compare['segments']) >= 2 && $scale !== []): ?>
    <h2><?= e(__('surveys.compare_averages')) ?> (<?= e(__('surveys.segments.' . $compare['column'])) ?>)</h2>
    <table>
        <thead><tr><th><?= e(__('surveys.segments.' . $compare['column'])) ?></th><th>n</th><?php foreach ($scale as $q): ?><th><?= e(mb_strimwidth(localized($q, 'text'), 0, 40, '…')) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($compare['segments'] as $k => $s): ?>
            <tr><td><?= e($s['label']) ?></td><td><?= e($s['n']) ?></td><?php foreach ($scale as $q): ?><td><?= e($q['rows'][$k]['average'] ?? '—') ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
