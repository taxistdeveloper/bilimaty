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
use App\Repositories\PracticeRepository;
use App\Services\AccessScope;
use App\Services\PracticeService;

/**
 * Practice schedules.
 *  - academic office (practice.manage): assigns practices, approves or returns schedules;
 *  - department heads (practice.view): see the practices of their groups;
 *  - a supervising teacher (practice.fill): fills in and submits own schedules.
 * Every page checks the record: a teacher reaches only own practices, a viewer only groups in scope.
 */
final class PracticeController extends Controller
{
    private PracticeRepository $practices;
    private PracticeService $service;

    public function __construct()
    {
        $this->practices = new PracticeRepository();
        $this->service = new PracticeService($this->practices);
    }

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), PracticeService::STATUSES, true) ? (string) $request->query('status') : '';
        $filters = [
            'status' => $status,
            'teacher' => (int) $request->query('teacher', 0),
            'group' => (int) $request->query('group', 0),
            'overdue' => $request->query('overdue') === '1',
        ];
        [$groupIds, $teacherId] = $this->scope();

        return $this->view('practice/index', [
            'title' => __('practice.title'),
            'list' => $this->practices->list($filters, $groupIds, $teacherId),
            'counts' => $this->practices->counts($groupIds, $teacherId),
            'filters' => $filters,
            'own' => !$this->isViewer(),
            'teachers' => $this->isViewer() ? $this->teachers() : [],
            'groups' => $this->isViewer() ? $this->groups() : [],
        ]);
    }

    public function create(): Response
    {
        $this->ensureManage();

        return $this->view('practice/form', ['title' => __('practice.assign'), 'assignment' => null, 'teachers' => $this->teachers(), 'groups' => $this->groups()]);
    }

    public function store(Request $request): Response
    {
        $this->ensureManage();
        $data = $this->validate($request, PracticeService::rules());
        $id = $this->keepInput($request, fn (): int => $this->service->save($data, null));
        Session::flash('success', __('practice.assigned'));

        return $this->redirect('/practice/' . $id);
    }

    public function edit(int $id): Response
    {
        $this->ensureManage();
        $assignment = $this->find($id);

        return $this->view('practice/form', ['title' => __('practice.edit'), 'assignment' => $assignment, 'teachers' => $this->teachers(), 'groups' => $this->groups()]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->ensureManage();
        $assignment = $this->find($id);
        $data = $this->validate($request, PracticeService::rules());
        $this->keepInput($request, fn (): int => $this->service->save($data, $assignment));
        Session::flash('success', __('practice.updated'));

        return $this->redirect('/practice/' . $id);
    }

    public function destroy(int $id): Response
    {
        $this->ensureManage();
        $this->service->delete($this->find($id));
        Session::flash('success', __('practice.deleted'));

        return $this->redirect('/practice');
    }

    public function show(int $id): Response
    {
        $assignment = $this->find($id);
        $entries = $this->practices->entries($id);
        $own = $this->isOwn($assignment);

        return $this->view('practice/show', [
            'title' => $assignment['title'],
            'assignment' => $assignment,
            'entries' => $entries,
            'summary' => $this->service->summary($entries, (int) $assignment['hours']),
            'editable' => $own && in_array($assignment['status'], PracticeService::EDITABLE, true),
            'own' => $own,
            'canManage' => Auth::can('practice.manage'),
            'holidays' => $this->service->holidaysAround($assignment),
        ]);
    }

    /** Save the draft or submit the schedule (button "action" = save | submit). */
    public function saveSchedule(Request $request, int $id): Response
    {
        $assignment = $this->find($id);
        if (!$this->isOwn($assignment)) {
            throw new HttpException(403);
        }
        $rows = $request->input('entries', []);
        $submit = $request->input('action') === 'submit';
        $this->keepInput($request, fn () => $this->service->saveSchedule($assignment, is_array($rows) ? array_values($rows) : [], (string) $request->input('teacher_comment', ''), $submit));
        Session::flash('success', __($submit ? 'practice.submitted' : 'practice.draft_saved'));

        return $this->redirect('/practice/' . $id);
    }

    public function approve(int $id): Response
    {
        $this->ensureManage();
        $this->service->approve($this->find($id));
        Session::flash('success', __('practice.approved'));

        return $this->redirect('/practice/' . $id);
    }

    public function returnForRework(Request $request, int $id): Response
    {
        $this->ensureManage();
        $assignment = $this->find($id);
        $this->keepInput($request, fn () => $this->service->returnForRework($assignment, (string) $request->input('reason', '')));
        Session::flash('success', __('practice.returned'));

        return $this->redirect('/practice/' . $id);
    }

    /** "График программы прохождения практики" — A4 for printing / PDF. */
    public function print(int $id): Response
    {
        $assignment = $this->find($id);

        return $this->view('practice/print', [
            'title' => __('practice.print_title'),
            'assignment' => $assignment,
            'entries' => $this->practices->entries($id),
        ], 'layouts/print');
    }

    /** Practice sessions on a date (all schedules the user may see). */
    public function day(Request $request): Response
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date', '')) ? (string) $request->query('date') : date('Y-m-d');
        [$groupIds, $teacherId] = $this->scope();

        return $this->view('practice/day', [
            'title' => __('practice.day_title'),
            'date' => $date,
            'rows' => $this->practices->onDate($date, $groupIds, $teacherId),
        ]);
    }

    // ---- access ------------------------------------------------------------------

    private function isViewer(): bool
    {
        return Auth::canAny('practice.view', 'practice.manage');
    }

    /** @return array{0: list<int>|null, 1: int|null} groups in scope (null = all) and own teacher id for teachers */
    private function scope(): array
    {
        if ($this->isViewer()) {
            return [AccessScope::groupIds(), null];
        }

        return [null, AccessScope::teacherId() ?? -1];
    }

    private function isOwn(array $assignment): bool
    {
        return Auth::can('practice.fill') && AccessScope::teacherId() !== null && (int) $assignment['teacher_id'] === AccessScope::teacherId();
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $assignment = $this->practices->find($id) ?? throw new HttpException(404);
        $visible = $this->isOwn($assignment) || ($this->isViewer() && AccessScope::allowsGroup((int) $assignment['group_id']));
        if (!$visible) {
            throw new HttpException(403);
        }

        return $assignment;
    }

    private function ensureManage(): void
    {
        if (!Auth::can('practice.manage')) {
            throw new HttpException(403);
        }
    }

    /** @return array<int, string> active teachers (id => name) */
    private function teachers(): array
    {
        $out = [];
        foreach (Database::fetchAll(
            "SELECT t.id, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.status = 'active' ORDER BY u.last_name, u.first_name"
        ) as $t) {
            $out[(int) $t['id']] = full_name($t, true);
        }

        return $out;
    }

    /** @return array<int, string> active groups in scope (id => code) */
    private function groups(): array
    {
        $out = [];
        foreach (Database::fetchAll("SELECT id, code FROM `groups` WHERE status = 'active' ORDER BY code") as $g) {
            if (AccessScope::allowsGroup((int) $g['id'])) {
                $out[(int) $g['id']] = (string) $g['code'];
            }
        }

        return $out;
    }

    private function keepInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $e->oldInput !== [] ? $e->oldInput : $request->all());
        }
    }
}
