<?php
/** @var App\Core\View $this */
/** @var array<string, mixed> $survey */
/** @var array{responses: int, audience: int, rate: ?float, questions: list<array<string, mixed>>} $results */
/** @var array{column: string, segments: list<array<string, mixed>>, hidden: int, questions: list<array<string, mixed>>} $compare */
/** @var list<array<string, mixed>> $participants */
$anonymous = (bool) $survey['is_anonymous'];
$charts = [];
$by = $compare['column'];
$segmentOptions = ['respondent_role' => __('surveys.segments.respondent_role'), 'department_id' => __('surveys.segments.department_id'), 'course' => __('surveys.segments.course')];
if (!$anonymous) {
    $segmentOptions['group_id'] = __('surveys.segments.group_id');
}
$query = '?by=' . rawurlencode($by);
?>
<?= $this->partial('partials/page-header', [
    'heading' => localized($survey, 'title'),
    'subtitle' => __('surveys.results') . ' · ' . format_date((string) $survey['starts_on']) . ' — ' . format_date((string) $survey['ends_on']) . ' · ' . __('surveys.statuses.' . $survey['status']),
    'back' => can('surveys.manage') ? '/surveys/' . $survey['id'] . '/edit' : '/surveys',
    'actions' => '<a href="' . e(url('/surveys/' . $survey['id'] . '/results/print' . $query)) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . e(__('workload.print')) . '</a>'
        . '<a href="' . e(url('/surveys/' . $survey['id'] . '/results/export')) . '" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>' . e(__('surveys.export')) . '</a>',
]) ?>

<div class="row g-3 mb-3">
    <?php foreach ([['surveys.participants', $results['responses'], 'bi-people'], ['surveys.audience_size', $results['audience'], 'bi-person-lines-fill'], ['surveys.rate', $results['rate'] !== null ? $results['rate'] . '%' : '—', 'bi-pie-chart']] as [$label, $value, $icon]): ?>
        <div class="col-4"><div class="card h-100"><div class="card-body">
            <div class="small text-muted"><i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e(__($label)) ?></div>
            <div class="h4 fw-bold mb-0"><?= e($value) ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<p class="small text-muted"><i class="bi <?= $anonymous ? 'bi-incognito' : 'bi-person' ?> me-1" aria-hidden="true"></i><?= e(__($anonymous ? 'surveys.anonymous_results_hint' : 'surveys.named_results_hint', ['min' => App\Services\SurveyService::MIN_SEGMENT])) ?></p>

<?php if ($results['responses'] === 0): ?>
    <div class="card"><div class="app-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><p class="mb-0"><?= e(__('surveys.no_responses')) ?></p></div></div>
