<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\JournalRepository;
use App\Repositories\StudentRepository;

/**
 * Academic transcript.
 *
 * build()  — live data: only APPROVED journals (official finals with their scheme version),
 *            hours/credits from the group's curriculum (fallback: hours of conducted lessons),
 *            rating/exam components and the group history.
 * issue()  — stores an immutable snapshot with a unique number, a random verification code
 *            (QR) and a SHA-256 fingerprint of the content.
 * GPA      — only when config/transcript.php has an approved scale (enabled + scale + basis).
 */
final class TranscriptService
{
    public function __construct(
        private readonly JournalRepository $journals = new JournalRepository(),
        private readonly StudentRepository $students = new StudentRepository(),
    ) {
    }

    /** @return array<string, mixed> */
    public function build(int $studentId): array
    {
        $student = $this->students->find($studentId) ?? throw new ValidationException(['student_id' => [__('transcripts.student_missing')]]);
        $types = array_column(Database::fetchAll("SELECT id, code FROM assessment_types WHERE source <> 'computed_final'"), 'code', 'id');
        $finalTypeId = (int) Database::fetchColumn("SELECT id FROM assessment_types WHERE code = 'final'");
        $gpaConfig = (array) Config::get('transcript.gpa', []);
        $gpaEnabled = !empty($gpaConfig['enabled']) && !empty($gpaConfig['scale']);

        $items = [];
        $pending = 0;
        foreach ($this->journals->journalsOfStudent($studentId) as $journal) {
            if ($journal['status'] !== 'approved') {
                $pending++;
                continue;
            }
            $assessments = $this->journals->assessments((int) $journal['id'])[$studentId] ?? [];
            $components = [];
            foreach ($assessments as $typeId => $row) {
                if (isset($types[$typeId])) {
                    $components[$types[$typeId]] = $row['value'] !== null ? (float) $row['value'] : null;
                }
            }
            $final = isset($assessments[$finalTypeId]) ? (float) $assessments[$finalTypeId]['value'] : null;
            [$hours, $source, $credits] = $this->volume($journal);
            [$letter, $points] = $gpaEnabled ? $this->letter($final, (array) $gpaConfig['scale']) : [null, null];
            $items[] = [
                'academic_year' => (string) $journal['year_title'],
                'semester_number' => (int) $journal['semester_number'],
                'semester_start' => (string) $journal['semester_start'],
                'subject_code' => (string) $journal['subject_code'],
                'subject_name_ru' => (string) $journal['subject_name_ru'],
                'subject_name_kk' => (string) $journal['subject_name_kk'],
                'hours' => $hours,
                'hours_source' => $source,
                'credits' => $credits,
                'components' => $components,
                'final' => $final,
                'letter' => $letter,
                'grade_points' => $points,
                'scheme' => $journal['scheme_title'] !== null ? $journal['scheme_title'] . ' v' . $journal['scheme_version'] : null,
            ];
        }
        usort($items, static fn (array $a, array $b): int => [$a['semester_start'], $a['subject_code']] <=> [$b['semester_start'], $b['subject_code']]);

        $history = array_map(static fn (array $h): array => [
            'group' => $h['group_code'], 'from' => $h['start_date'], 'to' => $h['end_date'], 'reason' => $h['reason'], 'end_reason' => $h['end_reason'], 'order' => $h['order_number'],
        ], array_reverse($this->students->history($studentId)));

        return [
            'header' => [
                'student_id' => $studentId,
                'full_name' => full_name($student),
                'student_number' => $student['student_number'],
                'birth_date' => $student['birth_date'],
                'specialty' => $student['specialty_code'] !== null ? $student['specialty_code'] . ' — ' . $student['specialty_name_ru'] : null,
                'specialty_kk' => $student['specialty_code'] !== null ? $student['specialty_code'] . ' — ' . $student['specialty_name_kk'] : null,
                'qualification' => $student['qualification_name_ru'],
                'qualification_kk' => $student['qualification_name_kk'],
                'group' => $student['group_code'],
                'course' => $student['course'],
                'status' => $student['status'],
                'enrollment_date' => $student['enrollment_date'],
                'college_ru' => Settings::get('college_name_ru'),
                'college_kk' => Settings::get('college_name_kk'),
                'history' => $history,
                'gpa_basis' => $gpaEnabled ? (string) ($gpaConfig['basis'] ?? '') : null,
            ],
            'items' => $items,
            'pending' => $pending,
            'gpa' => $gpaEnabled ? $this->gpa($items, (string) ($gpaConfig['weight_by'] ?? 'credits'), (int) ($gpaConfig['decimals'] ?? 2)) : null,
            'totals' => [
                'hours' => array_sum(array_map(static fn (array $i): int => (int) $i['hours'], $items)),
                'credits' => array_sum(array_map(static fn (array $i): float => (float) $i['credits'], $items)),
            ],
        ];
    }

