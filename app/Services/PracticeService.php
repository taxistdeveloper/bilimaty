<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\PracticeRepository;

/**
 * Practice schedules ("График программы прохождения практики").
 *
 *   assigned ──save──▶ in_progress ──submit──▶ submitted ──approve──▶ approved
 *                                                 └──return (reason)──▶ returned ──save/submit──▶ …
 *
 * Hours of a row = end time − start time (astronomical hours, one decimal). A row is counted only on a
 * working day: Saturday, Sunday and the public holidays of the academic calendar are not counted.
 * The schedule can be submitted only when every row is complete, falls on a working day, rows do not
 * overlap, and the counted hours equal the assigned hours exactly. A draft can be saved at any time.
 */
final class PracticeService
{
    public const TYPES = ['educational', 'production', 'pregraduation'];
    public const STATUSES = ['assigned', 'in_progress', 'submitted', 'approved', 'returned'];
    /** Statuses in which the supervisor edits the schedule. */
    public const EDITABLE = ['assigned', 'in_progress', 'returned'];
    public const MAX_ROWS = 200;

    public function __construct(private readonly PracticeRepository $repository = new PracticeRepository())
    {
    }

    // ---- assignment (academic office) -----------------------------------------

    /** @return array<string, string> validation rules of an assignment */
    public static function rules(): array
    {
        return [
            'teacher_id' => 'required|integer|exists:teachers,id',
            'group_id' => 'required|integer|exists:groups,id',
            'title' => 'required|string|max:255',
            'practice_type' => 'required|in:' . implode(',', self::TYPES),
            'module_name' => 'nullable|string|max:255',
            'hours' => 'required|integer|between:1,2000',
            'deadline' => 'required|date',
        ];
    }

    /** @param array<string, mixed> $data validated (rules()) */
    public function save(array $data, ?array $assignment): int
    {
        if ($assignment !== null && $assignment['status'] === 'approved') {
            throw new ValidationException(['title' => [__('practice.approved_readonly')]]);
        }
        if (!AccessScope::allowsGroup((int) $data['group_id'])) {
            throw new ValidationException(['group_id' => [__('practice.group_out_of_scope')]]);
        }
        $data['module_name'] = ($data['module_name'] ?? '') !== '' ? $data['module_name'] : null;
        if ($assignment === null) {
            $id = $this->repository->create($data + ['created_by' => Auth::id()]);
            AuditLogger::log('practice.assigned', 'practice_assignments', $id, null, $data);
            $this->notifyTeacher($this->repository->find($id) ?? [], 'practice.notify.assigned_title', 'practice.notify.assigned_body');

            return $id;
        }
        $this->repository->update((int) $assignment['id'], $data);
        AuditLogger::log('practice.updated', 'practice_assignments', (int) $assignment['id'], array_intersect_key($assignment, $data), $data);

        return (int) $assignment['id'];
    }

    public function delete(array $assignment): void
    {
        if ($assignment['status'] === 'approved') {
            throw new ValidationException(['status' => [__('practice.approved_readonly')]]);
        }
        $this->repository->delete((int) $assignment['id']);
        AuditLogger::log('practice.deleted', 'practice_assignments', (int) $assignment['id'], ['title' => $assignment['title'], 'group' => $assignment['group_code']], null);
    }

    public function approve(array $assignment): void
    {
        if ($assignment['status'] !== 'submitted') {
            throw new ValidationException(['status' => [__('practice.wrong_status')]]);
        }
        $this->repository->update((int) $assignment['id'], ['status' => 'approved', 'decided_at' => date('Y-m-d H:i:s'), 'decided_by' => Auth::id(), 'return_reason' => null]);
        AuditLogger::log('practice.approved', 'practice_assignments', (int) $assignment['id'], ['status' => 'submitted'], ['status' => 'approved']);
        $this->notifyTeacher($assignment, 'practice.notify.approved_title', 'practice.notify.approved_body');
    }

    public function returnForRework(array $assignment, string $reason): void
    {
        $reason = trim($reason);
        if (!in_array($assignment['status'], ['submitted', 'approved'], true)) {
            throw new ValidationException(['status' => [__('practice.wrong_status')]]);
        }
        if ($reason === '') {
            throw new ValidationException(['reason' => [__('practice.reason_required')]]);
        }
        $this->repository->update((int) $assignment['id'], ['status' => 'returned', 'return_reason' => mb_substr($reason, 0, 1000),
            'decided_at' => date('Y-m-d H:i:s'), 'decided_by' => Auth::id()]);
        AuditLogger::log('practice.returned', 'practice_assignments', (int) $assignment['id'], ['status' => $assignment['status']], ['status' => 'returned', 'reason' => $reason]);
        $this->notifyTeacher($assignment, 'practice.notify.returned_title', 'practice.notify.returned_body', ['reason' => $reason]);
    }

    // ---- schedule (supervising teacher) -----------------------------------------

