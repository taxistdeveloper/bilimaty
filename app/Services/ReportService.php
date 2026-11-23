<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\JournalRepository;

/**
 * Academic reports by group and semester: performance (finals of every subject) and
 * attendance (absences by subject, share of attended lessons).
 *
 * Who sees what:
 *  - administration, department heads and curators — whole groups of their scope, all subjects;
 *  - a teacher — the groups they teach, only the subjects they teach there.
 * No grading formula is introduced here: finals come from the journal and its approved scheme.
 */
final class ReportService
{
    public function __construct(private readonly JournalRepository $journals = new JournalRepository())
    {
    }

    /** @return array<int, string> groups the user may report on (id => code) */
    public function groups(): array
    {
        $scope = AccessScope::groupIds();
        $taught = $this->taughtGroups();
        if ($scope === null) {
            $where = "status = 'active'";
            $params = [];
        } else {
            $ids = array_values(array_unique(array_merge($scope, $taught)));
            if ($ids === []) {
                return [];
            }
            $where = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
        $out = [];
        foreach (Database::fetchAll("SELECT id, code FROM `groups` WHERE {$where} ORDER BY code", $params) as $g) {
            $out[(int) $g['id']] = (string) $g['code'];
        }

        return $out;
    }

    /**
     * Journals of the group in the semester the user may see.
     *
     * @return list<array<string, mixed>>
     */
    public function journals(int $groupId, int $semesterId): array
    {
        $teacher = AccessScope::teacherId();
        $wholeGroup = AccessScope::allowsGroup($groupId);
        $list = $this->journals->list(['semester' => $semesterId, 'group' => $groupId], $wholeGroup ? null : $teacher, !$wholeGroup);
        if (!$wholeGroup && $list === []) {
            return [];
        }

        return $list;
    }

    /**
     * @return array{subjects: list<array<string, mixed>>, students: list<array<string, mixed>>}
     *   students: id, name, number, cells[journal id] => [final, passed, absent, excused, lessons], debts, absent, excused, lessons
     */
    public function build(int $groupId, int $semesterId): array
    {
        $service = new JournalService($this->journals);
        $today = date('Y-m-d');
        $subjects = [];
        $students = [];
        foreach ($this->journals($groupId, $semesterId) as $journal) {
            $grid = $service->grid($journal);
            $held = array_values(array_filter($grid['lessons'], static fn (array $l): bool => $l['lesson_date'] <= $today));
            $subjects[] = ['id' => (int) $journal['id'], 'name' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]),
                'code' => $journal['subject_code'], 'status' => $journal['status'], 'lessons' => count($held),
                'scheme_id' => $journal['grading_scheme_id'] !== null ? (int) $journal['grading_scheme_id'] : null];
            foreach ($grid['students'] as $s) {
                $sid = (int) $s['id'];
                $students[$sid] ??= ['id' => $sid, 'name' => full_name($s, true), 'number' => $s['student_number'] ?? '', 'cells' => [], 'debts' => 0, 'absent' => 0, 'excused' => 0, 'lessons' => 0];
                $memberLessons = 0;
                foreach ($held as $lesson) {
                    $memberLessons += ($s['cells'][(int) $lesson['id']]['member'] ?? false) ? 1 : 0;
                }
                $r = $s['result'];
                $final = $journal['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final']) : $r['final'];
                $students[$sid]['cells'][(int) $journal['id']] = [
                    'final' => $final, 'passed' => $r['passed'], 'absent' => (int) $r['absent'], 'excused' => (int) $r['excused'], 'lessons' => $memberLessons,
                ];
                $students[$sid]['debts'] += $r['passed'] === false ? 1 : 0;
                $students[$sid]['absent'] += (int) $r['absent'];
                $students[$sid]['excused'] += (int) $r['excused'];
                $students[$sid]['lessons'] += $memberLessons;
            }
        }
        uasort($students, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['subjects' => $subjects, 'students' => array_values($students)];
    }

    /**
     * Summary grade sheet of a group (сводная ведомость): every final converted to the traditional
     * five-point mark and the letter of the point-rating scale, with absolute performance, quality
     * and average mark per subject, per student and for the whole group.
     *
     *   absolute performance = graded without a «2» / graded × 100
     *   quality              = graded «4» and «5» / graded × 100
     *   average mark         = mean of five-point marks
     *
     * @return array<string, mixed>
     */
    public function gradeSheet(int $groupId, int $semesterId): array
    {
        $data = $this->build($groupId, $semesterId);
        $schemeIds = array_values(array_unique(array_filter(array_column($data['subjects'], 'scheme_id'))));
        $schemes = [];
        if ($schemeIds !== []) {
            foreach (Database::fetchAll('SELECT id, grade_min, grade_max FROM grading_schemes WHERE id IN (' . implode(',', array_fill(0, count($schemeIds), '?')) . ')', $schemeIds) as $row) {
                $schemes[(int) $row['id']] = ['min' => (float) $row['grade_min'], 'max' => (float) $row['grade_max']];
            }
        }

        $subjectStats = [];
        foreach ($data['subjects'] as $subject) {
            $subjectStats[$subject['id']] = [];
        }
        $marksByStudent = [];
        foreach ($data['students'] as $i => &$student) {
            $marks = [];
            foreach ($data['subjects'] as $subject) {
                $cell = $student['cells'][$subject['id']] ?? null;
                if ($cell === null) {
                    continue;
                }
                $grade = GradeScale::grade($cell['final'], $subject['scheme_id'] !== null ? ($schemes[$subject['scheme_id']] ?? null) : null, $cell['passed']);
                $student['cells'][$subject['id']]['grade'] = $grade;
                if ($grade !== null) {
                    $marks[] = $grade['mark'];
                    $subjectStats[$subject['id']][] = $grade['mark'];
                }
            }
            $student['stats'] = GradeScale::stats($marks);
            $student['category'] = GradeScale::category($marks);
            $marksByStudent[$i] = $marks;
        }
        unset($student);
        $summary = GradeScale::summary($marksByStudent);
        foreach ($summary['categories'] as $key => $indexes) {
            $summary['categories'][$key] = array_map(static fn (string $i): string => $data['students'][(int) $i]['name'], $indexes);
        }

        return $data + [
            'subject_stats' => array_map([GradeScale::class, 'stats'], $subjectStats),
            'summary' => $summary,
            'scale' => GradeScale::scale(),
        ];
    }

    /** Share of attended lessons, % (null when nothing was held). */
    public static function attendance(int $lessons, int $absent, int $excused): ?float
    {
        return $lessons > 0 ? round(max(0, $lessons - $absent - $excused) / $lessons * 100, 1) : null;
    }

    /** @return list<int> groups where the signed-in teacher has a journal */
    private function taughtGroups(): array
    {
        $teacher = AccessScope::teacherId();
        if ($teacher === null) {
            return [];
        }

        return array_map('intval', array_column(Database::fetchAll(
            'SELECT DISTINCT group_id FROM teacher_subject_assignments WHERE teacher_id = ?',
            [$teacher]
        ), 'group_id'));
    }
}
