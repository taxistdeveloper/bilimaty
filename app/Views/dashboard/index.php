<?php
/** @var App\Core\View $this */
/** @var array{students: int, teachers: int, groups: int, departments: int} $counters */
/** @var array<string, mixed>|null $year */
/** @var list<array<string, mixed>> $semesters */
/** @var list<array<string, mixed>> $roles */
/** @var list<array{label: string, value: int}> $chart */
/** @var list<array<string, mixed>> $audit */
/** @var array{mode: string, items: list<array<string, mixed>>}|null $todayLessons */
/** @var list<array<string, mixed>>|null $upcomingExams */
/** @var array<string, mixed>|null $myWorkload */
/** @var list<array<string, mixed>> $mySurveys */
/** @var list<array<string, mixed>> $myChildren */
/** @var array<string, mixed>|null $myStudy student: group, semester, results */
/** @var list<array<string, mixed>>|null $myJournals teacher: journals of the semester */
$user = auth_user() ?? [];
$today = date('Y-m-d');

$cards = [
    ['key' => 'students', 'icon' => 'bi-people', 'tone' => 'primary', 'can' => 'students.view'],
    ['key' => 'teachers', 'icon' => 'bi-person-badge', 'tone' => 'success', 'can' => 'teachers.view'],
    ['key' => 'groups', 'icon' => 'bi-collection', 'tone' => 'warning', 'can' => 'groups.view'],
    ['key' => 'departments', 'icon' => 'bi-diagram-3', 'tone' => 'dark', 'can' => 'teachers.view'],
];
$cards = array_values(array_filter($cards, static fn (array $c): bool => can($c['can'])));
?>
<div class="card app-hero mb-4">
    <div class="card-body">
        <div class="app-hero__text">
            <h2 class="h4 fw-bold mb-1"><?= e(__('dashboard.greeting', ['name' => trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? ''))])) ?></h2>
            <p class="text-muted mb-3"><?= e(__('dashboard.subtitle')) ?> · <?= e(format_date($today)) ?></p>
            <div class="d-flex flex-wrap gap-1" aria-label="<?= e(__('dashboard.your_roles')) ?>">
                <?php foreach ($roles as $role): ?>
                    <span class="badge rounded-pill app-role-badge"><?= e(localized($role)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <img src="<?= e(asset('assets/images/mascot-thumb.png')) ?>" alt="" class="app-hero__mascot d-none d-sm-block" width="312" height="480">
    </div>
</div>

<?php if ($cards !== []): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($cards as $card): ?>
            <div class="col-6 col-xl-3">
                <div class="card app-stat h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="app-stat__icon app-stat__icon--<?= e($card['tone']) ?>"><i class="bi <?= e($card['icon']) ?>" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <div class="app-stat__value"><?= e(number_format($counters[$card['key']], 0, '.', ' ')) ?></div>
                            <div class="app-stat__label"><?= e(__('dashboard.' . $card['key'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($todayLessons !== null): ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-calendar-day text-primary me-2" aria-hidden="true"></i><?= e(__('schedule.today_title')) ?></span>
            <a href="<?= e(url('/schedule?view=day')) ?>" class="small text-decoration-none"><?= e(__('schedule.open_schedule')) ?></a>
        </div>
        <?php if ($todayLessons['items'] === []): ?>
            <div class="card-body small text-muted"><?= e(__('schedule.no_lessons_day')) ?></div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($todayLessons['items'] as $o): ?>
                    <?php $off = in_array($o['status'], ['cancelled', 'moved_out', 'suspended'], true); ?>
                    <div class="list-group-item d-flex gap-3 align-items-center<?= $off ? ' text-muted' : '' ?>">
                        <div class="text-center app-sched__daytime">
                            <div class="fw-bold"><?= e($o['period_number']) ?></div>
                            <div class="small text-muted"><?= e(substr((string) $o['start_time'], 0, 5)) ?>–<?= e(substr((string) $o['end_time'], 0, 5)) ?></div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold<?= $off ? ' text-decoration-line-through' : '' ?>"><?= e(localized(['name_ru' => $o['subject_name_ru'], 'name_kk' => $o['subject_name_kk']])) ?></div>
                            <div class="small text-muted">
                                <?= e($todayLessons['mode'] === 'teacher' ? $o['group_code'] . ($o['subgroup'] !== null ? '/' . $o['subgroup'] : '') : $o['effective_teacher_name']) ?>
                                · <?= e(__('schedule.room')) ?> <?= e($o['effective_classroom_label']) ?>
                            </div>
                        </div>
                        <?php if ($o['status'] !== 'regular'): ?><span class="badge app-badge-warning"><?= e(__('schedule.statuses.' . $o['status'])) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($myChildren)): ?>
    <h2 class="h6 fw-bold mb-2"><?= e(__('children.title')) ?></h2>
    <div class="row g-3 mb-4">
        <?php foreach ($myChildren as $c): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?= e(url('/children/' . $c['id'])) ?>" class="card h-100 text-decoration-none text-reset app-card-link">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <span class="app-avatar app-avatar--lg" aria-hidden="true"><?= e(mb_substr((string) $c['first_name'], 0, 1) . mb_substr((string) $c['last_name'], 0, 1)) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= e(full_name($c)) ?></div>
                            <div class="small text-muted"><?= e($c['group_code'] ?? '—') ?><?= $c['course'] !== null ? ' · ' . e(__('groups.course_n', ['n' => $c['course']])) : '' ?></div>
                            <div class="small text-primary"><?= e(__('children.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($mySurveys)): ?>
    <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-ui-checks me-2" aria-hidden="true"></i><?= e(__('surveys.dashboard', ['n' => count($mySurveys)])) ?>: <?= e(implode(', ', array_map(static fn (array $s): string => localized($s, 'title'), array_slice($mySurveys, 0, 3)))) ?></span>
        <a href="<?= e(url('/surveys')) ?>" class="btn btn-sm btn-primary"><?= e(__('surveys.take')) ?></a>
    </div>
<?php endif; ?>

<?php if (!empty($myStudy)): $st = $myStudy['student']; $nm = static fn ($v): string => App\Services\JournalService::normalize($v) ?? '—'; ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-person-badge text-primary me-2" aria-hidden="true"></i><?= e(__('dashboard.my_study')) ?></div>
                <div class="card-body">
                    <div class="fw-semibold mb-2"><?= e(full_name($st)) ?></div>
                    <dl class="row g-2 small mb-0">
                        <div class="col-6"><dt class="text-muted fw-normal"><?= e(__('fields.group_id')) ?></dt><dd class="mb-0"><?= e($st['group_code'] ?? '—') ?></dd></div>
                        <div class="col-6"><dt class="text-muted fw-normal"><?= e(__('fields.course')) ?></dt><dd class="mb-0"><?= e($st['course'] ?? '—') ?></dd></div>
                        <div class="col-12"><dt class="text-muted fw-normal"><?= e(__('fields.specialty_id')) ?></dt><dd class="mb-0"><?= e(trim(($st['specialty_code'] ?? '') . ' ' . localized(['name_ru' => $st['specialty_name_ru'] ?? null, 'name_kk' => $st['specialty_name_kk'] ?? null]))) ?></dd></div>
                        <div class="col-6"><dt class="text-muted fw-normal"><?= e(__('dashboard.current_semester')) ?></dt><dd class="mb-0"><?= e($myStudy['semester'] !== null ? __('dashboard.semester', ['number' => $myStudy['semester']['number']]) : '—') ?></dd></div>
                        <div class="col-6"><dt class="text-muted fw-normal"><?= e(__('children.absences')) ?></dt><dd class="mb-0"><?= e($myStudy['absences']) ?></dd></div>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-mortarboard text-primary me-2" aria-hidden="true"></i><?= e(__('dashboard.my_results')) ?></span>
                    <a href="<?= e(url('/grades')) ?>" class="small text-decoration-none"><?= e(__('journal.my_title')) ?></a>
                </div>
                <?php if ($myStudy['rows'] === []): ?>
                    <div class="card-body small text-muted"><?= e(__('journal.my_empty')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 app-table">
                            <thead class="table-light"><tr>
                                <th><?= e(__('curricula.subject')) ?></th><th class="text-center"><?= e(__('transcripts.components.rating1')) ?></th><th class="text-center"><?= e(__('transcripts.components.rating2')) ?></th>
                                <th class="text-center"><?= e(__('transcripts.components.exam')) ?></th><th class="text-center"><?= e(__('journal.final')) ?></th><th class="text-center d-none d-sm-table-cell"><?= e(__('journal.absences')) ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($myStudy['rows'] as $row): ?>
                                <tr>
                                    <td><a href="<?= e(url('/grades/' . $row['journal_id'])) ?>" class="text-decoration-none"><?= e($row['subject']) ?></a></td>
                                    <td class="text-center"><?= e($nm($row['rating1'])) ?></td>
                                    <td class="text-center"><?= e($nm($row['rating2'])) ?></td>
                                    <td class="text-center"><?= e($nm($row['exam'])) ?></td>
                                    <td class="text-center fw-bold<?= $row['passed'] === false ? ' text-danger' : '' ?>"><?= e($nm($row['final'])) ?></td>
                                    <td class="text-center d-none d-sm-table-cell"><?= e($row['absences']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($myJournals)): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-journal-bookmark text-primary me-2" aria-hidden="true"></i><?= e(__('dashboard.my_journals')) ?></span>
            <a href="<?= e(url('/journal?mine=1')) ?>" class="small text-decoration-none"><?= e(__('nav.journal')) ?></a>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($myJournals as $mj): $j = $mj['journal']; ?>
                <a href="<?= e(url('/journal/' . $j['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span class="min-w-0">
                        <span class="fw-semibold"><?= e($j['group_code']) ?></span> · <?= e(localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']])) ?>
                        <span class="small text-muted d-block"><?= e(__('dashboard.journal_stats', ['students' => $mj['students'], 'lessons' => $j['lessons_count']])) ?></span>
                    </span>
                    <span class="d-flex gap-2 align-items-center">
                        <?php if ($mj['incomplete'] > 0): ?><span class="badge app-badge-warning" title="<?= e(__('dashboard.incomplete_hint')) ?>"><?= e(__('dashboard.incomplete', ['n' => $mj['incomplete']])) ?></span><?php endif; ?>
                        <span class="badge <?= $j['status'] === 'approved' ? 'app-badge-success' : 'app-badge-muted' ?>"><?= e(__('journal.statuses.' . $j['status'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($myWorkload)): $wh = static fn ($v): string => App\Services\WorkloadService::hours($v); ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-hourglass-split text-primary me-2" aria-hidden="true"></i><?= e(__('workload.dashboard')) ?></span>
            <a href="<?= e(url('/workload/teachers/' . $myWorkload['teacher_id'] . '?year=' . $myWorkload['year_id'])) ?>" class="small text-decoration-none"><?= e(__('workload.title')) ?></a>
        </div>
        <div class="card-body row g-2 text-center">
            <div class="col-4"><div class="small text-muted"><?= e(__('workload.plan')) ?></div><div class="h5 fw-bold mb-0"><?= e($wh($myWorkload['plan'])) ?></div></div>
            <div class="col-4"><div class="small text-muted"><?= e(__('workload.fact')) ?></div><div class="h5 fw-bold mb-0"><?= e($wh($myWorkload['fact'])) ?></div></div>
            <div class="col-4"><div class="small text-muted"><?= e(__('workload.remainder')) ?></div><div class="h5 fw-bold mb-0<?= $myWorkload['remainder'] < 0 ? ' text-danger' : '' ?>"><?= e($wh($myWorkload['remainder'])) ?></div></div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($upcomingExams)): ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-clipboard-check text-primary me-2" aria-hidden="true"></i><?= e(__('exams.upcoming')) ?></span>
            <a href="<?= e(url('/exams')) ?>" class="small text-decoration-none"><?= e(__('exams.title')) ?></a>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($upcomingExams as $x): ?>
                <div class="list-group-item d-flex gap-3 align-items-center">
                    <div class="text-center app-sched__daytime"><div class="fw-bold"><?= e(format_date($x['exam_date'], 'd.m')) ?></div><div class="small text-muted"><?= e($x['start_time'] !== null ? substr((string) $x['start_time'], 0, 5) : '') ?></div></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold"><?= e(localized(['name_ru' => $x['subject_name_ru'], 'name_kk' => $x['subject_name_kk']])) ?> <span class="badge app-badge-primary"><?= e(localized(['name_ru' => $x['type_name_ru'], 'name_kk' => $x['type_name_kk']])) ?></span></div>
                        <div class="small text-muted"><?= e($x['group_code']) ?><?= $x['classroom_number'] !== null ? ' · ' . e(__('schedule.room')) . ' ' . e($x['classroom_number']) : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-calendar-range text-primary" aria-hidden="true"></i>
                <span class="fw-semibold"><?= e(__('dashboard.academic_year')) ?></span>
            </div>
            <div class="card-body">
                <?php if ($year === null): ?>
                    <p class="text-muted mb-0"><?= e(__('dashboard.no_active_year')) ?></p>
                <?php else: ?>
                    <div class="display-6 fw-bold mb-1"><?= e(str_replace('-', '–', (string) $year['title'])) ?></div>
                    <p class="text-muted small mb-3"><?= e(format_date($year['start_date'])) ?> — <?= e(format_date($year['end_date'])) ?></p>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($semesters as $semester): ?>
                            <?php $isCurrent = $semester['start_date'] <= $today && $today <= $semester['end_date']; ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>
                                    <?= e(__('dashboard.semester', ['number' => $semester['number']])) ?>
                                    <?php if ($isCurrent): ?><span class="badge text-bg-primary ms-1"><?= e(__('dashboard.current')) ?></span><?php endif; ?>
                                </span>
                                <span class="small text-muted"><?= e(format_date($semester['start_date'], 'd.m')) ?> — <?= e(format_date($semester['end_date'], 'd.m.Y')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (can('students.view')): ?>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart text-primary" aria-hidden="true"></i>
                    <span class="fw-semibold"><?= e(__('dashboard.chart_title')) ?></span>
                </div>
                <div class="card-body">
                    <?php if (array_sum(array_column($chart, 'value')) === 0): ?>
                        <div class="app-empty">
                            <i class="bi bi-inboxes" aria-hidden="true"></i>
                            <p class="mb-0"><?= e(__('dashboard.no_data')) ?></p>
                        </div>
                    <?php else: ?>
                        <div class="app-chart">
                            <canvas id="studentsChart" aria-label="<?= e(__('dashboard.chart_title')) ?>" role="img"></canvas>
                        </div>
                        <script type="application/json" id="studentsChartData"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (can('audit.view')): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-activity text-primary" aria-hidden="true"></i>
                    <span class="fw-semibold"><?= e(__('dashboard.recent_activity')) ?></span>
                </div>
                <?php if ($audit === []): ?>
                    <div class="card-body text-muted"><?= e(__('dashboard.no_activity')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 app-table">
                            <tbody>
                            <?php foreach ($audit as $row): ?>
                                <?php
                                $actionKey = 'audit_actions.' . str_replace('.', '_', (string) $row['action']);
                                $actionLabel = __($actionKey) === $actionKey ? $row['action'] : __($actionKey);
                                $danger = in_array($row['action'], ['auth.login_failed', 'auth.login_locked'], true);
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><?= e(format_date($row['created_at'], 'd.m.Y H:i')) ?></td>
                                    <td>
                                        <span class="app-dot <?= $danger ? 'app-dot--danger' : 'app-dot--success' ?>" aria-hidden="true"></span>
                                        <?= e($actionLabel) ?>
                                    </td>
                                    <td class="small"><?= e($row['last_name'] !== null ? full_name($row, true) : __('dashboard.system')) ?></td>
                                    <td class="small text-muted text-end text-nowrap d-none d-md-table-cell"><?= e($row['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $this->start('scripts'); ?>
<?php if (can('students.view') && array_sum(array_column($chart, 'value')) > 0): ?>
    <script src="<?= e(asset('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/dashboard.js')) ?>"></script>
<?php endif; ?>
<?php $this->stop(); ?>
