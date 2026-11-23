<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Csv;
use App\Repositories\OptionsRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\WorkloadRepository;
use App\Services\AccessScope;
use App\Services\WorkloadService;

/**
 * Teaching workload: overview by teachers, a teacher's plan (distribution of hours and
 * manual actual hours) and plan/fact reports with CSV export and A4 print.
 *
 * Seeing: workload.view within scope (department heads — their departments, teachers — themselves).
 * Planning: workload.manage. Manual actual hours: workload.manage or the teacher of the line.
 */
final class WorkloadController extends Controller
{
    private WorkloadRepository $workload;
    private WorkloadService $service;

    public function __construct()
    {
        $this->workload = new WorkloadRepository();
        $this->service = new WorkloadService($this->workload);
    }

    public function index(Request $request): Response
    {
        $yearId = $this->yearId($request);
        $me = AccessScope::teacherId();
        $departments = Auth::can('workload.manage') ? null : AccessScope::departmentIds();
        if ($departments === [] && $me !== null) {
            return $this->redirect('/workload/teachers/' . $me . '?year=' . $yearId); // a teacher sees only own workload
        }
        $department = ctype_digit((string) $request->query('department', '')) ? (int) $request->query('department') : null;
        $teachers = $this->workload->teachers($yearId, $departments, $me, $department);
        $totals = [];
        if ($teachers !== []) {
            $report = $this->service->report($this->service->lines(['year' => $yearId, 'teachers' => array_map('intval', array_column($teachers, 'teacher_id')), 'department' => $department]), 'teacher', $yearId);
            foreach ($report['rows'] as $row) {
                $totals[$row['teacher_id']] = $row;
            }
        }

        return $this->view('workloads/index', [
            'title' => __('workload.title'),
            'yearId' => $yearId,
            'department' => $department,
            'teachers' => $teachers,
            'totals' => $totals,
            'years' => (new OptionsRepository())->academicYears(),
            'departments' => (new OptionsRepository())->departments(!Auth::can('workload.manage')),
        ]);
    }

    public function teacher(Request $request, int $id): Response
    {
        $yearId = $this->yearId($request);
        $teacher = (new TeacherRepository())->find($id) ?? throw new HttpException(404);
        if (!$this->service->canSeeTeacher($yearId, $id)) {
            throw new HttpException(403);
        }
        $plan = $this->workload->planFor($yearId, $id);
        $lines = $this->service->lines(['year' => $yearId, 'teacher' => $id]);
        $report = $this->service->report($lines, 'detail', $yearId);
        $year = Database::fetch('SELECT id, title, status FROM academic_years WHERE id = ?', [$yearId]);

        return $this->view('workloads/teacher', [
            'title' => __('workload.teacher_title') . ': ' . full_name($teacher, true),
            'teacher' => $teacher,
            'plan' => $plan,
            'year' => $year,
            'lines' => $lines,
            'report' => $report,
            'years' => (new OptionsRepository())->academicYears(),
            'options' => $this->formOptions($yearId),
            'canManage' => $this->canManage($year),
            'planHours' => $plan !== null ? $this->workload->planHours((int) $plan['id']) : 0,
        ]);
    }

