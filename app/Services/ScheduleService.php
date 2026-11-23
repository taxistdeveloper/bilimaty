<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\ScheduleRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Timetable rules.
 *
 * Conflicts: in one semester, weekday and period, two lessons whose week parities
 * overlap ("every" overlaps both odd and even) must not share
 *   - the teacher,
 *   - the classroom,
 *   - the group (unless both lessons are for different subgroups).
 * The same rules are applied to one-off changes on a concrete date.
 * Writes run under a per-semester MySQL named lock so two dispatchers cannot
 * create the same conflict at the same moment.
 */
final class ScheduleService
{
    public const PARITIES = ['every', 'odd', 'even'];
    public const TYPES = ['lecture', 'practice', 'lab', 'consultation'];

    public function __construct(private readonly ScheduleRepository $repo = new ScheduleRepository())
    {
    }

    // ------------------------------------------------------------------ regular entries

    /**
     * @param array<string, mixed> $data validated entry (semester_id, group_id, subgroup, subject_id, teacher_id,
     *                                     classroom_id, weekday, lesson_period_id, week_parity, lesson_type, note)
     */
    public function saveEntry(array $data, ?array $existing = null): int
    {
        $semester = $this->repo->semester((int) $data['semester_id']) ?? throw new ValidationException(['semester_id' => [__('schedule.semester_missing')]]);
        if ($semester['year_status'] === 'archived') {
            throw new ValidationException(['semester_id' => [__('years.archived_readonly')]]);
        }
        $this->checkReferences($data, $semester);

        return $this->locked((int) $semester['id'], function () use ($data, $existing): int {
            $conflicts = $this->entryConflicts($data, $existing !== null ? (int) $existing['id'] : null);
            if ($conflicts !== []) {
                throw new ValidationException(['conflicts' => $conflicts]);
            }
            if ($existing === null) {
                $id = $this->repo->create($data + ['created_by' => Auth::id()]);
                AuditLogger::log('schedule.created', 'schedule_entries', $id, null, $data);

                return $id;
            }
            $this->repo->update((int) $existing['id'], $data);
            $keys = array_keys($data);
            AuditLogger::log('schedule.updated', 'schedule_entries', (int) $existing['id'], array_intersect_key($existing, array_flip($keys)), $data);

            return (int) $existing['id'];
        });
    }

    /** @param array<string, mixed> $entry */
    public function deleteEntry(array $entry): void
    {
        $semester = $this->repo->semester((int) $entry['semester_id']);
        if ($semester !== null && $semester['year_status'] === 'archived') {
            throw new ValidationException(['semester_id' => [__('years.archived_readonly')]]);
        }
        $this->repo->delete((int) $entry['id']); // its one-off changes are removed by ON DELETE CASCADE
        AuditLogger::log('schedule.deleted', 'schedule_entries', (int) $entry['id'], array_intersect_key($entry, array_flip([
            'semester_id', 'group_id', 'subgroup', 'subject_id', 'teacher_id', 'classroom_id', 'weekday', 'lesson_period_id', 'week_parity',
        ])));
    }

    /**
     * Human-readable conflicts of a (new or edited) regular entry.
     *
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public function entryConflicts(array $data, ?int $ignoreId): array
    {
        $messages = [];
        $slot = $this->repo->slotEntries((int) $data['semester_id'], (int) $data['weekday'], (int) $data['lesson_period_id'], $ignoreId);
        foreach ($slot as $other) {
            if (!self::paritiesOverlap((string) $data['week_parity'], (string) $other['week_parity'])) {
                continue;
            }
            $where = $this->describeEntry($other);
            if ((int) $other['teacher_id'] === (int) $data['teacher_id']) {
                $messages[] = __('schedule.conflict_teacher', ['teacher' => $this->teacherName($other), 'where' => $where]);
            }
            if ($data['classroom_id'] !== null && (int) $other['classroom_id'] === (int) $data['classroom_id']) {
                $messages[] = __('schedule.conflict_classroom', ['classroom' => $this->classroomLabel($other), 'where' => $where]);
            }
            if ((int) $other['group_id'] === (int) $data['group_id'] && !self::differentSubgroups($data['subgroup'], $other['subgroup'])) {
                $messages[] = __('schedule.conflict_group', ['group' => $other['group_code'], 'where' => $where]);
            }
        }

        return array_values(array_unique($messages));
    }

    // ------------------------------------------------------------------ occurrences

    /**
     * Week number parity counted from the week of the semester's first day (week 1 = odd).
     */
    public static function weekParity(string $semesterStart, string $date): string
    {
        $monday = static fn (string $d): DateTimeImmutable => (new DateTimeImmutable($d))->modify('-' . ((int) (new DateTimeImmutable($d))->format('N') - 1) . ' days');
        $weeks = intdiv((int) $monday($semesterStart)->diff($monday($date))->format('%r%a'), 7);

        return $weeks % 2 === 0 ? 'odd' : 'even';
    }

