<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\ExamRepository;
use App\Repositories\GradingSchemeRepository;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;

/**
 * Journal use cases: grid with computed results, cell edits with validation and
 * history, lessons, filling dates from the timetable, submit/approve/reopen.
 *
 * Cell input in a lesson column:
 *   number      — grade on the scheme's scale (comma or dot, max 2 decimals)
 *   н / n       — absent
 *   у / y       — absent for a valid reason
 *   empty       — clears grade and absence
 */
final class JournalService
{
    public const ABSENT_MARKS = ['н', 'n', 'Н', 'N'];
    public const EXCUSED_MARKS = ['у', 'y', 'У', 'Y'];

    /** @var array<int, array<string, mixed>|null> */
    private array $schemeCache = [];

    private array|false|null $finalTypeCache = false;

    public function __construct(
        private readonly JournalRepository $journals = new JournalRepository(),
        private readonly GradingSchemeRepository $schemes = new GradingSchemeRepository(),
    ) {
    }

    /** @return array<string, mixed>|null scheme with components */
    public function scheme(array $journal): ?array
    {
        $id = $journal['grading_scheme_id'] !== null ? (int) $journal['grading_scheme_id'] : 0;
        if (!array_key_exists($id, $this->schemeCache)) {
            $this->schemeCache[$id] = $id > 0 ? $this->schemes->find($id) : null;
        }

        return $this->schemeCache[$id];
    }

    /**
     * Full grid: lessons, students with cells and computed results.
     *
     * @return array{lessons: list<array<string, mixed>>, students: list<array<string, mixed>>, scheme: ?array, components: list<array<string, mixed>>}
     */
    public function grid(array $journal): array
    {
        $id = (int) $journal['id'];
        $scheme = $this->scheme($journal);
        $lessons = $this->journals->lessons($id);
        $grades = $this->journals->grades($id);
        $attendance = $this->journals->attendance($id);
        $assessments = $this->journals->assessments($id);

        $students = [];
        foreach ($this->journals->students($journal) as $student) {
            $sid = (int) $student['id'];
            $cells = [];
            $values = [];
            foreach ($lessons as $lesson) {
                $lid = (int) $lesson['id'];
                $grade = $grades[$lid][$sid] ?? null;
                $cells[$lid] = [
                    'grade' => $grade['value'] ?? null,
                    'attendance' => $attendance[$lid][$sid] ?? null,
                    'comment' => $grade['comment'] ?? null,
                    'member' => $this->isMemberOn($student, (string) $lesson['lesson_date']),
                ];
                $values[] = $grade['value'] ?? null;
            }
            $student['cells'] = $cells;
            $student['result'] = $this->compute($scheme, $values, $assessments[$sid] ?? [], array_column($cells, 'attendance'));
            $students[] = $student;
        }

        $examTypes = [];
        foreach ($scheme['components'] ?? [] as $component) {
            if ((new ExamRepository())->existsFor($id, (int) $component['assessment_type_id'])) {
                $examTypes[] = (int) $component['assessment_type_id'];
            }
        }

        return [
            'exam_types' => $examTypes,
            'lessons' => $lessons,
            'students' => $students,
            'scheme' => $scheme,
            'components' => $scheme['components'] ?? [],
        ];
    }

    /** Computed results of one student (used after every cell save). @return array<string, mixed> */
    public function studentResult(array $journal, int $studentId): array
    {
        $grid = $this->grid($journal);
        foreach ($grid['students'] as $student) {
            if ((int) $student['id'] === $studentId) {
                return $student['result'];
            }
        }

        return [];
    }

    /**
     * @param list<string|null> $lessonGrades
     * @param array<int, array<string, mixed>> $assessments assessment_type_id => row
     * @param list<string|null> $attendance
     * @return array<string, mixed>
     */
    private function compute(?array $scheme, array $lessonGrades, array $assessments, array $attendance): array
    {
        $current = GradeCalculator::average($lessonGrades);
        $components = [];
        $values = [];
        foreach ($scheme['components'] ?? [] as $component) {
            $value = $component['source'] === 'lessons_average'
                ? $current
                : (isset($assessments[(int) $component['assessment_type_id']]) ? (float) $assessments[(int) $component['assessment_type_id']]['value'] : null);
            $components[(int) $component['assessment_type_id']] = $value;
            $values[(string) $component['code']] = $value;
        }
        $final = $scheme !== null ? GradeCalculator::final($scheme, $values) : ['value' => null, 'missing' => []];
        $finalType = $this->finalType();
        $fixed = $finalType !== null && isset($assessments[(int) $finalType['id']]) ? (float) $assessments[(int) $finalType['id']]['value'] : null;

        return [
            'current' => $current,
            'components' => $components,
            'final' => $final['value'],
            'missing' => $final['missing'],
            'fixed_final' => $fixed,
            'passed' => $scheme !== null ? GradeCalculator::passed($scheme, $final['value']) : null,
            'absent' => count(array_filter($attendance, static fn ($a) => $a === 'absent')),
            'excused' => count(array_filter($attendance, static fn ($a) => $a === 'excused')),
        ];
    }