    public function savePlan(Request $request, int $id): Response
    {
        $this->authorizeManage();
        (new TeacherRepository())->find($id) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'rate' => 'nullable|decimal:2|between:0.01,3',
            'max_hours' => 'nullable|integer|between:1,3000',
            'note' => 'nullable|string|max:500',
        ]);
        $yearId = (int) $data['academic_year_id'];
        $this->withInput($request, fn (): int => $this->service->savePlan($yearId, $id, [
            'rate' => $data['rate'] ?? null,
            'max_hours' => isset($data['max_hours']) ? (int) $data['max_hours'] : null,
            'note' => ($data['note'] ?? '') !== '' ? (string) $data['note'] : null,
        ], $this->workload->planFor($yearId, $id)));
        Session::flash('success', __('workload.plan_saved'));

        return $this->redirect('/workload/teachers/' . $id . '?year=' . $yearId);
    }

    public function planStatus(Request $request, int $planId): Response
    {
        $this->authorizeManage();
        $plan = $this->workload->plan($planId) ?? throw new HttpException(404);
        $action = (string) $request->input('action', '');
        match ($action) {
            'approve' => $this->service->approve($plan),
            'reopen' => $this->service->reopen($plan, (string) $this->validate($request, ['reason' => 'required|string|max:255'])['reason']),
            'fill' => Session::flash('fill', $this->service->fill($plan)),
            default => throw new HttpException(400),
        };
        Session::flash('success', __('workload.status_' . $action));

        return $this->redirect('/workload/teachers/' . $plan['teacher_id'] . '?year=' . $plan['academic_year_id']);
    }

    public function storeAssignment(Request $request, int $planId): Response
    {
        $this->authorizeManage();
        $plan = $this->workload->plan($planId) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'semester_id' => 'required|integer|exists:semesters,id',
            'group_id' => 'nullable|integer|exists:groups,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'workload_type_id' => 'required|integer|exists:workload_types,id',
            'subgroup' => 'nullable|integer|between:1,4',
            'hours' => 'required|integer|between:1,2000',
            'note' => 'nullable|string|max:255',
        ]);
        $this->withInput($request, fn (): int => $this->service->saveAssignment($plan, [
            'semester_id' => (int) $data['semester_id'],
            'group_id' => isset($data['group_id']) ? (int) $data['group_id'] : null,
            'subject_id' => isset($data['subject_id']) ? (int) $data['subject_id'] : null,
            'workload_type_id' => (int) $data['workload_type_id'],
        ] + $this->lineData($data)));
        Session::flash('success', __('workload.assignment_saved'));

        return $this->redirect('/workload/teachers/' . $plan['teacher_id'] . '?year=' . $plan['academic_year_id']);
    }

    public function assignment(int $id): Response
    {
        $a = $this->findAssignment($id);
        $lines = array_values(array_filter(
            $this->service->lines(['year' => (int) $a['academic_year_id'], 'teacher' => (int) $a['teacher_id'], 'semester' => (int) $a['semester_id']]),
            static fn (array $l): bool => $l['assignment_id'] === $id
        ));

        return $this->view('workloads/assignment', [
            'title' => __('workload.line'),
            'a' => $a,
            'line' => $lines[0] ?? null,
            'teacher' => (new TeacherRepository())->find((int) $a['teacher_id']),
            'actuals' => $this->workload->actuals($id),
            'canManage' => $this->canManage(['status' => $a['year_status']]),
            'canRecord' => $this->canRecord($a),
        ]);
    }

    public function updateAssignment(Request $request, int $id): Response
    {
        $this->authorizeManage();
        $a = $this->findAssignment($id);
        $plan = $this->workload->plan((int) $a['plan_id']) ?? throw new HttpException(404);
        $data = $this->validate($request, [
            'subgroup' => 'nullable|integer|between:1,4',
            'hours' => 'required|integer|between:1,2000',
            'note' => 'nullable|string|max:255',
        ]);
        $this->withInput($request, fn (): int => $this->service->saveAssignment($plan, $this->lineData($data), $a));
        Session::flash('success', __('workload.assignment_saved'));

        return $this->redirect('/workload/assignments/' . $id);
    }

    public function deleteAssignment(int $id): Response
    {
        $this->authorizeManage();
        $a = $this->findAssignment($id);
        $this->service->deleteAssignment($a);
        Session::flash('success', __('workload.assignment_deleted'));

        return $this->redirect('/workload/teachers/' . $a['teacher_id'] . '?year=' . $a['academic_year_id']);
    }

    public function storeActual(Request $request, int $id): Response
    {
        $a = $this->findAssignment($id);
        if (!$this->canRecord($a)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'work_date' => 'required|date',
            'hours' => 'required|string|max:6',
            'note' => 'nullable|string|max:255',
        ]);
        $hours = str_replace(',', '.', trim((string) $data['hours']));
        if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $hours) || (float) $hours <= 0 || (float) $hours > 24) {
            throw new ValidationException(['hours' => [__('workload.fact_hours_invalid')]], $request->all());
        }
        $this->withInput($request, fn (): int => $this->service->addActual($a, [
            'work_date' => (string) $data['work_date'], 'hours' => $hours, 'note' => ($data['note'] ?? '') !== '' ? (string) $data['note'] : null,
        ]));
        Session::flash('success', __('workload.fact_saved'));

        return $this->redirect('/workload/assignments/' . $id);
    }

    public function deleteActual(int $id): Response
    {
        $actual = $this->workload->actual($id) ?? throw new HttpException(404);
        $a = $this->findAssignment((int) $actual['assignment_id']);
        $own = (int) ($actual['created_by'] ?? 0) === (int) Auth::id();
        if (!($this->canRecord($a) && ($own || Auth::can('workload.manage')))) {
            throw new HttpException(403);
        }
        $this->service->deleteActual($actual, $a);
        Session::flash('success', __('workload.fact_deleted'));

        return $this->redirect('/workload/assignments/' . $a['id']);
    }

    public function reports(Request $request): Response
    {
        [$filters, $by] = $this->reportFilters($request);
        $report = $this->service->report($this->service->lines($filters, $filters['as_of']), $by, $filters['year']);

        return $this->view('workloads/reports', [
            'title' => __('workload.reports'),
            'filters' => $filters,
            'report' => $report,
            'header' => $this->labelHeader($by),
            'options' => $this->reportOptions($filters['year']),
        ]);
    }

    /** GET /api/workloads?year=&semester=&department=&teacher=&group=&type=&as_of=&by= — plan/fact rows as JSON. */
    public function api(Request $request): Response
    {
        [$filters, $by] = $this->reportFilters($request);
        $report = $this->service->report($this->service->lines($filters, $filters['as_of']), $by, $filters['year']);
        $rows = array_map(static fn (array $r): array => [
            'label' => $r['label'],
            'plan' => (int) $r['plan'],
            'fact' => round((float) $r['fact'], 2),
            'remainder' => round((float) $r['remainder'], 2),
            'percent' => $r['percent'],
            'unplanned' => round((float) $r['unplanned'], 2),
            'curriculum' => $r['curriculum'],
            'limit' => $r['limit'] ?? null,
        ], $report['rows']);

        return $this->json([
            'year' => $filters['year'],
            'as_of' => $filters['as_of'],
            'by' => $by,
            'columns' => $this->labelHeader($by),
            'rows' => $rows,
            'totals' => ['plan' => (int) $report['totals']['plan'], 'fact' => round((float) $report['totals']['fact'], 2), 'remainder' => round((float) $report['totals']['remainder'], 2), 'percent' => $report['totals']['percent']],
        ]);
    }

    public function export(Request $request): Response
    {
        [$filters, $by] = $this->reportFilters($request);
        $report = $this->service->report($this->service->lines($filters, $filters['as_of']), $by, $filters['year']);
        $h = static fn ($v): string => WorkloadService::hours($v);
        $rows = [[__('workload.reports') . ': ' . __('workload.by.' . $by), $this->yearTitle($filters['year']), __('workload.as_of') . ' ' . format_date($filters['as_of'])]];
        $header = $this->labelHeader($by);
        if ($report['limits']) {
            array_push($header, __('workload.rate'), __('workload.limit'));
        }
        if ($report['curriculum']) {
            $header[] = __('workload.curriculum');
        }
        array_push($header, __('workload.plan'), __('workload.fact'), __('workload.remainder'), '%', __('workload.unplanned'));
        $rows[] = $header;
        foreach ($report['rows'] as $r) {
            $row = $r['label'];
            if ($report['limits']) {
                array_push($row, $r['rate'] ?? '', $r['limit'] ?? '');
            }
            if ($report['curriculum']) {
                $row[] = $r['curriculum'] ?? '';
            }
            array_push($row, $r['plan'], $h($r['fact']), $h($r['remainder']), $r['percent'] ?? '', $r['unplanned'] > 0 ? $h($r['unplanned']) : '');
            $rows[] = $row;
        }
        $total = array_fill(0, count($this->labelHeader($by)) - 1, '');
        array_unshift($total, __('workload.total'));
        if ($report['limits']) {
            array_push($total, '', $report['totals']['limit'] ?: '');
        }
        if ($report['curriculum']) {
            $total[] = $report['totals']['curriculum'] ?: '';
        }
        array_push($total, $report['totals']['plan'], $h($report['totals']['fact']), $h($report['totals']['remainder']), $report['totals']['percent'] ?? '', $report['totals']['unplanned'] > 0 ? $h($report['totals']['unplanned']) : '');
        $rows[] = $total;

        return Csv::download($rows, 'workload-' . $by . '-' . date('Ymd') . '.csv');
    }

    public function print(Request $request): Response
    {
        [$filters, $by] = $this->reportFilters($request);
        $report = $this->service->report($this->service->lines($filters, $filters['as_of']), $by, $filters['year']);
        $options = $this->reportOptions($filters['year']);

        return $this->view('workloads/print', [
            'title' => __('workload.reports') . ': ' . __('workload.by.' . $by),
            'filters' => $filters,
            'report' => $report,
            'header' => $this->labelHeader($by),
            'yearTitle' => $this->yearTitle($filters['year']),
            'options' => $options,
        ], 'layouts/print');
    }

    // ---- helpers -------------------------------------------------------------

    /** @return array{0: array<string, mixed>, 1: string} */
    private function reportFilters(Request $request): array
    {
        $yearId = $this->yearId($request);
        $int = static fn (string $key): ?int => ctype_digit((string) $request->query($key, '')) ? (int) $request->query($key) : null;
        $asOf = (string) $request->query('as_of', '');
        $filters = [
            'year' => $yearId,
            'semester' => $int('semester'),
            'department' => $int('department'),
            'teacher' => $int('teacher'),
            'group' => $int('group'),
            'type' => $int('type'),
            'as_of' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) && strtotime($asOf) !== false ? $asOf : date('Y-m-d'),
            'teachers' => $this->service->visibleTeachers($yearId),
        ];
        if ($filters['teacher'] !== null && $filters['teachers'] !== null && !in_array($filters['teacher'], $filters['teachers'], true)) {
            throw new HttpException(403);
        }
        $by = (string) $request->query('by', 'teacher');

        return [$filters, in_array($by, WorkloadService::GROUP_BY, true) ? $by : 'teacher'];
    }

    /** @return array<string, array<int|string, string>> */
    private function reportOptions(int $yearId): array
    {
        $options = $this->formOptions($yearId);
        $visible = $this->service->visibleTeachers($yearId);
        $teachers = (new OptionsRepository())->teachers();
        if ($visible !== null) {
            $teachers = array_intersect_key($teachers, array_flip($visible));
        }

        return $options + [
            'years' => (new OptionsRepository())->academicYears(),
            'departments' => (new OptionsRepository())->departments(!Auth::can('workload.manage')),
            'teachers' => $teachers,
        ];
    }

    /** @return array<string, array<int, string>> semesters of the year, groups, subjects, kinds */
    private function formOptions(int $yearId): array
    {
        $semesters = [];
        foreach ((new ScheduleRepository())->semestersOfYear($yearId) as $s) {
            $semesters[(int) $s['id']] = __('dashboard.semester', ['number' => $s['number']]);
        }
        $subjects = [];
        foreach (Database::fetchAll('SELECT id, code, name_ru, name_kk FROM subjects WHERE is_active = 1 ORDER BY code') as $s) {
            $subjects[(int) $s['id']] = $s['code'] . ' ' . localized($s);
        }
        $types = [];
        foreach ($this->workload->types() as $t) {
            $types[(int) $t['id']] = localized($t);
        }
        $groups = [];
        foreach (Database::fetchAll("SELECT id, code FROM `groups` WHERE status = 'active' ORDER BY code") as $g) {
            $groups[(int) $g['id']] = (string) $g['code'];
        }

        return ['semesters' => $semesters, 'groups' => $groups, 'subjects' => $subjects, 'types' => $types];
    }

    /** @return list<string> */
    private function labelHeader(string $by): array
    {
        return match ($by) {
            'teacher' => [__('workload.teacher'), __('fields.department_id')],
            'department' => [__('fields.department_id')],
            'group' => [__('fields.group_id')],
            'subject' => [__('curricula.subject')],
            'type' => [__('fields.workload_type_id')],
            'semester' => [__('fields.semester_id')],
            default => [__('workload.teacher'), __('fields.semester_id'), __('fields.group_id'), __('curricula.subject'), __('fields.workload_type_id')],
        };
    }

    private function yearId(Request $request): int
    {
        $year = $request->query('year', '');
        if (ctype_digit((string) $year) && Database::fetchColumn('SELECT 1 FROM academic_years WHERE id = ?', [(int) $year]) !== null) {
            return (int) $year;
        }

        return (new OptionsRepository())->activeAcademicYearId()
            ?? (int) (Database::fetchColumn('SELECT id FROM academic_years ORDER BY start_date DESC LIMIT 1') ?? throw new HttpException(404));
    }

    private function yearTitle(int $yearId): string
    {
        return str_replace('-', '–', (string) Database::fetchColumn('SELECT title FROM academic_years WHERE id = ?', [$yearId]));
    }

    /** @return array<string, mixed> */
    private function findAssignment(int $id): array
    {
        $a = $this->workload->assignment($id) ?? throw new HttpException(404);
        if (!$this->service->canSeeTeacher((int) $a['academic_year_id'], (int) $a['teacher_id'])) {
            throw new HttpException(403);
        }

        return $a;
    }

    /** @param array<string, mixed>|null $year */
    private function canManage(?array $year): bool
    {
        return Auth::can('workload.manage') && ($year['status'] ?? '') !== 'archived';
    }

    /** @param array<string, mixed> $a */
    private function canRecord(array $a): bool
    {
        if ($a['lesson_type'] !== null || $a['year_status'] === 'archived') {
            return false;
        }
        $me = AccessScope::teacherId();

        return Auth::can('workload.manage') || ($me !== null && $me === (int) $a['teacher_id']);
    }

    private function authorizeManage(): void
    {
        if (!Auth::can('workload.manage')) {
            throw new HttpException(403);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{subgroup: ?int, hours: int, note: ?string}
     */
    private function lineData(array $data): array
    {
        return [
            'subgroup' => isset($data['subgroup']) ? (int) $data['subgroup'] : null,
            'hours' => (int) $data['hours'],
            'note' => ($data['note'] ?? '') !== '' ? (string) $data['note'] : null,
        ];
    }

    private function withInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
    }
}
