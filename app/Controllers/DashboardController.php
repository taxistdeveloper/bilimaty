<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Repositories\DashboardRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\ScheduleService;

final class DashboardController extends Controller
{
    /**
     * Student: group, course, specialty, current semester and results of its subjects
     * (Rating 1, Rating 2, exam, final, absences). Null for other users.
     *
     * @return array<string, mixed>|null
     */
    private function myStudy(): ?array
    {
        $journals = new \App\Repositories\JournalRepository();
        $studentId = Auth::can('journal.view') ? $journals->studentIdOfUser((int) Auth::id()) : null;
        if ($studentId === null) {
            return null;
        }
        $student = (new \App\Repositories\StudentRepository())->find($studentId);
        $semester = (new ScheduleRepository())->semesterOn(date('Y-m-d'));
        $service = new \App\Services\JournalService($journals);
        $codes = [];
        foreach (\App\Core\Database::fetchAll("SELECT id, code FROM assessment_types WHERE code IN ('rating1','rating2','exam')") as $t) {
            $codes[(string) $t['code']] = (int) $t['id'];
        }
        $rows = [];
        $absent = 0;
        foreach ($journals->journalsOfStudent($studentId) as $j) {
            if ($semester !== null && (int) $j['semester_id'] !== (int) $semester['id']) {
                continue;
            }
            $r = $service->studentResult($j, $studentId);
            $absent += (int) ($r['absent'] ?? 0) + (int) ($r['excused'] ?? 0);
            $rows[] = [
                'journal_id' => (int) $j['id'],
                'subject' => localized(['name_ru' => $j['subject_name_ru'], 'name_kk' => $j['subject_name_kk']]),
                'rating1' => $r['components'][$codes['rating1'] ?? 0] ?? null,
                'rating2' => $r['components'][$codes['rating2'] ?? 0] ?? null,
                'exam' => $r['components'][$codes['exam'] ?? 0] ?? null,
                'final' => $j['status'] === 'approved' ? ($r['fixed_final'] ?? $r['final'] ?? null) : ($r['final'] ?? null),
                'passed' => $r['passed'] ?? null,
                'absences' => (int) ($r['absent'] ?? 0) + (int) ($r['excused'] ?? 0),
            ];
        }

        return ['student' => $student, 'semester' => $semester, 'rows' => $rows, 'absences' => $absent];
    }

    /**
     * Teacher: own journals of the current semester with the number of students whose
     * results are incomplete (a component of the scheme has no value yet).
     *
     * @return list<array<string, mixed>>|null
     */
    private function myJournals(): ?array
    {
        $teacherId = \App\Services\AccessScope::teacherId();
        if ($teacherId === null || !Auth::can('journal.manage')) {
            return null;
        }
        $semester = (new ScheduleRepository())->semesterOn(date('Y-m-d'));
        $repo = new \App\Repositories\JournalRepository();
        $service = new \App\Services\JournalService($repo);
        $out = [];
        foreach ($repo->list(['semester' => $semester['id'] ?? null], $teacherId, true) as $j) {
            $grid = $service->grid($j);
            $incomplete = count(array_filter($grid['students'], static fn (array $s): bool => ($s['result']['missing'] ?? []) !== [] || ($s['result']['final'] ?? null) === null));
            $out[] = ['journal' => $j, 'students' => count($grid['students']), 'incomplete' => $incomplete];
        }

        return $out;
    }

    /** Open surveys addressed to the user that are not answered yet. @return list<array<string, mixed>> */
    private function mySurveys(): array
    {
        if (!Auth::can('surveys.respond')) {
            return [];
        }
        $repository = new \App\Repositories\SurveyRepository();
        $respondent = $repository->respondent((int) Auth::id());
        if ($respondent === null) {
            return [];
        }
        $service = new \App\Services\SurveyService($repository);

        return array_values(array_filter(
            $repository->openSurveys((int) Auth::id(), date('Y-m-d')),
            static fn (array $s): bool => !(bool) $s['answered'] && $service->matches($s, $respondent)
        ));
    }

