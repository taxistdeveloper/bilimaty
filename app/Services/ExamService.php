<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\ExamRepository;
use App\Repositories\JournalRepository;

/**
 * Ratings and exams: events, statements, closing (sync into the journal component)
 * and retakes with history.
 *
 * The statement (exam_results) holds admission, attendance and value. Closing the
 * statement copies values into student_assessments — the journal component used by the
 * final grade. After closing, the result changes only through a retake, which is logged
 * (retakes + grade_change_history) and re-fixes the official final in an approved journal.
 */
final class ExamService
{
    public const FORMS = ['written', 'oral', 'test', 'computer_test', 'practical', 'project', 'combined'];

    public function __construct(
        private readonly ExamRepository $exams = new ExamRepository(),
        private readonly JournalRepository $journals = new JournalRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $data validated event data
     * @param array<string, mixed>|null $exam existing event
     */
    public function save(array $data, ?array $exam = null): int
    {
        $journal = $this->journals->find((int) ($exam['assignment_id'] ?? $data['assignment_id'])) ?? throw new ValidationException(['assignment_id' => [__('exams.journal_missing')]]);
        $typeId = (int) ($exam['assessment_type_id'] ?? $data['assessment_type_id']);
        if ($exam !== null && $exam['status'] === 'closed') {
            throw new ValidationException(['exam_date' => [__('exams.closed_readonly')]]);
        }
        if ($journal['year_status'] === 'archived') {
            throw new ValidationException(['exam_date' => [__('years.archived_readonly')]]);
        }
        $errors = [];
        $scheme = (new JournalService($this->journals))->scheme($journal);
        $component = null;
        foreach ($scheme['components'] ?? [] as $c) {
            if ((int) $c['assessment_type_id'] === $typeId) {
                $component = $c;
            }
        }
        if ($component === null || $component['source'] !== 'manual') {
            $errors['assessment_type_id'][] = __('exams.type_not_in_scheme');
        }
        if ($exam === null && $this->exams->existsFor((int) $journal['id'], $typeId)) {
            $errors['assessment_type_id'][] = __('exams.already_exists');
        }
        $yearStart = (string) Database::fetchColumn('SELECT start_date FROM academic_years WHERE id = ?', [$journal['academic_year_id']]);
        $yearEnd = (string) Database::fetchColumn('SELECT end_date FROM academic_years WHERE id = ?', [$journal['academic_year_id']]);
        if ($data['exam_date'] < $journal['semester_start'] || $data['exam_date'] > $yearEnd) {
            $errors['exam_date'][] = __('exams.date_range', ['from' => format_date((string) $journal['semester_start']), 'to' => format_date($yearEnd)]);
        }
        if ($data['period_start'] !== null && ($data['period_start'] > $data['exam_date'] || $data['period_start'] < $yearStart)) {
            $errors['period_start'][] = __('exams.period_invalid');
        }
        if ($errors === []) {
            foreach ($this->exams->clashes((int) $journal['group_id'], $data['exam_date'], $data['start_time'], (int) $data['examiner_teacher_id'], $data['classroom_id'], $exam !== null ? (int) $exam['id'] : null) as $other) {
                $what = localized(['name_ru' => $other['subject_name_ru'], 'name_kk' => $other['subject_name_kk']]) . ' (' . $other['group_code'] . ')';
                if ((int) $other['group_id'] === (int) $journal['group_id']) {
                    $errors['conflicts'][] = __('exams.clash_group', ['what' => $what]);
                } elseif ((int) $other['examiner_teacher_id'] === (int) $data['examiner_teacher_id']) {
                    $errors['conflicts'][] = __('exams.clash_examiner', ['what' => $what]);
                } else {
                    $errors['conflicts'][] = __('exams.clash_classroom', ['what' => $what]);
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($exam === null) {
            $id = $this->exams->create($data + [
                'assignment_id' => (int) $journal['id'],
                'assessment_type_id' => $typeId,
                'statement_number' => $this->exams->nextStatementNumber((int) substr((string) $data['exam_date'], 0, 4)),
                'created_by' => Auth::id(),
            ]);
            AuditLogger::log('exams.created', 'exams', $id, null, $data + ['assignment_id' => (int) $journal['id'], 'type' => $typeId]);

            return $id;
        }
        $this->exams->update((int) $exam['id'], $data);
        AuditLogger::log('exams.updated', 'exams', (int) $exam['id'], array_intersect_key($exam, $data), $data);

        return (int) $exam['id'];
    }

    /**
     * Statement rows of the event: students of the journal with their result.
     *
     * @return list<array<string, mixed>>
     */
    public function statement(array $exam): array
    {
        $journal = $this->journals->find((int) $exam['assignment_id']);
        $results = $this->exams->results((int) $exam['id']);
        $rows = [];
        foreach ($this->journals->students($journal) as $student) {
            $result = $results[(int) $student['id']] ?? null;
            $student['admission'] = $result['admission'] ?? 'admitted';
            $student['attendance'] = $result['attendance'] ?? null;
            $student['value'] = $result['value'] ?? null;
            $student['note'] = $result['note'] ?? null;
            $student['member_on_exam'] = $this->journals->studentMemberOn($journal, (int) $student['id'], (string) $exam['exam_date']);
            $rows[] = $student;
        }

        return $rows;
    }

    /**
     * Save the statement (all rows at once).
     *
     * @param array<int, array{admission?: string, attendance?: string, value?: string, note?: string}> $rows student_id => row
     */
    public function saveStatement(array $exam, array $rows): void
    {
        if ($exam['status'] === 'closed') {
            throw new ValidationException(['rows' => [__('exams.closed_readonly')]]);
        }
        $journal = $this->journals->find((int) $exam['assignment_id']);
        $scheme = (new JournalService($this->journals))->scheme($journal) ?? throw new ValidationException(['rows' => [__('journal.no_scheme')]]);
        $students = array_column($this->statement($exam), null, 'id');
        $errors = [];
        $clean = [];
        foreach ($rows as $studentId => $row) {
            $studentId = (int) $studentId;
            if (!isset($students[$studentId])) {
                continue; // ignore foreign ids silently: they are not part of this statement
            }
            $name = full_name($students[$studentId], true);
            $admission = ($row['admission'] ?? 'admitted') === 'not_admitted' ? 'not_admitted' : 'admitted';
            $attendance = in_array($row['attendance'] ?? '', ['present', 'absent', 'excused'], true) ? $row['attendance'] : null;
            $raw = trim(str_replace(',', '.', (string) ($row['value'] ?? '')));
            $note = trim((string) ($row['note'] ?? '')) ?: null;
            $value = null;
            if ($raw !== '') {
                if ($admission !== 'admitted' || $attendance !== 'present') {
                    $errors['rows'][] = __('exams.value_requires_presence', ['name' => $name]);
                    continue;
                }
                if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw) || (float) $raw < (float) $scheme['grade_min'] || (float) $raw > (float) $scheme['grade_max']) {
                    $errors['rows'][] = __('exams.value_invalid', ['name' => $name, 'min' => JournalService::normalize($scheme['grade_min']), 'max' => JournalService::normalize($scheme['grade_max'])]);
                    continue;
                }
                $value = $raw;
            }
            if ($admission === 'not_admitted') {
                $attendance = null;
            }
            $clean[$studentId] = [$admission, $attendance, $value, $note !== null ? mb_substr($note, 0, 255) : null];
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        Database::transaction(function () use ($exam, $clean): void {
            $current = $this->exams->results((int) $exam['id']);
            foreach ($clean as $studentId => [$admission, $attendance, $value, $note]) {
                $old = $current[$studentId] ?? null;
                if ($old !== null && $old['admission'] === $admission && $old['attendance'] === $attendance
                    && JournalService::normalize($old['value']) === JournalService::normalize($value) && $old['note'] === $note) {
                    continue;
                }
                if ($old === null && $admission === 'admitted' && $attendance === null && $value === null && $note === null) {
                    continue;
                }
                $this->exams->saveResult((int) $exam['id'], $studentId, $admission, $attendance, $value, $note, Auth::id());
            }
            if ($exam['status'] === 'planned') {
                $this->exams->setStatus((int) $exam['id'], 'open', null);
            }
        });
        AuditLogger::log('exams.statement_saved', 'exams', (int) $exam['id'], null, ['rows' => count($clean)]);
    }

    /** Close the statement and transfer the values into the journal component. */
    public function close(array $exam): void
    {
        if ($exam['status'] === 'closed') {
            return;
        }
        $missing = [];
        foreach ($this->statement($exam) as $row) {
            if ($row['member_on_exam'] && $row['admission'] === 'admitted' && $row['attendance'] === null) {
                $missing[] = full_name($row, true);
            }
        }
        if ($missing !== []) {
            throw new ValidationException(['rows' => [__('exams.attendance_missing', ['names' => implode(', ', $missing)])]]);
        }
        $journal = $this->journals->find((int) $exam['assignment_id']);
        $journalService = new JournalService($this->journals);
        Database::transaction(function () use ($exam, $journal, $journalService): void {
            foreach ($this->exams->results((int) $exam['id']) as $studentId => $result) {
                $this->syncToJournal($journal, $journalService, (int) $exam['assessment_type_id'], (int) $studentId, $result['value'], 'statement ' . $exam['statement_number']);
            }
            $this->exams->setStatus((int) $exam['id'], 'closed', Auth::id());
        });
        AuditLogger::log('exams.closed', 'exams', (int) $exam['id']);
    }

    public function reopen(array $exam, string $reason): void
    {
        $this->exams->setStatus((int) $exam['id'], 'open', null);
        AuditLogger::log('exams.reopened', 'exams', (int) $exam['id'], null, ['reason' => $reason]);
    }

    public function delete(array $exam): void
    {
        if ($exam['status'] !== 'planned' || (int) $exam['graded_count'] > 0) {
            throw new ValidationException(['status' => [__('exams.cannot_delete')]]);
        }
        $this->exams->delete((int) $exam['id']);
        AuditLogger::log('exams.deleted', 'exams', (int) $exam['id'], ['statement' => $exam['statement_number']]);
    }

    // ------------------------------------------------------------------ retakes

    /** @param array{student_id: int, retake_date: string, examiner_teacher_id: int, reason: string, order_number: ?string} $data */
    public function scheduleRetake(array $exam, array $data): int
    {
        if ($exam['status'] !== 'closed') {
            throw new ValidationException(['student_id' => [__('exams.retake_needs_closed')]]);
        }
        $result = $this->exams->result((int) $exam['id'], $data['student_id']);
        $row = array_values(array_filter($this->statement($exam), static fn (array $s): bool => (int) $s['id'] === $data['student_id']));
        if ($row === []) {
            throw new ValidationException(['student_id' => [__('journal.student_not_in_group')]]);
        }
        if ($this->exams->hasScheduledRetake((int) $exam['id'], $data['student_id'])) {
            throw new ValidationException(['student_id' => [__('exams.retake_pending')]]);
        }
        if ($data['retake_date'] < $exam['exam_date']) {
            throw new ValidationException(['retake_date' => [__('exams.retake_date_before')]]);
        }
        $id = $this->exams->createRetake($data + [
            'exam_id' => (int) $exam['id'],
            'attempt_no' => $this->exams->nextAttempt((int) $exam['id'], $data['student_id']),
            'old_value' => $result['value'] ?? null,
            'created_by' => Auth::id(),
        ]);
        AuditLogger::log('exams.retake_scheduled', 'retakes', $id, null, $data);

        return $id;
    }

    /** Record the retake result (value, or absence) and update statement + journal. */
    public function completeRetake(array $exam, array $retake, ?string $value, bool $absent): void
    {
        if ($retake['status'] !== 'scheduled') {
            throw new ValidationException(['value' => [__('exams.retake_not_scheduled')]]);
        }
        $journal = $this->journals->find((int) $exam['assignment_id']);
        $scheme = (new JournalService($this->journals))->scheme($journal);
        if (!$absent) {
            $value = trim(str_replace(',', '.', (string) $value));
            if ($scheme === null || !preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value) || (float) $value < (float) $scheme['grade_min'] || (float) $value > (float) $scheme['grade_max']) {
                throw new ValidationException(['value' => [__('journal.out_of_scale', ['min' => JournalService::normalize($scheme['grade_min'] ?? 0), 'max' => JournalService::normalize($scheme['grade_max'] ?? 0)])]]);
            }
        }
        $studentId = (int) $retake['student_id'];
        $journalService = new JournalService($this->journals);
        Database::transaction(function () use ($exam, $retake, $journal, $journalService, $studentId, $value, $absent): void {
            if ($absent) {
                $this->exams->finishRetake((int) $retake['id'], 'completed', null);
                $this->journals->logChange([
                    'assignment_id' => (int) $journal['id'], 'student_id' => $studentId, 'assessment_type_id' => (int) $exam['assessment_type_id'],
                    'field' => 'assessment', 'old_value' => JournalService::normalize($retake['old_value']), 'new_value' => 'retake ' . $retake['attempt_no'] . ': absent', 'changed_by' => Auth::id(),
                ]);

                return;
            }
            $old = $this->exams->result((int) $exam['id'], $studentId);
            $this->exams->saveResult((int) $exam['id'], $studentId, 'admitted', 'present', $value, ($old['note'] ?? null), Auth::id());
            $this->exams->finishRetake((int) $retake['id'], 'completed', $value);
            $this->syncToJournal($journal, $journalService, (int) $exam['assessment_type_id'], $studentId, $value, 'retake ' . $retake['attempt_no']);
        });
        AuditLogger::log('exams.retake_completed', 'retakes', (int) $retake['id'], ['old' => $retake['old_value']], ['new' => $absent ? 'absent' : $value]);
    }

    public function cancelRetake(array $retake): void
    {
        if ($retake['status'] !== 'scheduled') {
            return;
        }
        $this->exams->finishRetake((int) $retake['id'], 'cancelled', null);
        AuditLogger::log('exams.retake_cancelled', 'retakes', (int) $retake['id']);
    }

    /** Copy a result into the journal component; log it; keep the official final up to date. */
    private function syncToJournal(array $journal, JournalService $journalService, int $typeId, int $studentId, mixed $value, string $source): void
    {
        $old = $this->journals->assessments((int) $journal['id'])[$studentId][$typeId]['value'] ?? null;
        $new = $value !== null && $value !== '' ? (string) $value : null;
        if (JournalService::normalize($old) === JournalService::normalize($new)) {
            return;
        }
        $this->journals->saveAssessment((int) $journal['id'], $studentId, $typeId, $new, null, Auth::id());
        $this->journals->logChange([
            'assignment_id' => (int) $journal['id'], 'student_id' => $studentId, 'assessment_type_id' => $typeId, 'field' => 'assessment',
            'old_value' => JournalService::normalize($old), 'new_value' => trim((JournalService::normalize($new) ?? '—') . ' (' . $source . ')'), 'changed_by' => Auth::id(),
        ]);
        $journalService->refixFinal($journal, $studentId);
    }
}