    public static function weekNumber(string $semesterStart, string $date): int
    {
        $monday = static fn (string $d): DateTimeImmutable => (new DateTimeImmutable($d))->modify('-' . ((int) (new DateTimeImmutable($d))->format('N') - 1) . ' days');

        return intdiv((int) $monday($semesterStart)->diff($monday($date))->format('%r%a'), 7) + 1;
    }

    public static function paritiesOverlap(string $a, string $b): bool
    {
        return $a === 'every' || $b === 'every' || $a === $b;
    }

    private static function differentSubgroups(mixed $a, mixed $b): bool
    {
        return $a !== null && $a !== '' && $b !== null && $b !== '' && (int) $a !== (int) $b;
    }

    /**
     * Effective lessons of a semester between two dates (inclusive), after applying one-off
     * changes and calendar suspensions. Optionally filtered by group/teacher/classroom
     * (a substitute teacher sees the lesson in their schedule; the replaced teacher sees it marked as substituted).
     *
     * Every occurrence: entry data + date, period, effective teacher/classroom and
     * status: regular | modified | cancelled | moved_out | moved_in | suspended.
     *
     * @param array{group_id?: ?int, teacher_id?: ?int, classroom_id?: ?int} $filter
     * @return list<array<string, mixed>>
     */
    public function occurrences(array $semester, string $from, string $to, array $filter = []): array
    {
        $from = max($from, (string) $semester['start_date']);
        $to = min($to, (string) $semester['end_date']);
        if ($from > $to) {
            return [];
        }

        $entries = $this->repo->entries((int) $semester['id']);
        $byWeekday = [];
        foreach ($entries as $entry) {
            $byWeekday[(int) $entry['weekday']][] = $entry;
        }
        $entriesById = array_column($entries, null, 'id');

        $changes = [];
        $movedIn = [];
        foreach ($this->repo->changesInRange((int) $semester['id'], $from, $to) as $change) {
            $changes[(int) $change['schedule_entry_id'] . '@' . $change['lesson_date']] = $change;
            if ($change['change_type'] === 'reschedule' && $change['new_date'] >= $from && $change['new_date'] <= $to) {
                $movedIn[] = $change;
            }
        }
        $suspensions = $this->repo->suspendingEvents((int) $semester['academic_year_id'], $from, $to, (array) Config::get('schedule.suspending_event_types', []));
        $weekdays = (array) Config::get('schedule.weekdays', [1, 2, 3, 4, 5, 6]);

        $result = [];
        for ($day = new DateTimeImmutable($from); $day->format('Y-m-d') <= $to; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $weekday = (int) $day->format('N');
            if (!in_array($weekday, $weekdays, true)) {
                continue;
            }
            $parity = self::weekParity((string) $semester['start_date'], $date);
            foreach ($byWeekday[$weekday] ?? [] as $entry) {
                if (!self::paritiesOverlap($parity, (string) $entry['week_parity']) || ($entry['week_parity'] !== 'every' && $entry['week_parity'] !== $parity)) {
                    continue;
                }
                $occurrence = $this->baseOccurrence($entry, $date);
                $suspension = $this->suspensionFor($suspensions, $entry, $date);
                $change = $changes[$entry['id'] . '@' . $date] ?? null;
                if ($suspension !== null) {
                    $occurrence['status'] = 'suspended';
                    $occurrence['suspension'] = $suspension;
                } elseif ($change !== null) {
                    $occurrence = $this->applyChange($occurrence, $change);
                }
                $result[] = $occurrence;
            }
        }
        foreach ($movedIn as $change) {
            $entry = $entriesById[(int) $change['schedule_entry_id']] ?? null;
            if ($entry === null) {
                continue;
            }
            $occurrence = $this->applyChange($this->baseOccurrence($entry, (string) $change['new_date']), $change);
            $occurrence['status'] = 'moved_in';
            $occurrence['period_number'] = (int) $change['new_period_number'];
            $occurrence['lesson_period_id'] = (int) $change['new_lesson_period_id'];
            $occurrence['start_time'] = $change['new_start_time'];
            $occurrence['end_time'] = $change['new_end_time'];
            $occurrence['original_date'] = $change['lesson_date'];
            $result[] = $occurrence;
        }

        $result = array_values(array_filter($result, static function (array $o) use ($filter): bool {
            if (!empty($filter['group_id']) && (int) $o['group_id'] !== (int) $filter['group_id']) {
                return false;
            }
            if (!empty($filter['teacher_id']) && (int) $o['effective_teacher_id'] !== (int) $filter['teacher_id']
                && !((int) $o['teacher_id'] === (int) $filter['teacher_id'] && in_array($o['status'], ['cancelled', 'moved_out', 'suspended', 'modified'], true))) {
                return false;
            }
            if (!empty($filter['classroom_id']) && (int) $o['effective_classroom_id'] !== (int) $filter['classroom_id']) {
                return false;
            }

            return true;
        }));
        usort($result, static fn (array $a, array $b): int => [$a['date'], $a['period_number'], $a['group_code']] <=> [$b['date'], $b['period_number'], $b['group_code']]);

        return $result;
    }

