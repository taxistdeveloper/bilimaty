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
use App\Repositories\ExamRepository;
use App\Repositories\JournalRepository;
use App\Repositories\ScheduleRepository;
use App\Services\AccessScope;
use App\Services\ExamService;
use DateTimeImmutable;

/**
 * Exam schedule, statements and retakes.
 *   view:   exams.view — examiner, journal teacher, or the group within AccessScope
 *   manage: exams.manage within AccessScope (create, edit, reopen, retakes)
 *   grade:  the examiner of the event, or a manager
 */
final class ExamController extends Controller
{
    private ExamRepository $exams;

    public function __construct()
    {
        $this->exams = new ExamRepository();
    }

    public function index(Request $request): Response
    {
        if (!Auth::canAny('exams.manage', 'groups.view', 'journal.manage') && (new JournalRepository())->studentIdOfUser((int) Auth::id()) !== null) {
            return $this->redirect('/exams/my');
        }
        $int = static fn (string $k): ?int => ctype_digit((string) $request->query($k, '')) ? (int) $request->query($k) : null;
        $filters = [
            'semester' => $int('semester') ?? ((new ScheduleRepository())->semesterOn(date('Y-m-d'))['id'] ?? null),
            'group' => $int('group'),
            'type' => $int('type'),
            'status' => in_array($request->query('status'), ['planned', 'open', 'closed'], true) ? (string) $request->query('status') : '',
        ];

        return $this->view('exams/index', [
            'title' => __('exams.title'),
            'filters' => $filters,
            'exams' => $this->exams->list($filters, AccessScope::teacherId()),
            'options' => $this->options($filters['semester'] !== null ? (int) $filters['semester'] : null),
            'canManage' => Auth::can('exams.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $semesterId = ctype_digit((string) $request->query('semester', '')) ? (int) $request->query('semester') : ((new ScheduleRepository())->semesterOn(date('Y-m-d'))['id'] ?? null);

        return $this->form(null, ['exam_form' => 'written', 'exam_date' => date('Y-m-d')], $semesterId !== null ? (int) $semesterId : null);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, true);
        $journal = (new JournalRepository())->find((int) $data['assignment_id']) ?? throw new HttpException(404);
        if (!AccessScope::allowsGroup((int) $journal['group_id'])) {
            throw new HttpException(403);
        }
        $id = $this->withInput($request, fn (): int => (new ExamService($this->exams))->save($data));
        Session::flash('success', __('exams.created'));

        return $this->redirect('/exams/' . $id);
    }

    public function edit(int $id): Response
    {
        $exam = $this->find($id);
        $this->ensureManage($exam);

        return $this->form($exam, $exam, (int) $exam['semester_id']);
    }

    public function update(Request $request, int $id): Response
    {
        $exam = $this->find($id);
        $this->ensureManage($exam);
        $data = $this->validated($request, false);
        $this->withInput($request, fn (): int => (new ExamService($this->exams))->save($data, $exam));
        Session::flash('success', __('exams.updated'));

        return $this->redirect('/exams/' . $id);
    }

    public function show(int $id): Response
    {
        $exam = $this->find($id);
        if (!$this->canView($exam)) {
            throw new HttpException(403);
        }
        $service = new ExamService($this->exams);
        $journal = (new JournalRepository())->find((int) $exam['assignment_id']);

        return $this->view('exams/show', [
            'title' => __('exams.statement') . ' ' . $exam['statement_number'],
            'exam' => $exam,
            'rows' => $service->statement($exam),
            'retakes' => $this->exams->retakes($id),
            'scheme' => (new \App\Services\JournalService())->scheme($journal),
            'canGrade' => $this->canGrade($exam),
            'canManage' => $this->canManage($exam),
            'teachers' => $this->options(null)['teachers'],
        ]);
    }

    public function saveStatement(Request $request, int $id): Response
    {
        $exam = $this->find($id);
        if (!$this->canGrade($exam)) {
            throw new HttpException(403);
        }
        $rows = $request->input('rows', []);
        $this->withInput($request, fn () => (new ExamService($this->exams))->saveStatement($exam, is_array($rows) ? $rows : []));
        Session::flash('success', __('exams.statement_saved'));

        return $this->redirect('/exams/' . $id);
    }

    public function status(Request $request, int $id): Response
    {
        $exam = $this->find($id);
        $action = (string) $request->input('action', '');
        $service = new ExamService($this->exams);
        match ($action) {
            'close' => $this->canGrade($exam) ? $service->close($exam) : throw new HttpException(403),
            'reopen' => $this->canManage($exam)
                ? $service->reopen($exam, (string) $this->validate($request, ['reason' => 'required|string|max:255'])['reason'])
                : throw new HttpException(403),
            'delete' => $this->canManage($exam) ? $service->delete($exam) : throw new HttpException(403),
            default => throw new HttpException(400),
        };
        Session::flash('success', __('exams.status_' . $action));

        return $this->redirect($action === 'delete' ? '/exams' : '/exams/' . $id);
    }

    public function print(int $id): Response
    {
        $exam = $this->find($id);
        if (!$this->canView($exam)) {
            throw new HttpException(403);
        }
        $journal = (new JournalRepository())->find((int) $exam['assignment_id']);

        $exam['course'] = \App\Services\Order130::courseIn((int) $exam['course'], (int) $journal['academic_year_id']);

        return $this->view('exams/print', [
            'title' => __('exams.statement') . ' ' . $exam['statement_number'],
            'exam' => $exam,
            'rows' => (new ExamService($this->exams))->statement($exam),
            'scheme' => (new \App\Services\JournalService())->scheme($journal),
            'group' => \App\Services\Order130::group((int) $exam['group_id']),
        ], 'layouts/print');
    }

    public function scheduleRetake(Request $request, int $id): Response
    {
        $exam = $this->find($id);
        $this->ensureManage($exam);
        $data = $this->validate($request, [
            'student_id' => 'required|integer',
            'retake_date' => 'required|date',
            'examiner_teacher_id' => 'required|integer|exists:teachers,id',
            'reason' => 'required|string|max:255',
            'order_number' => 'nullable|string|max:50',
        ]);
        $this->withInput($request, fn (): int => (new ExamService($this->exams))->scheduleRetake($exam, [
            'student_id' => (int) $data['student_id'], 'retake_date' => (string) $data['retake_date'],
            'examiner_teacher_id' => (int) $data['examiner_teacher_id'], 'reason' => (string) $data['reason'],
            'order_number' => ($data['order_number'] ?? '') !== '' ? (string) $data['order_number'] : null,
        ]));
        Session::flash('success', __('exams.retake_scheduled'));

        return $this->redirect('/exams/' . $id . '#retakes');
    }

    public function completeRetake(Request $request, int $id, int $retakeId): Response
    {
        $exam = $this->find($id);
        $retake = $this->exams->retake($retakeId);
        if ($retake === null || (int) $retake['exam_id'] !== $id) {
            throw new HttpException(404);
        }
        $me = AccessScope::teacherId();
        if (!$this->canManage($exam) && ($me === null || (int) $retake['examiner_teacher_id'] !== $me)) {
            throw new HttpException(403);
        }
        $service = new ExamService($this->exams);
        if ($request->input('action') === 'cancel') {
            if (!$this->canManage($exam)) {
                throw new HttpException(403);
            }
            $service->cancelRetake($retake);
            Session::flash('success', __('exams.retake_cancelled'));
        } else {
            $this->withInput($request, fn () => $service->completeRetake($exam, $retake, (string) $request->input('value', ''), $request->input('absent') === '1'));
            Session::flash('success', __('exams.retake_completed'));
        }

        return $this->redirect('/exams/' . $id . '#retakes');
    }

    /** Student: own exams, ratings and results. */
    public function my(): Response
    {
        $studentId = (new JournalRepository())->studentIdOfUser((int) Auth::id()) ?? throw new HttpException(403);

        return $this->view('exams/my', ['title' => __('exams.my_title'), 'exams' => $this->exams->forStudent($studentId)]);
    }

    /**
     * @param array<string, mixed>|null $exam
     * @param array<string, mixed> $values
     */
    private function form(?array $exam, array $values, ?int $semesterId): Response
    {
        return $this->view('exams/form', [
            'title' => $exam === null ? __('exams.create') : __('exams.edit'),
            'exam' => $exam,
            'values' => $values,
            'options' => $this->options($semesterId),
            'semesterId' => $semesterId,
            'forms' => ExamService::FORMS,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $isNew): array
    {
        $rules = [
            'exam_form' => 'required|in:' . implode(',', ExamService::FORMS),
            'period_start' => 'nullable|date',
            'exam_date' => 'required|date',
            'start_time' => 'nullable|time',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'examiner_teacher_id' => 'required|integer|exists:teachers,id',
            'note' => 'nullable|string|max:500',
        ];
        if ($isNew) {
            $rules['assignment_id'] = 'required|integer|exists:teacher_subject_assignments,id';
            $rules['assessment_type_id'] = 'required|integer|exists:assessment_types,id';
        }
        $data = $this->validate($request, $rules);

        return [
            'assignment_id' => isset($data['assignment_id']) ? (int) $data['assignment_id'] : null,
            'assessment_type_id' => isset($data['assessment_type_id']) ? (int) $data['assessment_type_id'] : null,
            'exam_form' => (string) $data['exam_form'],
            'period_start' => ($data['period_start'] ?? '') !== '' ? (string) $data['period_start'] : null,
            'exam_date' => (string) $data['exam_date'],
            'start_time' => ($data['start_time'] ?? '') !== '' ? substr((string) $data['start_time'], 0, 5) . ':00' : null,
            'classroom_id' => !empty($data['classroom_id']) ? (int) $data['classroom_id'] : null,
            'examiner_teacher_id' => (int) $data['examiner_teacher_id'],
            'note' => ($data['note'] ?? '') !== '' ? (string) $data['note'] : null,
        ];
    }

    /** @return array<string, array<int, string>> */
    private function options(?int $semesterId): array
    {
        $map = static function (string $sql, callable $label, array $params = []): array {
            $o = [];
            foreach (Database::fetchAll($sql, $params) as $r) {
                $o[(int) $r['id']] = $label($r);
            }

            return $o;
        };
        [$scopeSql, $scopeParams] = AccessScope::condition('a.group_id', AccessScope::groupIds());

        return [
            'semesters' => $map(
                'SELECT s.id, s.number, y.title FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY s.start_date DESC',
                static fn (array $r): string => str_replace('-', '–', (string) $r['title']) . ', ' . __('dashboard.semester', ['number' => $r['number']])
            ),
            'journals' => $semesterId === null ? [] : $map(
                "SELECT a.id, g.code, sb.code AS subject_code, sb.name_ru, sb.name_kk FROM teacher_subject_assignments a
                 JOIN `groups` g ON g.id = a.group_id JOIN subjects sb ON sb.id = a.subject_id
                 WHERE a.semester_id = ? AND a.grading_scheme_id IS NOT NULL AND {$scopeSql} ORDER BY g.code, sb.code",
                static fn (array $r): string => $r['code'] . ' — ' . $r['subject_code'] . ' ' . localized($r),
                [$semesterId, ...$scopeParams]
            ),
            'types' => $map("SELECT id, name_ru, name_kk FROM assessment_types WHERE source = 'manual' AND is_active = 1 ORDER BY sort_order", static fn (array $r): string => localized($r)),
            'groups' => $map("SELECT id, code FROM `groups` WHERE status = 'active' ORDER BY code", static fn (array $r): string => (string) $r['code']),
            'teachers' => $map(
                "SELECT t.id, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.status <> 'dismissed' ORDER BY u.last_name",
                static fn (array $r): string => full_name($r, true)
            ),
            'classrooms' => $map('SELECT id, number, building FROM classrooms WHERE is_active = 1 ORDER BY building, number', static fn (array $r): string => trim(($r['building'] !== '' ? $r['building'] . '-' : '') . $r['number'])),
        ];
    }

    private function canManage(array $exam): bool
    {
        return Auth::can('exams.manage') && AccessScope::allowsGroup((int) $exam['group_id']) && $exam['year_status'] !== 'archived';
    }

    private function canGrade(array $exam): bool
    {
        $me = AccessScope::teacherId();

        return $exam['year_status'] !== 'archived' && ($this->canManage($exam) || ($me !== null && (int) $exam['examiner_teacher_id'] === $me));
    }

    private function canView(array $exam): bool
    {
        if ($this->canGrade($exam)) {
            return true;
        }
        $me = AccessScope::teacherId();
        if ($me !== null && (int) $exam['journal_teacher_id'] === $me) {
            return true;
        }

        return Auth::can('exams.view') && Auth::can('groups.view') && AccessScope::allowsGroup((int) $exam['group_id']);
    }

    private function ensureManage(array $exam): void
    {
        if (!$this->canManage($exam)) {
            throw new HttpException(403);
        }
    }

    private function withInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->exams->find($id) ?? throw new HttpException(404);
    }
}
