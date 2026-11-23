<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Data for the printed forms of Order No. 130 of the Minister of Education and Science of the
 * Republic of Kazakhstan of 6 April 2020 ("Перечень документов, обязательных для ведения
 * педагогами ... и их формы", Appendix 8, technical and vocational education):
 *  - Журнал учета теоретического обучения: title page, form 5.3 and form 5.5;
 *  - Экзаменационная ведомость (для промежуточной аттестации обучающихся).
 *
 * Nothing here changes how grades are calculated: values come from the journal and its scheme.
 * One assumption: the order defines "рейтинг допуска" only as an arithmetic mean, without
 * naming the grades; it is taken as the mean of the scheme's rating components (rating1,
 * rating2) — see RATING_CODES.
 */
final class Order130
{
    public const BASIS = 'Приказ Министра образования и науки Республики Казахстан от 6 апреля 2020 года № 130';

    /** Scheme components whose mean is the "рейтинг допуска". */
    public const RATING_CODES = ['rating1', 'rating2'];

    /** Title page details of a group: course, specialty, qualification, curriculum. @return array<string, mixed> */
    public static function group(int $groupId): array
    {
        return Database::fetch(
            'SELECT g.id, g.code, g.course, g.curriculum_id, sp.code AS specialty_code, sp.name_ru AS specialty_ru, sp.name_kk AS specialty_kk,
                    q.code AS qualification_code, q.name_ru AS qualification_ru, q.name_kk AS qualification_kk
               FROM `groups` g JOIN specialties sp ON sp.id = g.specialty_id LEFT JOIN qualifications q ON q.id = g.qualification_id
              WHERE g.id = ?',
            [$groupId]
        ) ?? [];
    }

    /** "06130100 Программное обеспечение". @param array<string, mixed> $group */
    public static function specialty(array $group): string
    {
        return trim(($group['specialty_code'] ?? '') . ' ' . localized(['name_ru' => $group['specialty_ru'] ?? '', 'name_kk' => $group['specialty_kk'] ?? '']));
    }

    /** @param array<string, mixed> $group */
    public static function qualification(array $group): string
    {
        return ($group['qualification_ru'] ?? null) === null ? ''
            : trim($group['qualification_code'] . ' ' . localized(['name_ru' => $group['qualification_ru'], 'name_kk' => $group['qualification_kk']]));
    }

    /**
     * Curriculum line of the subject in the group's study semester ((course − 1) × 2 + semester number).
     *
     * @return array{module_code: string, hours_total: int|null, control_form: string}
     */
    public static function curriculumItem(array $group, int $subjectId, int $semesterNumber): array
    {
        $item = ($group['curriculum_id'] ?? null) === null ? null : Database::fetch(
            'SELECT module_code, hours_total, control_form FROM curriculum_subjects WHERE curriculum_id = ? AND subject_id = ? AND semester = ?',
            [$group['curriculum_id'], $subjectId, ((int) $group['course'] - 1) * 2 + $semesterNumber]
        );

        return [
            'module_code' => (string) ($item['module_code'] ?? ''),
            'hours_total' => isset($item['hours_total']) ? (int) $item['hours_total'] : null,
            'control_form' => (string) ($item['control_form'] ?? 'none'),
        ];
    }

    /**
     * Course of the group in a given academic year: the stored course is the current one, so a
     * past year subtracts the years between them ("ПО-25-1" is on the 2nd course now, was on the 1st).
     */
    public static function courseIn(int $currentCourse, int $academicYearId): int
    {
        $active = (string) Database::fetchColumn("SELECT start_date FROM academic_years WHERE status = 'active' ORDER BY start_date DESC LIMIT 1");
        $that = (string) Database::fetchColumn('SELECT start_date FROM academic_years WHERE id = ?', [$academicYearId]);
        if ($active === '' || $that === '') {
            return $currentCourse;
        }

        return max(1, $currentCourse - ((int) substr($active, 0, 4) - (int) substr($that, 0, 4)));
    }