    // ------------------------------------------------------------------ one-off changes

    /**
     * Change one occurrence of a regular entry on $lessonDate.
     *
     * @param array<string, mixed> $entry
     * @param array{change_type: string, new_teacher_id: ?int, new_classroom_id: ?int, new_date: ?string, new_lesson_period_id: ?int, reason: string} $data
     */
    public function changeOccurrence(array $entry, string $lessonDate, array $data): void
    {
        $semester = $this->repo->semester((int) $entry['semester_id']) ?? throw new RuntimeException('Semester not found.');
        if ($semester['year_status'] === 'archived') {
            throw new ValidationException(['reason' => [__('years.archived_readonly')]]);
        }
        $existing = $this->repo->changeFor((int) $entry['id'], $lessonDate);
        $isOccurrence = array_filter(
            $this->occurrences($semester, $lessonDate, $lessonDate, ['group_id' => (int) $entry['group_id']]),
            static fn (array $o): bool => (int) $o['entry_id'] === (int) $entry['id'] && $o['date'] === $lessonDate && $o['status'] !== 'moved_in'
        );
        if ($isOccurrence === []) {
            throw new ValidationException(['lesson_date' => [__('schedule.no_lesson_on_date')]]);
        }

        $type = $data['change_type'];
        $targetDate = $type === 'reschedule' ? (string) $data['new_date'] : $lessonDate;
        $targetPeriod = $type === 'reschedule' ? (int) $data['new_lesson_period_id'] : (int) $entry['lesson_period_id'];
        $teacherId = $data['new_teacher_id'] ?? (int) $entry['teacher_id'];
        $classroomId = $data['new_classroom_id'] ?? ($entry['classroom_id'] !== null ? (int) $entry['classroom_id'] : null);

        if ($type === 'modify' && $data['new_teacher_id'] === null && $data['new_classroom_id'] === null) {
            throw new ValidationException(['new_teacher_id' => [__('schedule.modify_empty')]]);
        }
        if ($type === 'reschedule') {
            if ($targetDate < $semester['start_date'] || $targetDate > $semester['end_date']) {
                throw new ValidationException(['new_date' => [__('schedule.outside_semester')]]);
            }
            $weekday = (int) (new DateTimeImmutable($targetDate))->format('N');
            if (!in_array($weekday, (array) Config::get('schedule.weekdays', []), true)) {
                throw new ValidationException(['new_date' => [__('schedule.not_teaching_day')]]);
            }
            if ($targetDate === $lessonDate && $targetPeriod === (int) $entry['lesson_period_id']) {
                throw new ValidationException(['new_date' => [__('schedule.reschedule_same')]]);
            }
        }
        if ($data['new_teacher_id'] !== null) {
            $teacher = $this->repo->teacher((int) $data['new_teacher_id']);
            if ($teacher === null || $teacher['status'] === 'dismissed') {
                throw new ValidationException(['new_teacher_id' => [__('schedule.teacher_unavailable')]]);
            }
        }

        $this->locked((int) $semester['id'], function () use ($entry, $lessonDate, $data, $type, $targetDate, $targetPeriod, $teacherId, $classroomId, $semester, $existing): void {
            if ($type !== 'cancel') {
                $conflicts = $this->occurrenceConflicts($semester, $entry, $lessonDate, $targetDate, $targetPeriod, $teacherId, $classroomId);
                if ($conflicts !== []) {
                    throw new ValidationException(['conflicts' => $conflicts]);
                }
            }
            $id = $this->repo->saveChange([
                'schedule_entry_id' => (int) $entry['id'],
                'lesson_date' => $lessonDate,
                'change_type' => $type,
                'new_teacher_id' => $type === 'cancel' ? null : $data['new_teacher_id'],
                'new_classroom_id' => $type === 'cancel' ? null : $data['new_classroom_id'],
                'new_date' => $type === 'reschedule' ? $targetDate : null,
                'new_lesson_period_id' => $type === 'reschedule' ? $targetPeriod : null,
                'reason' => $data['reason'],
                'created_by' => Auth::id(),
            ]);
            AuditLogger::log('schedule.occurrence_changed', 'schedule_changes', $id, $existing, ['entry' => (int) $entry['id'], 'date' => $lessonDate] + $data);
        });
    }

