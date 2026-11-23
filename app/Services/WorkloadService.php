<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\WorkloadRepository;

/**
 * Teaching workload: plans, distribution of hours and plan/fact.
 *
 * Planned hours are entered by the administration (no built-in norm or formula). Actual hours:
 *  - kinds linked to a lesson type — hours of journal lessons held up to the report date, counted
 *    for the teacher who actually conducted them (substitutions included);
 *  - other kinds (consultations outside the journal, exams, practice…) — entered by hand.
 * Lessons held without a planned line appear as "unplanned" lines (plan 0).
 * Control: a line cannot exceed the curriculum hours of its column (shared by all teachers of the
 * group, per subgroup), and a plan cannot exceed the teacher's annual limit, if one is set.
 */
final class WorkloadService
{
    public const GROUP_BY = ['teacher', 'department', 'group', 'subject', 'type', 'semester', 'detail'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $typeIndex = null;

    /** @var array<string, ?int> curriculum hours per (semester, group, subject, column) */
    private array $curriculumCache = [];

    public function __construct(private readonly WorkloadRepository $repository = new WorkloadRepository())
    {
    }

    // ---- scope ---------------------------------------------------------------

    /** Teachers whose workload the user may see (null = all). @return list<int>|null */
    public function visibleTeachers(int $yearId): ?array
    {
        $departments = AccessScope::departmentIds();
        if (Auth::can('workload.manage') || $departments === null) {
            return null;
        }

        return array_map('intval', array_column($this->repository->teachers($yearId, $departments, AccessScope::teacherId(), null), 'teacher_id'));
    }

    public function canSeeTeacher(int $yearId, int $teacherId): bool
    {
        $visible = $this->visibleTeachers($yearId);

        return $visible === null || in_array($teacherId, $visible, true);
    }

    // ---- plans ---------------------------------------------------------------

    /** @param array{rate: ?string, max_hours: ?int, note: ?string} $data */
    public function savePlan(int $yearId, int $teacherId, array $data, ?array $plan): int
    {
        $yearStatus = (string) Database::fetchColumn('SELECT status FROM academic_years WHERE id = ?', [$yearId]);
        if ($yearStatus === 'archived') {
            throw new ValidationException(['max_hours' => [__('years.archived_readonly')]]);
        }
        if ($plan !== null && $data['max_hours'] !== null && $this->repository->planHours((int) $plan['id']) > $data['max_hours']) {
            throw new ValidationException(['max_hours' => [__('workload.limit_below_plan', ['hours' => $this->repository->planHours((int) $plan['id'])])]]);
        }
        if ($plan === null) {
            $id = $this->repository->createPlan($data + ['academic_year_id' => $yearId, 'teacher_id' => $teacherId, 'created_by' => Auth::id()]);
            AuditLogger::log('workload.plan_created', 'workload_plans', $id, null, $data + ['teacher_id' => $teacherId, 'academic_year_id' => $yearId]);

            return $id;
        }
        $this->repository->updatePlan((int) $plan['id'], $data);
        AuditLogger::log('workload.plan_updated', 'workload_plans', (int) $plan['id'], array_intersect_key($plan, $data), $data);

        return (int) $plan['id'];
    }

    /** @param array<string, mixed> $plan */
    public function approve(array $plan): void
    {
        $this->ensureEditable($plan);
        $hours = $this->repository->planHours((int) $plan['id']);
        if ($hours === 0) {
            throw new ValidationException(['status' => [__('workload.plan_empty')]]);
        }
        if ($plan['max_hours'] !== null && $hours > (int) $plan['max_hours']) {
            throw new ValidationException(['status' => [__('workload.exceeds_limit', ['limit' => $plan['max_hours'], 'free' => 0])]]);
        }
        $this->repository->setPlanStatus((int) $plan['id'], 'approved', Auth::id());
        AuditLogger::log('workload.plan_approved', 'workload_plans', (int) $plan['id'], ['status' => $plan['status']], ['status' => 'approved', 'hours' => $hours]);
    }

    /** @param array<string, mixed> $plan */
    public function reopen(array $plan, string $reason): void
    {
        if ($plan['year_status'] === 'archived') {
            throw new ValidationException(['reason' => [__('years.archived_readonly')]]);
        }
        $this->repository->setPlanStatus((int) $plan['id'], 'draft', null);
        AuditLogger::log('workload.plan_reopened', 'workload_plans', (int) $plan['id'], ['status' => $plan['status']], ['status' => 'draft', 'reason' => $reason]);
    }

    // ---- distribution --------------------------------------------------------

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $data semester_id, group_id, subject_id, workload_type_id, subgroup, hours, note
     * @param array<string, mixed>|null $existing on edit only subgroup, hours and note change
     */
    public function saveAssignment(array $plan, array $data, ?array $existing = null): int
    {
        $this->ensureEditable($plan);
        if ($existing !== null) {
            $data = ['semester_id' => (int) $existing['semester_id'], 'group_id' => $existing['group_id'] !== null ? (int) $existing['group_id'] : null,
                'subject_id' => $existing['subject_id'] !== null ? (int) $existing['subject_id'] : null, 'workload_type_id' => (int) $existing['workload_type_id']] + $data;
        }
        $errors = [];
        $type = $this->repository->type((int) $data['workload_type_id']);
        if ($type === null || ($existing === null && !(bool) $type['is_active'])) {
            $errors['workload_type_id'][] = __('validation.exists', ['field' => __('fields.workload_type_id')]);
        }
        $semesterYear = Database::fetchColumn('SELECT academic_year_id FROM semesters WHERE id = ?', [(int) $data['semester_id']]);
        if ((int) $semesterYear !== (int) $plan['academic_year_id']) {
            $errors['semester_id'][] = __('workload.semester_not_in_year');
        }
        $needsLine = $type !== null && ($type['lesson_type'] !== null || $type['curriculum_field'] !== null);
        if ($needsLine && ($data['group_id'] === null || $data['subject_id'] === null)) {
            $errors['group_id'][] = __('workload.group_subject_required');
        }
        if ($data['subgroup'] !== null && $data['group_id'] === null) {
            $errors['subgroup'][] = __('workload.subgroup_needs_group');
        }
        if ($errors === [] && $this->repository->duplicate((int) $plan['id'], (int) $data['semester_id'], $data['group_id'], $data['subject_id'], (int) $data['workload_type_id'], $data['subgroup'], $existing !== null ? (int) $existing['id'] : null)) {
            $errors['workload_type_id'][] = __('workload.duplicate');
        }
        if ($errors === [] && $type['curriculum_field'] !== null) {
            $free = $this->curriculumFree($data, (string) $type['curriculum_field'], $existing !== null ? (int) $existing['id'] : null);
            if ($free !== null && $data['hours'] > $free['free']) {
                $errors['hours'][] = __('workload.exceeds_curriculum', ['cap' => $free['cap'], 'free' => max(0, $free['free'])]);
            }
        }
        if ($errors === [] && $plan['max_hours'] !== null) {
            $free = (int) $plan['max_hours'] - $this->repository->planHours((int) $plan['id'], $existing !== null ? (int) $existing['id'] : null);
            if ($data['hours'] > $free) {
                $errors['hours'][] = __('workload.exceeds_limit', ['limit' => $plan['max_hours'], 'free' => max(0, $free)]);
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($existing !== null) {
            $this->repository->updateAssignment((int) $existing['id'], $data);
            AuditLogger::log('workload.assignment_updated', 'workload_assignments', (int) $existing['id'], array_intersect_key($existing, ['subgroup' => 1, 'hours' => 1, 'note' => 1]), array_intersect_key($data, ['subgroup' => 1, 'hours' => 1, 'note' => 1]));

            return (int) $existing['id'];
        }
        $id = $this->repository->createAssignment($data + ['plan_id' => (int) $plan['id'], 'created_by' => Auth::id()]);
        AuditLogger::log('workload.assignment_created', 'workload_assignments', $id, null, $data + ['plan_id' => (int) $plan['id']]);

        return $id;
    }

    /** @param array<string, mixed> $assignment */
    public function deleteAssignment(array $assignment): void
    {
        $plan = $this->repository->plan((int) $assignment['plan_id']);
        $this->ensureEditable($plan ?? []);
        if ($this->repository->hasActuals((int) $assignment['id'])) {
            throw new ValidationException(['status' => [__('workload.has_actuals')]]);
        }
        $this->repository->deleteAssignment((int) $assignment['id']);
        AuditLogger::log('workload.assignment_deleted', 'workload_assignments', (int) $assignment['id'], array_intersect_key($assignment, array_flip(['semester_id', 'group_id', 'subject_id', 'workload_type_id', 'subgroup', 'hours'])), null);
    }

    /**
     * Plan the not yet distributed curriculum hours of the teacher's journals in this year
     * (one line per curriculum column). Existing lines are kept; the annual limit is respected.
     *
     * @param array<string, mixed> $plan
     * @return array{created: int, hours: int, limit_reached: bool, without_curriculum: int}
     */
    public function fill(array $plan): array
    {
        $this->ensureEditable($plan);
        $types = array_values(array_filter($this->repository->types(), static fn (array $t): bool => $t['curriculum_field'] !== null));
        $result = ['created' => 0, 'hours' => 0, 'limit_reached' => false, 'without_curriculum' => 0];

        Database::transaction(function () use ($plan, $types, &$result): void {
            foreach ($this->repository->teacherJournals((int) $plan['teacher_id'], (int) $plan['academic_year_id']) as $journal) {
                $line = ['semester_id' => (int) $journal['semester_id'], 'group_id' => (int) $journal['group_id'], 'subject_id' => (int) $journal['subject_id']];
                if ($this->repository->curriculumHours($line['group_id'], $line['semester_id'], $line['subject_id'], 'hours_total') === null) {
                    $result['without_curriculum']++;
                    continue;
                }
                foreach ($types as $type) {
                    if ($this->repository->duplicate((int) $plan['id'], $line['semester_id'], $line['group_id'], $line['subject_id'], (int) $type['id'], null, null)) {
                        continue;
                    }
                    $free = $this->curriculumFree($line + ['workload_type_id' => (int) $type['id'], 'subgroup' => null], (string) $type['curriculum_field'], null);
                    $hours = $free === null ? 0 : $free['free'];
                    if ($plan['max_hours'] !== null) {
                        $room = (int) $plan['max_hours'] - $this->repository->planHours((int) $plan['id']);
                        if ($room < $hours) {
                            $result['limit_reached'] = $result['limit_reached'] || $hours > 0;
                            $hours = max(0, $room);
                        }
                    }
                    if ($hours <= 0) {
                        continue;
                    }
                    $this->repository->createAssignment($line + ['plan_id' => (int) $plan['id'], 'workload_type_id' => (int) $type['id'], 'subgroup' => null,
                        'hours' => $hours, 'note' => null, 'created_by' => Auth::id()]);
                    $result['created']++;
                    $result['hours'] += $hours;
                }
            }
        });
        AuditLogger::log('workload.plan_filled', 'workload_plans', (int) $plan['id'], null, $result);

        return $result;
    }

    // ---- manual actual hours -------------------------------------------------

    /**
     * @param array<string, mixed> $assignment
     * @param array{work_date: string, hours: string, note: ?string} $data
     */
    public function addActual(array $assignment, array $data): int
    {
        if ($assignment['lesson_type'] !== null) {
            throw new ValidationException(['hours' => [__('workload.fact_from_journal')]]);
        }
        if ($assignment['year_status'] === 'archived') {
            throw new ValidationException(['work_date' => [__('years.archived_readonly')]]);
        }
        if ($data['work_date'] < $assignment['semester_start'] || $data['work_date'] > $assignment['semester_end'] || $data['work_date'] > date('Y-m-d')) {
            throw new ValidationException(['work_date' => [__('workload.fact_date_range', ['from' => format_date((string) $assignment['semester_start']), 'to' => format_date(min((string) $assignment['semester_end'], date('Y-m-d')))])]]);
        }
        $id = $this->repository->addActual($data + ['assignment_id' => (int) $assignment['id'], 'created_by' => Auth::id()]);
        AuditLogger::log('workload.actual_added', 'workload_actuals', $id, null, $data + ['assignment_id' => (int) $assignment['id']]);

        return $id;
    }

    /**
     * @param array<string, mixed> $actual
     * @param array<string, mixed> $assignment
     */
    public function deleteActual(array $actual, array $assignment): void
    {
        if ($assignment['year_status'] === 'archived') {
            throw new ValidationException(['work_date' => [__('years.archived_readonly')]]);
        }
        $this->repository->deleteActual((int) $actual['id']);
        AuditLogger::log('workload.actual_deleted', 'workload_actuals', (int) $actual['id'], $actual, null);
    }

    // ---- plan / fact ---------------------------------------------------------

    /**
     * Plan/fact lines: one per planned line plus unplanned journal hours.
     *
     * @param array<string, mixed> $filters year (required), semester, department, teacher, group, subject, type, teachers
     * @return list<array<string, mixed>> assignment_id, teacher_id, semester_id, group_id, subject_id, type_id, subgroup, plan, fact, source, curriculum
     */
    public function lines(array $filters, ?string $asOf = null): array
    {
        $asOf ??= date('Y-m-d');
        $types = $this->typeIndex();
        $byLessonType = [];
        foreach ($types as $type) {
            if ($type['lesson_type'] !== null) {
                $byLessonType[$type['lesson_type']] = (int) $type['id'];
            }
        }
        $key = static fn (array $r, int $typeId): string => implode('|', [(int) $r['teacher_id'], (int) $r['semester_id'], (int) $r['group_id'], (int) $r['subject_id'], $typeId]);

        $facts = [];
        foreach ($this->repository->journalFacts($filters, $asOf) as $f) {
            if (isset($byLessonType[$f['lesson_type']])) {
                $k = $key($f, $byLessonType[$f['lesson_type']]);
                $facts[$k] = ($facts[$k] ?? 0) + (int) $f['hours'];
            }
        }

        $lines = [];
        foreach ($this->repository->planLines($filters, $asOf) as $p) {
            $typeId = (int) $p['workload_type_id'];
            $fromJournal = ($types[$typeId]['lesson_type'] ?? null) !== null;
            $fact = (float) $p['manual_fact'];
            if ($fromJournal) {
                $k = $key($p, $typeId);
                $fact = (float) ($facts[$k] ?? 0); // several lines of one key (subgroups): the first one takes the hours
                unset($facts[$k]);
            }
            $lines[] = $this->line($p, $typeId, (int) $p['plan'], $fact, $fromJournal ? 'journal' : 'manual', (int) $p['assignment_id'], $p['subgroup'] !== null ? (int) $p['subgroup'] : null);
        }
        foreach ($facts as $k => $hours) {
            [$teacher, $semester, $group, $subject, $typeId] = array_map('intval', explode('|', $k));
            if (!empty($filters['type']) && (int) $filters['type'] !== $typeId) {
                continue;
            }
            $lines[] = $this->line(['teacher_id' => $teacher, 'semester_id' => $semester, 'group_id' => $group, 'subject_id' => $subject], $typeId, 0, (float) $hours, 'journal', null, null);
        }

        return $lines;
    }

    /**
     * Aggregate lines into report rows.
     *
     * @param list<array<string, mixed>> $lines
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>, curriculum: bool, limits: bool}
     */
    public function report(array $lines, string $by, int $yearId): array
    {
        $by = in_array($by, self::GROUP_BY, true) ? $by : 'teacher';
        $labels = $this->repository->labels(
            array_values(array_unique(array_column($lines, 'teacher_id'))),
            array_values(array_unique(array_column($lines, 'semester_id'))),
            array_values(array_filter(array_unique(array_column($lines, 'group_id')))),
            array_values(array_filter(array_unique(array_column($lines, 'subject_id'))))
        );
        $types = $this->typeIndex();
        $showCurriculum = in_array($by, ['group', 'subject', 'type', 'semester', 'detail'], true);
        $limits = $by === 'teacher' ? $this->repository->limits($yearId, array_map('intval', array_unique(array_column($lines, 'teacher_id')))) : [];

        $teacherName = static fn (?array $t): string => $t !== null ? full_name($t, true) : '—';
        $subjectName = static fn (?array $s): string => $s !== null ? trim($s['code'] . ' ' . localized($s)) : '—';
        $buckets = [];
        foreach ($lines as $l) {
            $t = $labels['teachers'][$l['teacher_id']] ?? null;
            $semester = __('dashboard.semester', ['number' => $labels['semesters'][$l['semester_id']]['number'] ?? '?']);
            $group = $l['group_id'] !== null ? ($labels['groups'][$l['group_id']] ?? '—') : '—';
            $subject = $l['subject_id'] !== null ? $subjectName($labels['subjects'][$l['subject_id']] ?? null) : '—';
            $type = isset($types[$l['type_id']]) ? localized($types[$l['type_id']]) : '—';
            [$k, $label] = match ($by) {
                'teacher' => ['t' . $l['teacher_id'], [$teacherName($t), $t !== null ? localized(['name_ru' => $t['department_name_ru'], 'name_kk' => $t['department_name_kk']]) : '']],
                'department' => ['d' . ($t['department_id'] ?? 0), [$t !== null && $t['department_id'] !== null ? localized(['name_ru' => $t['department_name_ru'], 'name_kk' => $t['department_name_kk']]) : __('workload.no_department')]],
                'group' => ['g' . ($l['group_id'] ?? 0), [$group]],
                'subject' => ['s' . ($l['subject_id'] ?? 0), [$subject]],
                'type' => ['y' . $l['type_id'], [$type]],
                'semester' => ['m' . $l['semester_id'], [$semester]],
                default => ['l' . count($buckets), [$teacherName($t), $semester, $group . ($l['subgroup'] !== null ? ' (' . __('schedule.subgroup_n', ['n' => $l['subgroup']]) . ')' : ''), $subject, $type]],
            };
            $b = &$buckets[$k];
            $b ??= ['label' => $label, 'plan' => 0, 'fact' => 0.0, 'curriculum' => [], 'unplanned' => 0.0, 'teacher_id' => (int) $l['teacher_id'], 'lines' => 0];
            $b['plan'] += $l['plan'];
            $b['fact'] += $l['fact'];
            $b['lines']++;
            if ($l['assignment_id'] === null) {
                $b['unplanned'] += $l['fact'];
            }
            if ($l['curriculum'] !== null) {
                $b['curriculum'][$l['curriculum_key']] = $l['curriculum']; // one curriculum line counted once
            }
            if ($by === 'detail') {
                $b['source'] = $l['source'];
                $b['assignment_id'] = $l['assignment_id'];
            }
            unset($b);
        }

        $rows = [];
        $totals = ['plan' => 0, 'fact' => 0.0, 'curriculum' => 0, 'unplanned' => 0.0, 'limit' => 0];
        foreach ($buckets as $b) {
            $b['curriculum'] = $b['curriculum'] === [] ? null : array_sum($b['curriculum']);
            // Remainder and completion compare the plan with hours of planned lines only;
            // unplanned hours (substitutions) are part of the fact but do not reduce the remainder.
            $b['remainder'] = $b['plan'] - ($b['fact'] - $b['unplanned']);
            $b['percent'] = $b['plan'] > 0 ? round(($b['fact'] - $b['unplanned']) / $b['plan'] * 100, 1) : null;
            if ($by === 'teacher') {
                $limit = $limits[$b['teacher_id']] ?? null;
                $b['limit'] = $limit['max_hours'] ?? null;
                $b['rate'] = $limit['rate'] ?? null;
                $b['over_limit'] = $b['limit'] !== null && $b['plan'] > $b['limit'];
                $totals['limit'] += (int) $b['limit'];
            }
            $rows[] = $b;
            $totals['plan'] += $b['plan'];
            $totals['fact'] += $b['fact'];
            $totals['curriculum'] += (int) $b['curriculum'];
            $totals['unplanned'] += $b['unplanned'];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp(implode(' ', $a['label']), implode(' ', $b['label'])));
        $totals['remainder'] = $totals['plan'] - ($totals['fact'] - $totals['unplanned']);
        $totals['percent'] = $totals['plan'] > 0 ? round(($totals['fact'] - $totals['unplanned']) / $totals['plan'] * 100, 1) : null;

        return ['rows' => $rows, 'totals' => $totals, 'curriculum' => $showCurriculum, 'limits' => $by === 'teacher', 'by' => $by];
    }

    /** @return array<int, array<string, mixed>> all kinds (inactive too: old lines keep their name) */
    public function typeIndex(): array
    {
        if ($this->typeIndex === null) {
            $this->typeIndex = [];
            foreach ($this->repository->types(false) as $type) {
                $this->typeIndex[(int) $type['id']] = $type;
            }
        }

        return $this->typeIndex;
    }

    /** Hours as text: integers without decimals. */
    public static function hours(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $v = round((float) $value, 2);

        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    // ---- internals -----------------------------------------------------------

    /** @param array<string, mixed> $plan */
    private function ensureEditable(array $plan): void
    {
        if (($plan['year_status'] ?? '') === 'archived') {
            throw new ValidationException(['status' => [__('years.archived_readonly')]]);
        }
        if (($plan['status'] ?? '') !== 'draft') {
            throw new ValidationException(['status' => [__('workload.plan_locked')]]);
        }
    }

    /**
     * Free curriculum hours of a line for the given subgroup (whole group and every subgroup
     * must fit into the curriculum volume). Null when the curriculum has no such line.
     *
     * @param array<string, mixed> $data
     * @return array{cap: int, free: int}|null
     */
    private function curriculumFree(array $data, string $field, ?int $exceptId): ?array
    {
        $cap = $this->repository->curriculumHours((int) $data['group_id'], (int) $data['semester_id'], (int) $data['subject_id'], $field);
        if ($cap === null) {
            return null;
        }
        $dist = $this->repository->distributed((int) $data['semester_id'], (int) $data['group_id'], (int) $data['subject_id'], (int) $data['workload_type_id'], $exceptId);
        $whole = $dist[0] ?? 0;
        unset($dist[0]);
        $used = $data['subgroup'] === null ? $whole + ($dist === [] ? 0 : max($dist)) : $whole + ($dist[(int) $data['subgroup']] ?? 0);

        return ['cap' => $cap, 'free' => $cap - $used];
    }

    /** @param array<string, mixed> $r */
    private function line(array $r, int $typeId, int $plan, float $fact, string $source, ?int $assignmentId, ?int $subgroup): array
    {
        $type = $this->typeIndex()[$typeId] ?? null;
        $curriculum = null;
        $curriculumKey = null;
        if ($type !== null && $type['curriculum_field'] !== null && !empty($r['group_id']) && !empty($r['subject_id'])) {
            $curriculumKey = implode('|', [$r['semester_id'], $r['group_id'], $r['subject_id'], $type['curriculum_field']]);
            if (!array_key_exists($curriculumKey, $this->curriculumCache)) {
                $this->curriculumCache[$curriculumKey] = $this->repository->curriculumHours((int) $r['group_id'], (int) $r['semester_id'], (int) $r['subject_id'], (string) $type['curriculum_field']);
            }
            $curriculum = $this->curriculumCache[$curriculumKey];
        }

        return [
            'assignment_id' => $assignmentId,
            'teacher_id' => (int) $r['teacher_id'],
            'semester_id' => (int) $r['semester_id'],
            'group_id' => !empty($r['group_id']) ? (int) $r['group_id'] : null,
            'subject_id' => !empty($r['subject_id']) ? (int) $r['subject_id'] : null,
            'type_id' => $typeId,
            'subgroup' => $subgroup,
            'plan' => $plan,
            'fact' => $fact,
            'source' => $source,
            'curriculum' => $curriculum,
            'curriculum_key' => $curriculumKey,
        ];
    }
}
