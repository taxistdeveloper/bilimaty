<?php
/** @var App\Core\View $this */
/** @var list<array<string, mixed>> $forMe */
/** @var bool $respondent */
/** @var bool $staff */
/** @var string $status */
/** @var list<array<string, mixed>> $list */
$audience = static fn (array $s): string => __('surveys.audiences.' . $s['audience'])
    . ($s['group_code'] !== null ? ' · ' . $s['group_code'] : (($s['department_name_ru'] ?? null) !== null ? ' · ' . localized(['name_ru' => $s['department_name_ru'], 'name_kk' => $s['department_name_kk']]) : ''))
    . ($s['course'] !== null && $s['group_code'] === null ? ' · ' . __('groups.course_n', ['n' => $s['course']]) : '');
?>
<?= $this->partial('partials/page-header', [
    'heading' => __('surveys.title'),
    'subtitle' => __('surveys.subtitle'),
    'actions' => can('surveys.manage') ? '<a href="' . e(url('/surveys/create')) . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . e(__('surveys.create')) . '</a>' : '',
]) ?>

<?php if ($respondent): ?>
    <h2 class="h6 fw-bold mb-2"><?= e(__('surveys.for_me')) ?></h2>
    <?php if ($forMe === []): ?>
        <div class="card mb-4"><div class="app-empty"><i class="bi bi-ui-checks" aria-hidden="true"></i><p class="mb-0"><?= e(__('surveys.none_for_me')) ?></p></div></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($forMe as $s): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100"><div class="card-body d-flex flex-column">
                        <div class="fw-semibold mb-1"><?= e(localized($s, 'title')) ?></div>
                        <div class="small text-muted mb-2"><?= e(__('surveys.until', ['date' => format_date((string) $s['ends_on'])])) ?><?= (bool) $s['is_anonymous'] ? ' · ' . e(__('surveys.anonymous')) : '' ?></div>
                        <div class="mt-auto">
                            <?php if ((bool) $s['answered']): ?>
                                <span class="badge app-badge-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i><?= e(__('surveys.answered')) ?></span>
                            <?php else: ?>
                                <a href="<?= e(url('/surveys/' . $s['id'])) ?>" class="btn btn-primary btn-sm"><?= e(__('surveys.take')) ?></a>
                            <?php endif; ?>
                        </div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($staff): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 fw-bold mb-0"><?= e(__('surveys.all')) ?></h2>
        <ul class="nav nav-pills small">
            <?php foreach (['' => 'surveys.tab_all', 'draft' => 'surveys.statuses.draft', 'published' => 'surveys.statuses.published', 'closed' => 'surveys.statuses.closed'] as $key => $label): ?>
                <li class="nav-item"><a class="nav-link py-1<?= $status === $key ? ' active' : '' ?>" href="<?= e(url('/surveys' . ($key !== '' ? '?status=' . $key : ''))) ?>"><?= e(__($label)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($list === []): ?>
        <div class="card"><div class="app-empty"><i class="bi bi-ui-checks" aria-hidden="true"></i><p class="mb-0"><?= e(__('surveys.empty')) ?></p></div></div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 app-table">
                    <thead class="table-light"><tr>
                        <th><?= e(__('surveys.survey')) ?></th><th><?= e(__('surveys.audience')) ?></th><th><?= e(__('surveys.period')) ?></th>
                        <th class="text-end"><?= e(__('surveys.responses')) ?></th><th><?= e(__('common.status')) ?></th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($list as $s): ?>
                        <tr>
                            <td>
                                <?php if (can('surveys.manage')): ?><a href="<?= e(url('/surveys/' . $s['id'] . '/edit')) ?>" class="fw-semibold text-decoration-none"><?= e(localized($s, 'title')) ?></a><?php else: ?><span class="fw-semibold"><?= e(localized($s, 'title')) ?></span><?php endif; ?>
                                <div class="small text-muted"><?= e(__('surveys.questions_n', ['n' => $s['questions_count']])) ?><?= (bool) $s['is_anonymous'] ? ' · ' . e(__('surveys.anonymous')) : ' · ' . e(__('surveys.named')) ?></div>
                            </td>
                            <td class="small"><?= e($audience($s)) ?></td>
                            <td class="small text-nowrap"><?= e(format_date((string) $s['starts_on'])) ?> — <?= e(format_date((string) $s['ends_on'])) ?></td>
                            <td class="text-end"><?= e($s['responses_count']) ?></td>
                            <td><?= $this->partial('surveys/_status', ['status' => $s['status']]) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($s['status'] !== 'draft'): ?><a href="<?= e(url('/surveys/' . $s['id'] . '/results')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart me-1" aria-hidden="true"></i><?= e(__('surveys.results')) ?></a><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