    private function isMemberOn(array $student, string $date): bool
    {
        return $student['member_from'] <= $date && ($student['member_to'] === null || $student['member_to'] >= $date);
    }

    // ------------------------------------------------------------------ cell edits

    /** @return array<string, mixed> updated result of the student */
    public function setLessonCell(array $journal, array $lesson, int $studentId, string $raw): array
    {
        $this->assertStudent($journal, $studentId);
        // A mark can only be given for a date on which the student belonged to the group.
        if (!$this->journals->studentMemberOn($journal, $studentId, (string) $lesson['lesson_date'])) {
            throw new ValidationException(['value' => [__('journal.not_member_on_date')]]);
        }
        [$grade, $absence] = $this->parseCell($journal, $raw);

        $oldGrade = $this->journals->gradeCell((int) $lesson['id'], $studentId);
        $oldAbsence = $this->journals->attendanceCell((int) $lesson['id'], $studentId);
        $oldValue = $oldGrade['value'] ?? null;

        Database::transaction(function () use ($journal, $lesson, $studentId, $grade, $absence, $oldGrade, $oldAbsence, $oldValue): void {
            $userId = Auth::id();
            if (self::normalize($oldValue) !== self::normalize($grade)) {
                $this->journals->saveGrade((int) $lesson['id'], $studentId, $grade, $oldGrade['comment'] ?? null, $userId);
                $this->log($journal, $studentId, 'grade', self::normalize($oldValue), self::normalize($grade), (int) $lesson['id']);
            }
            if ($oldAbsence !== $absence) {
                $this->journals->saveAttendance((int) $lesson['id'], $studentId, $absence, $userId);
                $this->log($journal, $studentId, 'attendance', $oldAbsence, $absence, (int) $lesson['id']);
            }
            $this->refixFinalIfApproved($journal, $studentId);
        });

        return $this->studentResult($journal, $studentId);
    }

    /** @return array<string, mixed> */
    public function setComment(array $journal, array $lesson, int $studentId, string $comment): array
    {
        $this->assertStudent($journal, $studentId);
        $comment = trim($comment);
        if (mb_strlen($comment) > 255) {
            throw new ValidationException(['comment' => [__('validation.max', ['field' => __('fields.comment'), 'max' => 255])]]);
        }
        $old = $this->journals->gradeCell((int) $lesson['id'], $studentId);
        if (($old['comment'] ?? '') !== $comment) {
            $this->journals->saveGrade((int) $lesson['id'], $studentId, $old['value'] ?? null, $comment, Auth::id());
            $this->log($journal, $studentId, 'comment', $old['comment'] ?? null, $comment !== '' ? $comment : null, (int) $lesson['id']);
        }

        return $this->studentResult($journal, $studentId);
    }

    /** Manual component (rating, exam…). @return array<string, mixed> */
    public function setAssessment(array $journal, int $typeId, int $studentId, string $raw): array
    {
        $this->assertStudent($journal, $studentId);
        $scheme = $this->scheme($journal) ?? throw new ValidationException(['value' => [__('journal.no_scheme')]]);
        $component = null;
        foreach ($scheme['components'] as $c) {
            if ((int) $c['assessment_type_id'] === $typeId) {
                $component = $c;
            }
        }
        if ($component === null || $component['source'] !== 'manual') {
            throw new ValidationException(['value' => [__('journal.not_manual_component')]]);
        }
        // Components with a statement (rating/exam event) are filled only through it.
        if ((new ExamRepository())->existsFor((int) $journal['id'], $typeId)) {
            throw new ValidationException(['value' => [__('journal.via_statement')]]);
        }
        $raw = trim(str_replace(',', '.', $raw));
        $value = $raw === '' ? null : $this->validateGrade($scheme, $raw);
        $old = $this->journals->assessments((int) $journal['id'])[$studentId][$typeId]['value'] ?? null;

        if (self::normalize($old) !== self::normalize($value)) {
            Database::transaction(function () use ($journal, $typeId, $studentId, $value, $old): void {
                $this->journals->saveAssessment((int) $journal['id'], $studentId, $typeId, $value, null, Auth::id());
                $this->log($journal, $studentId, 'assessment', self::normalize($old), self::normalize($value), null, $typeId);
                $this->refixFinalIfApproved($journal, $studentId);
            });
        }

        return $this->studentResult($journal, $studentId);
    }