    /**
     * Save the schedule as a draft, or submit it.
     *
     * @param list<array<string, mixed>> $rows posted rows: topic, date, time_start, time_end, place, is_distance
     */
    public function saveSchedule(array $assignment, array $rows, string $comment, bool $submit): void
    {
        if (!in_array($assignment['status'], self::EDITABLE, true)) {
            throw new ValidationException(['entries' => [__('practice.not_editable')]]);
        }
        $entries = $this->parse($rows);
        $summary = $this->summary($entries, (int) $assignment['hours']);
        if ($submit) {
            $errors = $this->submitErrors($entries, $summary);
            if ($errors !== []) {
                throw new ValidationException(['entries' => $errors]);
            }
        }
        $status = match (true) {
            $submit => 'submitted',
            $entries !== [] => 'in_progress',
            default => $assignment['status'] === 'returned' ? 'returned' : 'assigned',
        };
        Database::transaction(function () use ($assignment, $entries, $comment, $status, $submit): void {
            $this->repository->replaceEntries((int) $assignment['id'], $entries);
            $this->repository->update((int) $assignment['id'], ['status' => $status, 'teacher_comment' => trim($comment) !== '' ? mb_substr(trim($comment), 0, 1000) : null]
                + ($submit ? ['submitted_at' => date('Y-m-d H:i:s')] : []));
        });
        AuditLogger::log($submit ? 'practice.submitted' : 'practice.draft_saved', 'practice_assignments', (int) $assignment['id'], null,
            ['rows' => count($entries), 'counted_hours' => $summary['counted'], 'status' => $status]);
        if ($submit && $assignment['created_by'] !== null) {
            Notifier::send([(int) $assignment['created_by']], 'practice', 'practice.notify.submitted_title', 'practice.notify.submitted_body',
                ['title' => (string) $assignment['title'], 'group' => (string) $assignment['group_code'],
                 'teacher' => full_name(['last_name' => $assignment['teacher_last_name'], 'first_name' => $assignment['teacher_first_name'], 'middle_name' => $assignment['teacher_middle_name']], true)],
                '/practice/' . $assignment['id']);
        }
    }