    /** @param array<string, mixed> $change */
    public function revertChange(array $change): void
    {
        $this->repo->deleteChange((int) $change['id']);
        AuditLogger::log('schedule.change_reverted', 'schedule_changes', (int) $change['id'], $change);
    }

    /**
     * Who/what is already busy at $date + $periodId, excluding the occurrence being changed.
     *
     * @param array<string, mixed> $semester
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private function occurrenceConflicts(array $semester, array $entry, string $lessonDate, string $date, int $periodId, int $teacherId, ?int $classroomId): array
    {
        $messages = [];
        foreach ($this->occurrences($semester, $date, $date) as $other) {
            $active = !in_array($other['status'], ['cancelled', 'moved_out', 'suspended'], true);
            $same = (int) $other['entry_id'] === (int) $entry['id'] && ($other['original_date'] ?? $other['date']) === $lessonDate;
            if (!$active || $same || (int) $other['lesson_period_id'] !== $periodId) {
                continue;
            }
            $where = $other['group_code'] . ', ' . format_date($date) . ', ' . __('schedule.period_n', ['n' => $other['period_number']]);
            if ((int) $other['effective_teacher_id'] === $teacherId) {
                $messages[] = __('schedule.conflict_teacher', ['teacher' => $other['effective_teacher_name'], 'where' => $where]);
            }
            if ($classroomId !== null && (int) $other['effective_classroom_id'] === $classroomId) {
                $messages[] = __('schedule.conflict_classroom', ['classroom' => $other['effective_classroom_label'], 'where' => $where]);
            }
            if ((int) $other['group_id'] === (int) $entry['group_id'] && !self::differentSubgroups($entry['subgroup'], $other['subgroup'])) {
                $messages[] = __('schedule.conflict_group', ['group' => $other['group_code'], 'where' => $where]);
            }
        }

        return array_values(array_unique($messages));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $semester
     */
    private function checkReferences(array $data, array $semester): void
    {
        $errors = [];
        $group = $this->repo->group((int) $data['group_id']);
        if ($group === null || $group['status'] !== 'active') {
            $errors['group_id'][] = __('schedule.group_inactive');
        }
        $teacher = $this->repo->teacher((int) $data['teacher_id']);
        if ($teacher === null || $teacher['status'] === 'dismissed') {
            $errors['teacher_id'][] = __('schedule.teacher_unavailable');
        }
        if ($data['classroom_id'] !== null) {
            $classroom = $this->repo->classroom((int) $data['classroom_id']);
            if ($classroom === null || (int) $classroom['is_active'] !== 1) {
                $errors['classroom_id'][] = __('schedule.classroom_inactive');
            }
        }
        $period = $this->repo->period((int) $data['lesson_period_id']);
        if ($period === null || (int) $period['is_active'] !== 1) {
            $errors['lesson_period_id'][] = __('schedule.period_inactive');
        }
        if (!in_array((int) $data['weekday'], (array) Config::get('schedule.weekdays', []), true)) {
            $errors['weekday'][] = __('schedule.not_teaching_day');
        }
        // If the group follows a curriculum, the subject must be in it for this semester of study.
        if ($group !== null && $group['curriculum_id'] !== null) {
            $planSemester = ((int) $group['course'] - 1) * 2 + (int) $semester['number'];
            if (!$this->repo->subjectInCurriculum((int) $group['curriculum_id'], (int) $data['subject_id'], $planSemester)) {
                $errors['subject_id'][] = __('schedule.subject_not_in_plan', ['semester' => $planSemester]);
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** @return array<string, mixed>|null the suspending event that applies to the entry's group on $date */
    private function suspensionFor(array $events, array $entry, string $date): ?array
    {
        foreach ($events as $event) {
            if ($event['start_date'] > $date || $event['end_date'] < $date) {
                continue;
            }
            $forGroup = $event['group_id'] !== null && (int) $event['group_id'] === (int) $entry['group_id'];
            $forCourse = $event['group_id'] === null && ($event['course'] === null || (int) $event['course'] === (int) $entry['course']);
            if ($forGroup || $forCourse) {
                return $event;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function baseOccurrence(array $entry, string $date): array
    {
        return $entry + [
            'entry_id' => (int) $entry['id'],
            'date' => $date,
            'status' => 'regular',
            'change' => null,
            'effective_teacher_id' => (int) $entry['teacher_id'],
            'effective_teacher_name' => $this->teacherName($entry),
            'effective_classroom_id' => $entry['classroom_id'] !== null ? (int) $entry['classroom_id'] : null,
            'effective_classroom_label' => $this->classroomLabel($entry),
        ];
    }

    /** @return array<string, mixed> */
    private function applyChange(array $occurrence, array $change): array
    {
        $occurrence['change'] = $change;
        $occurrence['status'] = match ($change['change_type']) {
            'cancel' => 'cancelled',
            'reschedule' => 'moved_out',
            default => 'modified',
        };
        if ($change['new_teacher_id'] !== null) {
            $occurrence['effective_teacher_id'] = (int) $change['new_teacher_id'];
            $occurrence['effective_teacher_name'] = full_name([
                'last_name' => $change['new_teacher_last_name'], 'first_name' => $change['new_teacher_first_name'], 'middle_name' => $change['new_teacher_middle_name'],
            ], true);
        }
        if ($change['new_classroom_id'] !== null) {
            $occurrence['effective_classroom_id'] = (int) $change['new_classroom_id'];
            $occurrence['effective_classroom_label'] = trim(($change['new_classroom_building'] !== '' ? $change['new_classroom_building'] . '-' : '') . $change['new_classroom_number']);
        }

        return $occurrence;
    }

    private function teacherName(array $row): string
    {
        return full_name(['last_name' => $row['teacher_last_name'], 'first_name' => $row['teacher_first_name'], 'middle_name' => $row['teacher_middle_name']], true);
    }

    private function classroomLabel(array $row): string
    {
        if ($row['classroom_number'] === null) {
            return '—';
        }

        return trim(((string) $row['classroom_building'] !== '' ? $row['classroom_building'] . '-' : '') . $row['classroom_number']);
    }

    private function describeEntry(array $row): string
    {
        return $row['group_code'] . ', ' . __('schedule.weekdays_short.' . $row['weekday']) . ', ' . __('schedule.period_n', ['n' => $row['period_number']])
            . ($row['week_parity'] !== 'every' ? ', ' . __('schedule.parities.' . $row['week_parity']) : '');
    }

    /**
     * Run a write under a per-semester named lock and a transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function locked(int $semesterId, callable $callback): mixed
    {
        $lock = 'collegeos_schedule_' . $semesterId;
        if ((int) Database::fetchColumn('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            throw new ValidationException(['conflicts' => [__('schedule.busy_try_again')]]);
        }
        try {
            return Database::transaction($callback);
        } finally {
            Database::fetchColumn('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
}
