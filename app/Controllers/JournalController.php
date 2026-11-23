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
use App\Policies\JournalPolicy;
use App\Repositories\GradingSchemeRepository;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;
use App\Services\AccessScope;
use App\Services\JournalService;

/**
 * Electronic journal pages. Cell autosave goes through Api\JournalController.
 */
final class JournalController extends Controller
{
    private JournalRepository $journals;
    private JournalPolicy $policy;

    public function __construct()
    {
        $this->journals = new JournalRepository();
        $this->policy = new JournalPolicy($this->journals);
    }

    public function index(Request $request): Response
    {
        // A student is sent to "My grades".
        if (!Auth::canAny('groups.view', 'journal.manage') && $this->journals->studentIdOfUser((int) Auth::id()) !== null) {
            return $this->redirect('/grades');
        }
        $int = static fn (string $k): ?int => ctype_digit((string) $request->query($k, '')) ? (int) $request->query($k) : null;
        $semesterId = $int('semester') ?? ((new ScheduleRepository())->semesterOn(date('Y-m-d'))['id'] ?? null);
        $filters = [
            'semester' => $semesterId !== null ? (int) $semesterId : null,
            'group' => $int('group'), 'subject' => $int('subject'), 'teacher' => $int('teacher'),
            'status' => in_array($request->query('status'), ['open', 'submitted', 'approved'], true) ? (string) $request->query('status') : '',
        ];
        $mine = $request->query('mine') === '1';

        return $this->view('journals/index', [
            'title' => __('nav.journal'),
            'filters' => $filters,
            'mine' => $mine,
            'journals' => $this->journals->list($filters, $this->policy->myTeacherId(), $mine),
            'options' => $this->options(),
            'canAdminister' => $this->policy->administer(),
        ]);
    }