<?php else: ?>
    <h2 class="h6 fw-bold mb-2"><?= e(__('surveys.distribution')) ?></h2>
    <?php foreach ($results['questions'] as $i => $q): $canvas = 'qc' . $q['id']; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between gap-2 flex-wrap">
                <span class="fw-semibold"><?= e(($i + 1) . '. ' . localized($q, 'text')) ?></span>
                <span class="small text-muted"><?= e(__('surveys.types.' . $q['type'])) ?> · <?= e(__('surveys.answered_n', ['n' => $q['answered']])) ?></span>
            </div>
            <div class="card-body">
                <?php if ($q['type'] === 'text'): ?>
                    <?php if ($q['texts'] === []): ?><p class="small text-muted mb-0"><?= e(__('surveys.no_answers')) ?></p><?php endif; ?>
                    <ul class="small mb-0 app-survey-texts">
                        <?php foreach ($q['texts'] as $t): ?><li style="white-space: pre-line"><?= e($t) ?></li><?php endforeach; ?>
                    </ul>
                <?php else:
                    if ($q['type'] === 'scale') {
                        $labels = array_map('strval', array_keys($q['distribution']));
                        $values = array_values($q['distribution']);
                    } else {
                        $labels = array_map(static fn (array $o): string => localized($o, 'label'), $q['options']);
                        $values = array_map(static fn (array $o): int => (int) $o['count'], $q['options']);
                    }
                    $charts[$canvas] = ['labels' => $labels, 'values' => $values, 'horizontal' => $q['type'] !== 'scale'];
                    ?>
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-7"><div class="app-chart" style="height: <?= e(max(180, min(420, count($labels) * 38))) ?>px"><canvas id="<?= e($canvas) ?>" role="img" aria-label="<?= e(localized($q, 'text')) ?>"></canvas></div></div>
                        <div class="col-12 col-lg-5">
                            <?php if ($q['type'] === 'scale'): ?>
                                <div class="mb-2"><span class="text-muted small"><?= e(__('surveys.average')) ?>:</span> <span class="h5 fw-bold"><?= e($q['average'] ?? '—') ?></span> <span class="small text-muted">(<?= e($q['scale_min']) ?>–<?= e($q['scale_max']) ?>)</span></div>
                            <?php endif; ?>
                            <table class="table table-sm mb-0">
                                <tbody>
                                <?php foreach ($labels as $k => $label): $n = $values[$k]; $base = $q['answered'] > 0 ? $q['answered'] : 1; ?>
                                    <tr><td><?= e($label) ?></td><td class="text-end"><?= e($n) ?></td><td class="text-end text-muted"><?= e(round($n / $base * 100, 1)) ?>%</td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($q['type'] === 'multiple'): ?><div class="form-text"><?= e(__('surveys.multiple_hint')) ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <h2 class="h6 fw-bold mb-2 mt-4" id="compare"><?= e(__('surveys.compare')) ?></h2>
    <form method="get" action="<?= e(url('/surveys/' . $survey['id'] . '/results')) ?>#compare" class="row g-2 align-items-end mb-3">
        <div class="col-8 col-md-4"><?= $this->partial('partials/filter-select', ['name' => 'by', 'label' => __('surveys.compare_by'), 'items' => $segmentOptions, 'current' => $by, 'all' => false]) ?></div>
        <div class="col-4 col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><?= e(__('common.show')) ?></button></div>
    </form>
    <?php if ($compare['hidden'] > 0): ?><p class="small text-muted"><?= e(__('surveys.hidden_segments', ['n' => $compare['hidden'], 'min' => App\Services\SurveyService::MIN_SEGMENT])) ?></p><?php endif; ?>
    <?php if (count($compare['segments']) < 2): ?>
        <div class="alert alert-light border small"><?= e(__('surveys.compare_not_enough')) ?></div>
    <?php else:
        $scaleQuestions = array_values(array_filter($compare['questions'], static fn (array $q): bool => $q['type'] === 'scale'));
        if ($scaleQuestions !== []) {
            $charts['compareChart'] = ['grouped' => true, 'labels' => array_map(static fn (array $q): string => mb_strimwidth(localized($q, 'text'), 0, 40, '…'), $scaleQuestions),
                'series' => array_map(static fn (array $s, int $k) => ['label' => $s['label'], 'values' => array_map(static fn (array $q) => $q['rows'][$k]['average'], $scaleQuestions)], $compare['segments'], array_keys($compare['segments']))];
        } ?>
        <?php if ($scaleQuestions !== []): ?>
            <div class="card mb-3"><div class="card-header fw-semibold"><?= e(__('surveys.compare_averages')) ?></div><div class="card-body"><div class="app-chart" style="height: 300px"><canvas id="compareChart" role="img" aria-label="<?= e(__('surveys.compare_averages')) ?>"></canvas></div></div></div>
        <?php endif; ?>
        <?php foreach ($compare['questions'] as $q): ?>
            <div class="card mb-3">
                <div class="card-header small fw-semibold"><?= e(localized($q, 'text')) ?></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th><?= e($segmentOptions[$by]) ?></th><th class="text-end"><?= e(__('surveys.participants')) ?></th>
                            <?php if ($q['type'] === 'scale'): ?><th class="text-end"><?= e(__('surveys.average')) ?></th>
                            <?php else: foreach ($q['options'] as $o): ?><th class="text-end"><?= e(localized($o, 'label')) ?>, %</th><?php endforeach; endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($compare['segments'] as $k => $s): $row = $q['rows'][$k]; ?>
                            <tr><td><?= e($s['label']) ?></td><td class="text-end"><?= e($s['n']) ?></td>
                                <?php if ($q['type'] === 'scale'): ?><td class="text-end fw-semibold"><?= e($row['average'] ?? '—') ?></td>
                                <?php else: foreach ($row['percents'] as $pct): ?><td class="text-end"><?= e($pct) ?></td><?php endforeach; endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$anonymous && $participants !== []): ?>
        <h2 class="h6 fw-bold mb-2 mt-4"><?= e(__('surveys.who_answered')) ?></h2>
        <div class="card"><ul class="list-group list-group-flush small">
            <?php foreach ($participants as $p): ?>
                <li class="list-group-item d-flex justify-content-between gap-2"><span><?= e(full_name($p)) ?> <span class="text-muted">· <?= e(__('surveys.roles.' . $p['respondent_role'])) ?><?= $p['group_code'] !== null ? ' · ' . e($p['group_code']) : '' ?></span></span><span class="text-muted"><?= e(format_date((string) $p['submitted_at'], 'd.m.Y H:i')) ?></span></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
<?php endif; ?>

<script type="application/json" id="surveyCharts"><?= json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php $this->start('scripts'); ?>
<script src="<?= e(asset('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('assets/js/surveys.js')) ?>"></script>
<?php $this->stop(); ?>