    /** Mean of the rating components present in the result, or null. @param array<string, mixed> $result */
    public static function admissionRating(array $components, array $result): ?float
    {
        $values = [];
        foreach ($components as $c) {
            $v = $result['components'][(int) $c['assessment_type_id']] ?? null;
            if (in_array((string) $c['code'], self::RATING_CODES, true) && $v !== null) {
                $values[] = (float) $v;
            }
        }

        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }

    /** Value of a scheme component by code, or null. @param array<string, mixed> $result */
    public static function component(array $components, array $result, string $code): ?float
    {
        foreach ($components as $c) {
            if ((string) $c['code'] === $code) {
                $v = $result['components'][(int) $c['assessment_type_id']] ?? null;

                return $v === null ? null : (float) $v;
            }
        }

        return null;
    }

    /**
     * Form 5.5 converted to the five-point system (Типовые правила, приказ МОН РК № 125) with the
     * group totals the college adds under it: absolute performance, quality and average mark by
     * subject, and excellent / good / satisfactory / failing students for the year.
     * The mark of a subject for the year is its «Итоговая оценка», otherwise the latest semester mark.
     *
     * @param array<string, mixed> $form result of yearResults()
     * @return array<string, mixed> the form with marks[subject id][s1|s2|exam|final] in every student,
     *   subject_stats[subject id] and summary (see GradeScale::summary())
     */
    public function withTotals(array $form): array
    {
        $subjectMarks = array_fill_keys(array_column($form['subjects'], 'id'), []);
        $marksByStudent = [];
        foreach ($form['students'] as $i => &$student) {
            $marks = [];
            foreach ($student['cells'] as $subjectId => $cell) {
                $converted = [];
                foreach (['s1', 's2', 'exam', 'final'] as $key) {
                    $value = $cell[$key] ?? null;
                    $converted[$key] = is_numeric($value)
                        ? GradeScale::grade($value, $cell['scheme'] ?? null, $key === 'final' ? ($cell['passed'] ?? null) : null)
                        : null;
                }
                $credit = $cell['credit'] ?? null;
                $converted['credit'] = is_numeric($credit) ? GradeScale::grade($credit, $cell['scheme'] ?? null) : null;
                $student['marks'][$subjectId] = $converted;
                $year = $converted['final'] ?? $converted['s2'] ?? $converted['s1'];
                if ($year !== null) {
                    $marks[] = $year['mark'];
                    $subjectMarks[$subjectId][] = $year['mark'];
                }
            }
            $student['average'] = GradeScale::stats($marks)['average'];
            $marksByStudent[$i] = $marks;
        }
        unset($student);
        $summary = GradeScale::summary($marksByStudent);
        foreach ($summary['categories'] as $key => $indexes) {
            $summary['categories'][$key] = array_map(static fn (string $i): string => $form['students'][(int) $i]['name'], $indexes);
        }

        return $form + [
            'subject_stats' => array_map([GradeScale::class, 'stats'], $subjectMarks),
            'summary' => $summary,
            'scale' => GradeScale::scale(),
        ];
    }