    /** The signed-in teacher's plan/fact for the year. @return array<string, mixed>|null */
    private function myWorkload(int $yearId): ?array
    {
        $teacherId = \App\Services\AccessScope::teacherId();
        if ($teacherId === null || !Auth::can('workload.view')) {
            return null;
        }
        $service = new \App\Services\WorkloadService();
        $totals = $service->report($service->lines(['year' => $yearId, 'teacher' => $teacherId]), 'teacher', $yearId)['totals'];

        return $totals['plan'] > 0 || $totals['fact'] > 0 ? $totals + ['teacher_id' => $teacherId, 'year_id' => $yearId] : null;
    }

    /** Next exams/ratings of the signed-in student or examiner (30 days). @return list<array<string, mixed>>|null */
    private function upcomingExams(): ?array
    {
        if (!Auth::can('exams.view')) {
            return null;
        }
        $exams = new \App\Repositories\ExamRepository();
        $studentId = (new \App\Repositories\JournalRepository())->studentIdOfUser((int) Auth::id());
        $until = date('Y-m-d', strtotime('+30 days'));
        if ($studentId !== null) {
            $list = $exams->forStudent($studentId, date('Y-m-d'));
        } elseif (($teacherId = \App\Services\AccessScope::teacherId()) !== null) {
            $list = $exams->upcomingForTeacher($teacherId, date('Y-m-d'), 10);
        } else {
            return null;
        }

        return array_slice(array_values(array_filter($list, static fn (array $e): bool => $e['exam_date'] <= $until)), 0, 5);
    }

    /**
     * Today's lessons of the signed-in teacher or student (null when the user has neither profile).
     *
     * @return array{mode: string, items: list<array<string, mixed>>}|null
     */
    private function todayLessons(): ?array
    {
        if (!Auth::can('schedule.view')) {
            return null;
        }
        $repo = new ScheduleRepository();
        $groupId = $repo->groupOfStudentUser((int) Auth::id());
        $teacherId = $groupId === null ? $repo->teacherIdOfUser((int) Auth::id()) : null;
        if ($groupId === null && $teacherId === null) {
            return null;
        }
        $semester = $repo->semesterOn(date('Y-m-d'));
        $filter = $groupId !== null ? ['group_id' => $groupId] : ['teacher_id' => $teacherId];

        return [
            'mode' => $groupId !== null ? 'group' : 'teacher',
            'items' => $semester === null ? [] : (new ScheduleService($repo))->occurrences($semester, date('Y-m-d'), date('Y-m-d'), $filter),
        ];
    }

    public function index(): Response
    {
        $repository = new DashboardRepository();
        $year = $repository->activeAcademicYear();

        $chart = [];
        if (Auth::can('students.view')) {
            foreach ($repository->studentsByDepartment() as $row) {
                $chart[] = ['label' => localized($row), 'value' => (int) $row['total']];
            }
        }

        return $this->view('dashboard/index', [
            'title' => __('nav.dashboard'),
            'counters' => $repository->counters(),
            'year' => $year,
            'semesters' => $year !== null ? $repository->semesters((int) $year['id']) : [],
            'roles' => (new UserRepository())->roles((int) Auth::id()),
            'chart' => $chart,
            'audit' => Auth::can('audit.view') ? $repository->recentAudit() : [],
            'todayLessons' => $this->todayLessons(),
            'upcomingExams' => $this->upcomingExams(),
            'myWorkload' => $year !== null ? $this->myWorkload((int) $year['id']) : null,
            'mySurveys' => $this->mySurveys(),
            'myStudy' => $this->myStudy(),
            'myJournals' => $this->myJournals(),
            'myChildren' => Auth::can('children.view') ? (new \App\Repositories\GuardianRepository())->childrenOf((int) Auth::id()) : [],
        ]);
    }
}