    public function show(int $id): Response
    {
        $journal = $this->find($id);
        if (!$this->policy->view($journal)) {
            throw new HttpException(403);
        }
        $service = new JournalService($this->journals);

        return $this->view('journals/show', [
            'title' => localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]) . ' · ' . $journal['group_code'],
            'journal' => $journal,
            'grid' => $service->grid($journal),
            'canEdit' => $this->policy->edit($journal),
            'canSubmit' => $this->policy->submit($journal),
            'canApprove' => $this->policy->approve($journal),
            'canAdminister' => $this->policy->administer($journal),
            'periods' => (new ScheduleRepository())->periods(),
            'teachers' => $this->options()['teachers'],
            'schemes' => (new GradingSchemeRepository())->activeOptions(),
        ]);
    }

    public function store(Request $request): Response
    {
        if (!$this->policy->administer()) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'semester_id' => 'required|integer|exists:semesters,id',
            'group_id' => 'required|integer|exists:groups,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'grading_scheme_id' => 'nullable|integer|exists:grading_schemes,id',
        ]);
        if ($this->journals->exists((int) $data['semester_id'], (int) $data['group_id'], (int) $data['subject_id'])) {
            throw new ValidationException(['subject_id' => [__('journal.exists')]], $request->all());
        }
        $this->assertActiveScheme($data['grading_scheme_id'] ?? null, $request);
        $id = $this->journals->create([
            'semester_id' => (int) $data['semester_id'], 'group_id' => (int) $data['group_id'], 'subject_id' => (int) $data['subject_id'],
            'teacher_id' => (int) $data['teacher_id'], 'grading_scheme_id' => !empty($data['grading_scheme_id']) ? (int) $data['grading_scheme_id'] : null,
        ]);
        \App\Services\AuditLogger::log('journal.created', 'teacher_subject_assignments', $id, null, $data);
        Session::flash('success', __('journal.created'));

        return $this->redirect('/journal/' . $id);
    }

    public function generate(Request $request): Response
    {
        if (!$this->policy->administer()) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'semester_id' => 'required|integer|exists:semesters,id',
            'grading_scheme_id' => 'nullable|integer|exists:grading_schemes,id',
        ]);
        $this->assertActiveScheme($data['grading_scheme_id'] ?? null, $request);
        $count = (new JournalService($this->journals))->generateFromTimetable((int) $data['semester_id'], !empty($data['grading_scheme_id']) ? (int) $data['grading_scheme_id'] : null);
        Session::flash('success', __('journal.generated', ['count' => $count]));

        return $this->redirect('/journal?semester=' . (int) $data['semester_id']);
    }

    public function settings(Request $request, int $id): Response
    {
        $journal = $this->find($id);
        if (!$this->policy->administer($journal)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'teacher_id' => 'required|integer|exists:teachers,id',
            'grading_scheme_id' => 'nullable|integer|exists:grading_schemes,id',
        ]);
        $schemeId = !empty($data['grading_scheme_id']) ? (int) $data['grading_scheme_id'] : null;
        if ($schemeId !== (int) $journal['grading_scheme_id']) {
            if ($journal['status'] === 'approved') {
                throw new ValidationException(['grading_scheme_id' => [__('journal.scheme_locked')]], $request->all());
            }
            $this->assertActiveScheme($schemeId, $request);
        }
        $this->journals->updateSettings($id, (int) $data['teacher_id'], $schemeId);
        \App\Services\AuditLogger::log('journal.settings', 'teacher_subject_assignments', $id,
            ['teacher_id' => $journal['teacher_id'], 'grading_scheme_id' => $journal['grading_scheme_id']],
            ['teacher_id' => (int) $data['teacher_id'], 'grading_scheme_id' => $schemeId]);
        Session::flash('success', __('journal.settings_saved'));

        return $this->redirect('/journal/' . $id);
    }

    public function saveLesson(Request $request, int $id): Response
    {
        $journal = $this->editable($id);
        $lessonId = ctype_digit((string) $request->input('lesson_id', '')) ? (int) $request->input('lesson_id') : null;
        $lesson = $lessonId !== null ? ($this->journals->lesson($id, $lessonId) ?? throw new HttpException(404)) : null;
        $data = $this->validate($request, [
            'lesson_date' => 'required|date',
            'lesson_period_id' => 'required|integer|exists:lesson_periods,id',
            'lesson_type' => 'required|in:lecture,practice,lab,consultation',
            'topic' => 'nullable|string|max:255',
            'homework' => 'nullable|string|max:500',
            'note' => 'nullable|string|max:500',
            'hours' => 'required|integer|between:1,8',
            'conducted_by' => 'nullable|integer|exists:teachers,id',
        ]);
        $data['lesson_period_id'] = (int) $data['lesson_period_id'];
        $data['hours'] = (int) $data['hours'];
        $data['conducted_by'] = !empty($data['conducted_by']) ? (int) $data['conducted_by'] : (int) $journal['teacher_id'];
        foreach (['topic', 'homework', 'note'] as $k) {
            $data[$k] = ($data[$k] ?? '') !== '' ? $data[$k] : null;
        }
        try {
            (new JournalService($this->journals))->saveLesson($journal, $lesson, $data);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
        Session::flash('success', __('journal.lesson_saved'));

        return $this->redirect('/journal/' . $id);
    }

    public function deleteLesson(int $id, int $lessonId): Response
    {
        $journal = $this->editable($id);
        $lesson = $this->journals->lesson($id, $lessonId) ?? throw new HttpException(404);
        (new JournalService($this->journals))->deleteLesson($journal, $lesson);
        Session::flash('success', __('journal.lesson_deleted'));

        return $this->redirect('/journal/' . $id);
    }

    public function fill(int $id): Response
    {
        $journal = $this->editable($id);
        $count = (new JournalService($this->journals))->fillFromSchedule($journal, date('Y-m-d'));
        Session::flash('success', __('journal.filled', ['count' => $count]));

        return $this->redirect('/journal/' . $id);
    }

    public function status(Request $request, int $id): Response
    {
        $journal = $this->find($id);
        $action = (string) $request->input('action', '');
        $service = new JournalService($this->journals);
        try {
            match ($action) {
                'submit' => $this->policy->submit($journal) ? $service->submit($journal) : throw new HttpException(403),
                'approve' => $this->policy->approve($journal) && $journal['status'] !== 'approved' ? $service->approve($journal) : throw new HttpException(403),
                'reopen' => $this->policy->approve($journal) && $journal['status'] !== 'open'
                    ? $service->reopen($journal, $this->validate($request, ['reason' => 'required|string|max:255'])['reason'])
                    : throw new HttpException(403),
                default => throw new HttpException(400),
            };
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
        Session::flash('success', __('journal.status_' . $action));

        return $this->redirect('/journal/' . $id);
    }

    public function history(Request $request, int $id): Response
    {
        $journal = $this->find($id);
        if (!$this->policy->view($journal)) {
            throw new HttpException(403);
        }
        $studentId = ctype_digit((string) $request->query('student', '')) ? (int) $request->query('student') : null;

        return $this->view('journals/history', [
            'title' => __('journal.history'),
            'journal' => $journal,
            'students' => $this->journals->students($journal),
            'studentId' => $studentId,
            'result' => $this->journals->history($id, $studentId, max(1, (int) $request->query('page', 1))),
        ]);
    }

    /** CSV (UTF-8 with BOM, ";" separator) — opens directly in Excel. */
    public function export(int $id): Response
    {
        $journal = $this->find($id);
        if (!$this->policy->view($journal)) {
            throw new HttpException(403);
        }
        $grid = (new JournalService($this->journals))->grid($journal);
        $rows = [];
        $rows[] = [localized(['name_ru' => $journal['subject_name_ru'], 'name_kk' => $journal['subject_name_kk']]), $journal['group_code'],
            $journal['year_title'] . ', ' . __('dashboard.semester', ['number' => $journal['semester_number']])];
        $header = ['№', __('users.name'), __('fields.student_number')];
        foreach ($grid['lessons'] as $lesson) {
            $header[] = format_date($lesson['lesson_date'], 'd.m') . ' (' . $lesson['period_number'] . ')';
        }
        foreach ($grid['components'] as $c) {
            $header[] = localized($c, 'short');
        }
        array_push($header, __('journal.final'), __('journal.absences'));
        $rows[] = $header;
        foreach ($grid['students'] as $i => $s) {
            $row = [$i + 1, full_name($s), $s['student_number']];
            foreach ($grid['lessons'] as $lesson) {
                $cell = $s['cells'][(int) $lesson['id']];
                $row[] = $cell['attendance'] === 'absent' ? 'н' : ($cell['attendance'] === 'excused' ? 'у' : (JournalService::normalize($cell['grade']) ?? ''));
            }
            foreach ($grid['components'] as $c) {
                $row[] = JournalService::normalize($s['result']['components'][(int) $c['assessment_type_id']] ?? null) ?? '';
            }
            $row[] = JournalService::normalize($s['result']['fixed_final'] ?? $s['result']['final']) ?? '';
            $row[] = $s['result']['absent'] + $s['result']['excused'];
            $rows[] = $row;
        }

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            // Neutralise spreadsheet formulas in text cells (CSV injection).
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ';', '"', '\\');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        $filename = 'journal-' . $id . '-' . date('Ymd') . '.csv';

        return (new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]));
    }

    public function print(int $id): Response
    {
        $journal = $this->find($id);
        if (!$this->policy->view($journal)) {
            throw new HttpException(403);
        }

        // Form of Order No. 130 (MES RK, 06.04.2020): "Журнал учета теоретического обучения".
        $group = \App\Services\Order130::group((int) $journal['group_id']);
        $group['course'] = $journal['course'] = \App\Services\Order130::courseIn((int) $journal['course'], (int) $journal['academic_year_id']);

        return $this->view('journals/print', [
            'title' => __('o130.journal_title'),
            'journal' => $journal,
            'grid' => (new JournalService($this->journals))->grid($journal),
            'group' => $group,
            'item' => \App\Services\Order130::curriculumItem($group, (int) $journal['subject_id'], (int) $journal['semester_number']),
        ], 'layouts/print');
    }

    /** "My grades": the signed-in student's own results only. */
    /** Teacher: subjects taught this semester with their groups. */
    public function subjects(): Response
    {
        $teacherId = AccessScope::teacherId() ?? throw new HttpException(403);

        return $this->view('journals/subjects', ['title' => __('teaching.my_subjects'), 'subjects' => (new \App\Services\TeachingService())->subjects($teacherId)]);
    }

    /** Old address of the student's grades (kept for bookmarks). */
    public function my(): Response
    {
        return $this->redirect('/grades');
    }

    public function myShow(int $id): Response
    {
        return $this->redirect('/grades/' . $id);
    }

    /** @return array<string, array<int, string>> */
    private function options(): array
    {
        $map = static function (string $sql, callable $label): array {
            $o = [];
            foreach (Database::fetchAll($sql) as $r) {
                $o[(int) $r['id']] = $label($r);
            }

            return $o;
        };

        return [
            'semesters' => $map(
                'SELECT s.id, s.number, y.title FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY s.start_date DESC',
                static fn (array $r): string => str_replace('-', '–', (string) $r['title']) . ', ' . __('dashboard.semester', ['number' => $r['number']])
            ),
            'groups' => $map("SELECT id, code FROM `groups` WHERE status = 'active' ORDER BY code", static fn (array $r): string => (string) $r['code']),
            'subjects' => $map('SELECT id, code, name_ru, name_kk FROM subjects WHERE is_active = 1 ORDER BY code', static fn (array $r): string => $r['code'] . ' — ' . localized($r)),
            'teachers' => $map(
                "SELECT t.id, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.status <> 'dismissed' ORDER BY u.last_name",
                static fn (array $r): string => full_name($r, true)
            ),
            'schemes' => (new GradingSchemeRepository())->activeOptions(),
        ];
    }

    private function assertActiveScheme(mixed $schemeId, Request $request): void
    {
        if ($schemeId === null || $schemeId === '') {
            return;
        }
        if (!isset((new GradingSchemeRepository())->activeOptions()[(int) $schemeId])) {
            throw new ValidationException(['grading_scheme_id' => [__('journal.scheme_not_active')]], $request->all());
        }
    }

    /** @return array<string, mixed> */
    private function editable(int $id): array
    {
        $journal = $this->find($id);
        if (!$this->policy->edit($journal)) {
            throw new HttpException(403, __('journal.locked'));
        }

        return $journal;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->journals->find($id) ?? throw new HttpException(404);
    }
}