    /**
     * Form 5.5 "Итоги учебно-воспитательной работы" of a group for the academic year of the semester.
     *
     * @return array{
     *   group: array<string, mixed>, year: string,
     *   subjects: list<array{id: int, code: string, name: string, module: string, hours_plan: int|null, hours_given: int}>,
     *   students: list<array{id: int, name: string, cells: array<int, array<string, mixed>>, missed: array<int, array{total: int, unexcused: int}>}>
     * }
     */
    public function yearResults(int $groupId, int $semesterId): array
    {
        $reports = new ReportService();
        $service = new JournalService();
        $group = self::group($groupId);
        $year = Database::fetch('SELECT y.id, y.title FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE s.id = ?', [$semesterId]);
        $semesters = Database::fetchAll('SELECT id, number FROM semesters WHERE academic_year_id = ? ORDER BY number', [$year['id'] ?? 0]);
        if ($group !== [] && $year !== null) {
            $group['course'] = self::courseIn((int) $group['course'], (int) $year['id']);
        }
        $today = date('Y-m-d');
        $schemes = [];
        foreach (Database::fetchAll('SELECT id, grade_min, grade_max FROM grading_schemes') as $row) {
            $schemes[(int) $row['id']] = ['min' => (float) $row['grade_min'], 'max' => (float) $row['grade_max']];
        }

        $subjects = [];
        $students = [];
        foreach ($semesters as $semester) {
            $number = (int) $semester['number'];
            foreach ($reports->journals($groupId, (int) $semester['id']) as $journal) {
                $subjectId = (int) $journal['subject_id'];
                $grid = $service->grid($journal);
                $item = self::curriculumItem($group, $subjectId, $number);
                $held = array_filter($grid['lessons'], static fn (array $l): bool => $l['lesson_date'] <= $today);
                $subjects[$subjectId] ??= ['id' => $subjectId, 'code' => (string) $journal['subject_code'],
                    'name' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]),
                    'module' => '', 'hours_plan' => null, 'hours_given' => 0];
                $subjects[$subjectId]['module'] = $item['module_code'] !== '' ? $item['module_code'] : $subjects[$subjectId]['module'];
                if ($item['hours_total'] !== null) {
                    $subjects[$subjectId]['hours_plan'] = (int) $subjects[$subjectId]['hours_plan'] + $item['hours_total'];
                }
                $subjects[$subjectId]['hours_given'] += array_sum(array_map(static fn (array $l): int => (int) $l['hours'], $held));

                foreach ($grid['students'] as $s) {
                    $sid = (int) $s['id'];
                    $students[$sid] ??= ['id' => $sid, 'name' => full_name($s, true), 'cells' => [], 'missed' => [1 => ['total' => 0, 'unexcused' => 0], 2 => ['total' => 0, 'unexcused' => 0]]];
                    $r = $s['result'];
                    $final = $journal['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final']) : $r['final'];
                    $exam = self::component($grid['components'], $r, 'exam');
                    $cell = $students[$sid]['cells'][$subjectId] ?? ['s1' => null, 's2' => null, 'rating' => null, 'credit' => null, 'exam' => null, 'final' => null];
                    $cell['s' . min(2, max(1, $number))] = $final;
                    // Scale of the latest journal and its pass verdict: used to convert grades to five-point marks.
                    $cell['scheme'] = $journal['grading_scheme_id'] !== null ? ($schemes[(int) $journal['grading_scheme_id']] ?? null) : null;
                    $cell['passed'] = $r['passed'];
                    // Attestation of the semester in which the subject is examined (curriculum control form).
                    $cell['rating'] = self::admissionRating($grid['components'], $r) ?? $cell['rating'];
                    $form = $item['control_form'];
                    if ($form !== 'none' || $exam !== null) {
                        if (in_array($form, ['credit', 'diff_credit'], true)) {
                            $cell['credit'] = $form === 'diff_credit' ? ($exam ?? $final) : ($r['passed'] === null ? null : ($r['passed'] ? 'зач.' : 'незач.'));
                        } else {
                            $cell['exam'] = $exam ?? $cell['exam'];
                        }
                        $cell['final'] = $final;
                    }
                    $students[$sid]['cells'][$subjectId] = $cell;

                    foreach ($held as $lesson) {
                        $a = $s['cells'][(int) $lesson['id']]['attendance'] ?? null;
                        if ($a === 'absent' || $a === 'excused') {
                            $students[$sid]['missed'][min(2, max(1, $number))]['total'] += (int) $lesson['hours'];
                            $students[$sid]['missed'][min(2, max(1, $number))]['unexcused'] += $a === 'absent' ? (int) $lesson['hours'] : 0;
                        }
                    }
                }
            }
        }
        uasort($students, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'group' => $group,
            'year' => str_replace('-', '–', (string) ($year['title'] ?? '')),
            'subjects' => array_values($subjects),
            'students' => array_values($students),
        ];
    }
}