    /** @return array{0: ?string, 1: ?string} [grade, absence] */
    private function parseCell(array $journal, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, null];
        }
        if (in_array($raw, self::ABSENT_MARKS, true)) {
            return [null, 'absent'];
        }
        if (in_array($raw, self::EXCUSED_MARKS, true)) {
            return [null, 'excused'];
        }
        $scheme = $this->scheme($journal) ?? throw new ValidationException(['value' => [__('journal.no_scheme')]]);

        return [$this->validateGrade($scheme, str_replace(',', '.', $raw)), null];
    }

    private function validateGrade(array $scheme, string $value): string
    {
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value)) {
            throw new ValidationException(['value' => [__('journal.invalid_value')]]);
        }
        if ((float) $value < (float) $scheme['grade_min'] || (float) $value > (float) $scheme['grade_max']) {
            throw new ValidationException(['value' => [__('journal.out_of_scale', ['min' => self::normalize($scheme['grade_min']), 'max' => self::normalize($scheme['grade_max'])])]]);
        }

        return $value;
    }

    private function assertStudent(array $journal, int $studentId): void
    {
        if (!$this->journals->studentBelongs($journal, $studentId)) {
            throw new ValidationException(['student_id' => [__('journal.student_not_in_group')]]);
        }
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------ lessons

    /** @param array<string, mixed> $data */
    public function saveLesson(array $journal, ?array $lesson, array $data): int
    {
        if ($data['lesson_date'] < $journal['semester_start'] || $data['lesson_date'] > $journal['semester_end']) {
            throw new ValidationException(['lesson_date' => [__('schedule.outside_semester')]]);
        }
        if ($this->journals->lessonSlotTaken((int) $journal['id'], $data['lesson_date'], (int) $data['lesson_period_id'], $lesson !== null ? (int) $lesson['id'] : null)) {
            throw new ValidationException(['lesson_date' => [__('journal.lesson_exists')]]);
        }
        $id = $this->journals->saveLesson((int) $journal['id'], $lesson !== null ? (int) $lesson['id'] : null, $data + ['created_by' => Auth::id()]);
        AuditLogger::log($lesson === null ? 'journal.lesson_added' : 'journal.lesson_updated', 'journal_lessons', $id, $lesson, $data);

        return $id;
    }

    public function deleteLesson(array $journal, array $lesson): void
    {
        $marks = $this->journals->lessonHasMarks((int) $lesson['id']);
        $this->journals->deleteLesson((int) $journal['id'], (int) $lesson['id']);
        AuditLogger::log('journal.lesson_deleted', 'journal_lessons', (int) $lesson['id'], $lesson + ['marks_removed' => $marks]);
        if ($marks > 0) {
            $this->log($journal, null, 'status', 'lesson ' . $lesson['lesson_date'], 'deleted (' . $marks . ')');
        }
    }

    /**
     * Create journal lessons from the timetable: occurrences of this group + subject that
     * take place (regular, substituted or moved in) up to $until. Existing dates are kept.
     */
    public function fillFromSchedule(array $journal, string $until): int
    {
        $scheduleRepo = new ScheduleRepository();
        $semester = $scheduleRepo->semester((int) $journal['semester_id']);
        if ($semester === null) {
            return 0;
        }
        $to = min($until, (string) $journal['semester_end']);
        $created = 0;
        $occurrences = (new ScheduleService($scheduleRepo))->occurrences($semester, (string) $journal['semester_start'], $to, ['group_id' => (int) $journal['group_id']]);
        foreach ($occurrences as $o) {
            if ((int) $o['subject_id'] !== (int) $journal['subject_id'] || !in_array($o['status'], ['regular', 'modified', 'moved_in'], true)) {
                continue;
            }
            if ($this->journals->lessonSlotTaken((int) $journal['id'], (string) $o['date'], (int) $o['lesson_period_id'], null)) {
                continue;
            }
            $this->journals->saveLesson((int) $journal['id'], null, [
                'lesson_date' => $o['date'], 'lesson_period_id' => (int) $o['lesson_period_id'], 'lesson_type' => $o['lesson_type'],
                'topic' => null, 'homework' => null, 'note' => $o['subgroup'] !== null ? __('schedule.subgroup_n', ['n' => $o['subgroup']]) : null,
                'hours' => 2, 'conducted_by' => (int) $o['effective_teacher_id'], 'schedule_entry_id' => (int) $o['entry_id'], 'created_by' => Auth::id(),
            ]);
            $created++;
        }
        AuditLogger::log('journal.filled', 'teacher_subject_assignments', (int) $journal['id'], null, ['created' => $created, 'until' => $to]);

        return $created;
    }

    /**
     * Create journals for every group + subject of the semester timetable.
     * $respectScope = false only for trusted CLI seeding (no signed-in user).
     */
    public function generateFromTimetable(int $semesterId, ?int $schemeId, bool $respectScope = true): int
    {
        $created = 0;
        foreach ($this->journals->timetablePairs($semesterId) as $pair) {
            if (($respectScope && !AccessScope::allowsGroup((int) $pair['group_id'])) || $this->journals->exists($semesterId, (int) $pair['group_id'], (int) $pair['subject_id'])) {
                continue;
            }
            $this->journals->create([
                'semester_id' => $semesterId, 'group_id' => (int) $pair['group_id'], 'subject_id' => (int) $pair['subject_id'],
                'teacher_id' => (int) $pair['teacher_id'], 'grading_scheme_id' => $schemeId,
            ]);
            $created++;
        }
        AuditLogger::log('journal.generated', 'semesters', $semesterId, null, ['created' => $created, 'scheme' => $schemeId]);

        return $created;
    }

    // ------------------------------------------------------------------ workflow

    public function submit(array $journal): void
    {
        $this->journals->setStatus((int) $journal['id'], 'submitted', null);
        $this->log($journal, null, 'status', $journal['status'], 'submitted');
    }

    /** Approve and fix every student's final together with the scheme version. */
    public function approve(array $journal): void
    {
        $scheme = $this->scheme($journal);
        if ($scheme === null) {
            throw new ValidationException(['status' => [__('journal.no_scheme')]]);
        }
        Database::transaction(function () use ($journal): void {
            $this->journals->setStatus((int) $journal['id'], 'approved', Auth::id());
            $approved = ['status' => 'approved'] + $journal;
            foreach ($this->journals->students($journal) as $student) {
                $this->refixFinalIfApproved($approved, (int) $student['id']);
            }
            $this->log($journal, null, 'status', $journal['status'], 'approved');
        });
        AuditLogger::log('journal.approved', 'teacher_subject_assignments', (int) $journal['id']);
    }

    public function reopen(array $journal, string $reason): void
    {
        Database::transaction(function () use ($journal, $reason): void {
            $this->journals->setStatus((int) $journal['id'], 'open', null);
            $finalType = $this->finalType();
            if ($finalType !== null) {
                $this->journals->deleteFinals((int) $journal['id'], (int) $finalType['id']);
            }
            $this->log($journal, null, 'status', $journal['status'], 'open: ' . $reason);
        });
        AuditLogger::log('journal.reopened', 'teacher_subject_assignments', (int) $journal['id'], ['status' => $journal['status']], ['reason' => $reason]);
    }

    /** Public entry for other modules (statements, retakes): re-fix the official final if approved. */
    public function refixFinal(array $journal, int $studentId): void
    {
        $this->refixFinalIfApproved($journal, $studentId);
    }

    /** In an approved journal the official final follows the current data (with scheme version). */
    private function refixFinalIfApproved(array $journal, int $studentId): void
    {
        if ($journal['status'] !== 'approved') {
            return;
        }
        $finalType = $this->finalType();
        $scheme = $this->scheme($journal);
        if ($finalType === null || $scheme === null) {
            return;
        }
        $result = $this->studentResult(['status' => 'open'] + $journal, $studentId);
        $old = $this->journals->assessments((int) $journal['id'])[$studentId][(int) $finalType['id']]['value'] ?? null;
        $new = $result['final'] !== null ? (string) $result['final'] : null;
        if (self::normalize($old) !== self::normalize($new)) {
            $this->journals->saveAssessment((int) $journal['id'], $studentId, (int) $finalType['id'], $new, (int) $scheme['id'], Auth::id());
            $this->log($journal, $studentId, 'final', self::normalize($old), self::normalize($new), null, (int) $finalType['id']);
        }
    }

    /** @return array<string, mixed>|null the system "final" assessment type */
    private function finalType(): ?array
    {
        if ($this->finalTypeCache === false) {
            $this->finalTypeCache = $this->journals->assessmentType('final');
        }

        return $this->finalTypeCache;
    }

    private function log(array $journal, ?int $studentId, string $field, ?string $old, ?string $new, ?int $lessonId = null, ?int $typeId = null): void
    {
        $this->journals->logChange([
            'assignment_id' => (int) $journal['id'], 'student_id' => $studentId, 'journal_lesson_id' => $lessonId,
            'assessment_type_id' => $typeId, 'field' => $field, 'old_value' => $old, 'new_value' => $new, 'changed_by' => Auth::id(),
        ]);
    }
}