    public function issue(int $studentId): int
    {
        $data = $this->build($studentId);
        if ($data['items'] === []) {
            throw new ValidationException(['student_id' => [__('transcripts.nothing_to_issue')]]);
        }

        return Database::transaction(function () use ($studentId, $data): int {
            $year = (int) date('Y');
            $prefix = (string) Config::get('transcript.number_prefix', 'T') . '-' . $year . '-';
            $last = Database::fetchColumn('SELECT MAX(number) FROM transcripts WHERE number LIKE ? FOR UPDATE', [$prefix . '%']);
            $number = $prefix . str_pad((string) (($last !== null ? (int) substr((string) $last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
            $code = bin2hex(random_bytes(16));
            $items = array_map(static function (array $i): array {
                unset($i['semester_start'], $i['scheme']);

                return $i;
            }, $data['items']);
            $snapshot = ['header' => $data['header'], 'totals' => $data['totals'], 'number' => $number];
            $fingerprint = hash('sha256', json_encode(['snapshot' => $snapshot, 'items' => $items, 'gpa' => $data['gpa']], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

            Database::execute(
                'INSERT INTO transcripts (student_id, number, verification_code, fingerprint, snapshot, gpa, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$studentId, $number, $code, $fingerprint, json_encode($snapshot, JSON_UNESCAPED_UNICODE), $data['gpa'], Auth::id()]
            );
            $id = Database::lastInsertId();
            foreach ($items as $order => $i) {
                Database::execute(
                    'INSERT INTO transcript_items (transcript_id, academic_year, semester_number, subject_code, subject_name_ru, subject_name_kk, hours, hours_source,
                            credits, components, final, letter, grade_points, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $i['academic_year'], $i['semester_number'], $i['subject_code'], $i['subject_name_ru'], $i['subject_name_kk'], $i['hours'], $i['hours_source'],
                     $i['credits'], json_encode($i['components']), $i['final'], $i['letter'], $i['grade_points'], $order]
                );
            }
            AuditLogger::log('transcripts.issued', 'transcripts', $id, null, ['student_id' => $studentId, 'number' => $number, 'items' => count($items)]);

            return $id;
        });
    }

    /** @return array<string, mixed>|null issued transcript with items */
    public function find(int $id): ?array
    {
        $t = Database::fetch(
            'SELECT tr.*, u.last_name AS issuer_last_name, u.first_name AS issuer_first_name, u.middle_name AS issuer_middle_name
             FROM transcripts tr LEFT JOIN users u ON u.id = tr.issued_by WHERE tr.id = ?',
            [$id]
        );
        if ($t === null) {
            return null;
        }
        $t['snapshot'] = json_decode((string) $t['snapshot'], true);
        $t['items'] = array_map(static function (array $i): array {
            $i['components'] = json_decode((string) $i['components'], true);

            return $i;
        }, Database::fetchAll('SELECT * FROM transcript_items WHERE transcript_id = ? ORDER BY sort_order', [$id]));

        return $t;
    }

    public function revoke(array $transcript, string $reason): void
    {
        Database::execute(
            "UPDATE transcripts SET status = 'revoked', revoked_at = NOW(), revoked_by = ?, revoke_reason = ? WHERE id = ? AND status = 'issued'",
            [Auth::id(), $reason, $transcript['id']]
        );
        AuditLogger::log('transcripts.revoked', 'transcripts', (int) $transcript['id'], null, ['reason' => $reason]);
    }

    /** Public check: no personal data, only the document facts. @return array<string, mixed>|null */
    public function verify(string $code): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $code)) {
            return null;
        }

        return Database::fetch(
            'SELECT number, status, issued_at, revoked_at, fingerprint, (SELECT COUNT(*) FROM transcript_items i WHERE i.transcript_id = t.id) AS items_count
             FROM transcripts t WHERE verification_code = ?',
            [$code]
        );
    }

    /** @return array{0: ?int, 1: ?string, 2: ?float} hours, source, credits */
    private function volume(array $journal): array
    {
        $plan = Database::fetch(
            'SELECT cs.hours_total, cs.credits FROM `groups` g
             JOIN curriculum_subjects cs ON cs.curriculum_id = g.curriculum_id AND cs.subject_id = ?
             WHERE g.id = ? AND cs.semester = ? LIMIT 1',
            [$journal['subject_id'], $journal['group_id'], ((int) $journal['course'] - 1) * 2 + (int) $journal['semester_number']]
        );
        if ($plan !== null) {
            return [(int) $plan['hours_total'], 'curriculum', $plan['credits'] !== null ? (float) $plan['credits'] : null];
        }
        $conducted = (int) Database::fetchColumn('SELECT COALESCE(SUM(hours), 0) FROM journal_lessons WHERE assignment_id = ?', [$journal['id']]);

        return [$conducted > 0 ? $conducted : null, $conducted > 0 ? 'conducted' : null, null];
    }

    /**
     * @param list<array{0: float|int, 1: string, 2: float|int}> $scale [min final, letter, points], highest first
     * @return array{0: ?string, 1: ?float}
     */
    private function letter(?float $final, array $scale): array
    {
        if ($final === null) {
            return [null, null];
        }
        foreach ($scale as [$min, $letter, $points]) {
            if ($final >= (float) $min) {
                return [(string) $letter, (float) $points];
            }
        }

        return [null, null];
    }

    /** @param list<array<string, mixed>> $items */
    private function gpa(array $items, string $weightBy, int $decimals): ?float
    {
        $sum = 0.0;
        $weights = 0.0;
        foreach ($items as $item) {
            $weight = $weightBy === 'hours' ? (float) $item['hours'] : (float) $item['credits'];
            if ($item['grade_points'] === null || $weight <= 0) {
                continue;
            }
            $sum += $item['grade_points'] * $weight;
            $weights += $weight;
        }

        return $weights > 0 ? round($sum / $weights, $decimals) : null;
    }
}