    /**
     * Clean posted rows: empty rows are dropped, filled values must be well-formed.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{topic: ?string, entry_date: ?string, time_start: ?string, time_end: ?string, place: ?string, is_distance: bool}>
     */
    public function parse(array $rows): array
    {
        $entries = [];
        $errors = [];
        foreach (array_values($rows) as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = static fn (string $key, int $max): ?string => ($v = trim((string) ($row[$key] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
            $entry = [
                'topic' => $text('topic', 500),
                'entry_date' => $text('date', 10),
                'time_start' => $text('time_start', 5),
                'time_end' => $text('time_end', 5),
                'place' => $text('place', 255),
                'is_distance' => in_array($row['is_distance'] ?? null, ['1', 1, true, 'on'], true),
            ];
            if ($entry['topic'] === null && $entry['entry_date'] === null && $entry['time_start'] === null && $entry['time_end'] === null && $entry['place'] === null) {
                continue; // an empty row
            }
            $n = count($entries) + 1;
            if ($entry['entry_date'] !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['entry_date']) || !checkdate((int) substr($entry['entry_date'], 5, 2), (int) substr($entry['entry_date'], 8, 2), (int) substr($entry['entry_date'], 0, 4)))) {
                $errors[] = __('practice.row_bad_date', ['n' => $n]);
            }
            foreach (['time_start', 'time_end'] as $t) {
                if ($entry[$t] !== null && self::minutes($entry[$t]) === null) {
                    $errors[] = __('practice.row_bad_time', ['n' => $n]);
                }
            }
            if ($entry['time_start'] !== null && $entry['time_end'] !== null && self::minutes($entry['time_start']) !== null && self::minutes($entry['time_end']) !== null
                && self::minutes($entry['time_end']) <= self::minutes($entry['time_start'])) {
                $errors[] = __('practice.row_end_before_start', ['n' => $n]);
            }
            $entries[] = $entry;
        }
        if (count($entries) > self::MAX_ROWS) {
            $errors[] = __('practice.too_many_rows', ['max' => self::MAX_ROWS]);
        }
        if ($errors !== []) {
            throw new ValidationException(['entries' => array_values(array_unique($errors))]);
        }

        return $entries;
    }

    /**
     * Hours per row and in total: counted (working days), excluded (weekends, holidays), remaining.
     *
     * @param list<array<string, mixed>> $entries rows with entry_date, time_start, time_end
     * @return array{limit: int, counted: float, excluded: float, remaining: float, over: float, percent: int,
     *   rows: list<array{hours: float, counted: bool, reason: ?string}>}
     */
    public function summary(array $entries, int $limit): array
    {
        $dates = array_filter(array_column($entries, 'entry_date'), static fn ($d): bool => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1);
        $holidays = $dates === [] ? [] : array_flip($this->repository->holidays(min($dates), max($dates)));
        $counted = 0.0;
        $excluded = 0.0;
        $rows = [];
        foreach ($entries as $e) {
            $hours = self::hours((string) ($e['time_start'] ?? ''), (string) ($e['time_end'] ?? ''));
            $date = (string) ($e['entry_date'] ?? '');
            $reason = $date === '' ? null : self::exclusion($date, $holidays);
            $isCounted = $hours > 0 && $date !== '' && $reason === null;
            $counted += $isCounted ? $hours : 0;
            $excluded += !$isCounted && $reason !== null ? $hours : 0;
            $rows[] = ['hours' => $hours, 'counted' => $isCounted, 'reason' => $reason];
        }

        return [
            'limit' => $limit,
            'counted' => round($counted, 1),
            'excluded' => round($excluded, 1),
            'remaining' => round(max(0, $limit - $counted), 1),
            'over' => round(max(0, $counted - $limit), 1),
            'percent' => $limit > 0 ? (int) min(100, round($counted / $limit * 100)) : 0,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $summary
     * @return list<string>
     */
    public function submitErrors(array $entries, array $summary): array
    {
        if ($entries === []) {
            return [__('practice.submit_empty')];
        }
        $errors = [];
        foreach ($entries as $i => $e) {
            $n = $i + 1;
            $missing = [];
            foreach (['topic' => 'practice.col_topic', 'entry_date' => 'practice.col_date', 'time_start' => 'practice.col_start', 'time_end' => 'practice.col_end', 'place' => 'practice.col_place'] as $key => $label) {
                if ($e[$key] === null) {
                    $missing[] = mb_strtolower(__($label));
                }
            }
            if ($missing !== []) {
                $errors[] = __('practice.row_missing', ['n' => $n, 'fields' => implode(', ', $missing)]);
                continue;
            }
            $reason = $summary['rows'][$i]['reason'] ?? null;
            if ($reason !== null) {
                $errors[] = __('practice.row_' . $reason, ['n' => $n, 'date' => format_date((string) $e['entry_date'])]);
            }
        }
        // Rows of one day must not overlap (one supervisor cannot be in two places at once).
        $byDate = [];
        foreach ($entries as $i => $e) {
            if ($e['entry_date'] !== null && $e['time_start'] !== null && $e['time_end'] !== null) {
                $byDate[$e['entry_date']][] = [$i + 1, self::minutes($e['time_start']), self::minutes($e['time_end'])];
            }
        }
        foreach ($byDate as $date => $list) {
            usort($list, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
            for ($k = 1; $k < count($list); $k++) {
                if ($list[$k][1] < $list[$k - 1][2]) {
                    $errors[] = __('practice.rows_overlap', ['a' => $list[$k - 1][0], 'b' => $list[$k][0], 'date' => format_date((string) $date)]);
                }
            }
        }
        if ($errors === []) {
            if ($summary['counted'] > $summary['limit'] + 0.05) {
                $errors[] = __('practice.hours_over', ['counted' => self::fmt($summary['counted']), 'limit' => $summary['limit']]);
            } elseif ($summary['counted'] < $summary['limit'] - 0.05) {
                $errors[] = __('practice.hours_under', ['counted' => self::fmt($summary['counted']), 'limit' => $summary['limit']]);
            }
        }

        return $errors;
    }

    /** Holidays of the academic calendar for the date picker (around the deadline). @return list<string> */
    public function holidaysAround(array $assignment): array
    {
        $deadline = (string) $assignment['deadline'];

        return $this->repository->holidays(date('Y-m-d', strtotime($deadline . ' -12 months')), date('Y-m-d', strtotime($deadline . ' +12 months')));
    }

    public static function isOverdue(array $assignment): bool
    {
        return in_array($assignment['status'], self::EDITABLE, true) && (string) $assignment['deadline'] < date('Y-m-d');
    }

    public static function hours(string $start, string $end): float
    {
        $s = self::minutes($start);
        $e = self::minutes($end);

        return $s === null || $e === null || $e <= $s ? 0.0 : round(($e - $s) / 60, 1);
    }

    public static function fmt(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
    }

    private static function minutes(string $time): ?int
    {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::\d{2})?$/', trim($time), $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /** @param array<string, int|bool> $holidays */
    private static function exclusion(string $date, array $holidays): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        if ((int) date('N', (int) strtotime($date)) >= 6) {
            return 'weekend';
        }

        return isset($holidays[$date]) ? 'holiday' : null;
    }

    /** @param array<string, string> $extra */
    private function notifyTeacher(array $assignment, string $title, string $body, array $extra = []): void
    {
        if (($assignment['teacher_user_id'] ?? null) === null) {
            return;
        }
        Notifier::send([(int) $assignment['teacher_user_id']], 'practice', $title, $body,
            ['title' => (string) $assignment['title'], 'group' => (string) $assignment['group_code'], 'deadline' => format_date((string) $assignment['deadline'])] + $extra,
            '/practice/' . $assignment['id']);
    }
}
