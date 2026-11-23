<?php
/**
 * Grades by subject, grouped by semester (student's "My grades" and the parent's view of a child).
 *
 * @var list<array{journal: array<string, mixed>, result: array<string, mixed>, scheme: ?array}> $items
 * @var string $linkBase link to a subject journal: $linkBase . '/' . journal id
 */
use App\Services\JournalService;

$n = static fn ($v): string => JournalService::normalize($v) ?? '—';
$bySemester = [];
foreach ($items as $item) {
    $bySemester[$item['journal']['year_title'] . ' · ' . __('dashboard.semester', ['number' => $item['journal']['semester_number']])][] = $item;
}
?>
<?php foreach ($bySemester as $label => $list): ?>
    <h2 class="h6 fw-bold mt-3 mb-2"><?= e(str_replace('-', '–', $label)) ?></h2>
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table">
                <thead class="table-light">
                <tr>
                    <th scope="col"><?= e(__('curricula.subject')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('journal.current')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= e(__('journal.components')) ?></th>
                    <th scope="col" class="text-center"><?= e(__('journal.final')) ?></th>
                    <th scope="col" class="text-center d-none d-sm-table-cell"><?= e(__('journal.absences')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $item): ?>
                    <?php
                    $j = $item['journal'];
                    $r = $item['result'];
                    $final = $j['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final']) : $r['final'];
                    ?>
                    <tr>
                        <td><a href="<?= e(url($linkBase . '/' . $j['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']])) ?></a>
                            <div class="small text-muted"><?= e(full_name(['last_name' => $j['teacher_last_name'], 'first_name' => $j['teacher_first_name'], 'middle_name' => $j['teacher_middle_name']], true)) ?></div></td>
                        <td class="text-center"><?= e($n($r['current'] ?? null)) ?></td>
                        <td class="small d-none d-md-table-cell">
                            <?php foreach ($item['scheme']['components'] ?? [] as $c): ?>
                                <?php if ($c['source'] === 'manual'): ?><span class="me-2"><?= e(localized($c, 'short')) ?>: <strong><?= e($n($r['components'][(int) $c['assessment_type_id']] ?? null)) ?></strong></span><?php endif; ?>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-center fw-bold<?= ($r['passed'] ?? null) === false ? ' text-danger' : '' ?>"><?= e($n($final)) ?><?php if ($j['status'] === 'approved'): ?> <i class="bi bi-patch-check text-success" title="<?= e(__('journal.statuses.approved')) ?>"></i><?php endif; ?></td>
                        <td class="text-center d-none d-sm-table-cell"><?= e(($r['absent'] ?? 0) + ($r['excused'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
