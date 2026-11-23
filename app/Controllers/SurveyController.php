<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Csv;
use App\Repositories\OptionsRepository;
use App\Repositories\SurveyRepository;
use App\Services\SurveyService;

/**
 * Surveys: builder (surveys.manage), answering (surveys.respond) and results (surveys.results).
 */
final class SurveyController extends Controller
{
    private SurveyRepository $surveys;
    private SurveyService $service;

    public function __construct()
    {
        $this->surveys = new SurveyRepository();
        $this->service = new SurveyService($this->surveys);
    }

    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();
        $respondent = Auth::can('surveys.respond') ? $this->surveys->respondent($userId) : null;
        $forMe = [];
        if ($respondent !== null) {
            foreach ($this->surveys->openSurveys($userId, date('Y-m-d')) as $s) {
                if ($this->service->matches($s, $respondent)) {
                    $forMe[] = $s;
                }
            }
        }
        $staff = Auth::can('surveys.manage') || Auth::can('surveys.results');
        $status = (string) $request->query('status', '');

        return $this->view('surveys/index', [
            'title' => __('surveys.title'),
            'forMe' => $forMe,
            'respondent' => $respondent !== null,
            'staff' => $staff,
            'status' => $status,
            'list' => $staff ? $this->surveys->list($status !== '' ? $status : null) : [],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $id = $this->withInput($request, fn (): int => $this->service->save($request->all(), null));
        Session::flash('success', __('surveys.created'));

        return $this->redirect('/surveys/' . $id . '/edit');
    }

    public function edit(int $id): Response
    {
        return $this->form($this->find($id));
    }

    public function update(Request $request, int $id): Response
    {
        $survey = $this->find($id);
        $this->withInput($request, fn (): int => $this->service->save($request->all(), $survey));
        Session::flash('success', __('surveys.saved'));

        return $this->redirect('/surveys/' . $id . '/edit');
    }

    public function status(Request $request, int $id): Response
    {
        $survey = $this->find($id);
        $action = (string) $request->input('action', '');
        $target = '/surveys/' . $id . '/edit';
        match ($action) {
            'publish' => $this->service->publish($survey),
            'close' => $this->service->close($survey),
            'delete' => (function () use ($survey, &$target): void {
                $this->service->delete($survey);
                $target = '/surveys';
            })(),
            'duplicate' => (function () use ($survey, &$target): void {
                $target = '/surveys/' . $this->service->duplicate($survey) . '/edit';
            })(),
            default => throw new HttpException(400),
        };
        Session::flash('success', __('surveys.status_done.' . $action));

        return $this->redirect($target);
    }

    public function storeQuestion(Request $request, int $id): Response
    {
        $survey = $this->find($id);
        $this->withInput($request, fn (): int => $this->service->saveQuestion($survey, $request->all(), null));
        Session::flash('success', __('surveys.question_saved'));

        return $this->redirect('/surveys/' . $id . '/edit#questions');
    }

    public function editQuestion(int $id, int $questionId): Response
    {
        $survey = $this->find($id);
        $question = $this->findQuestion($survey, $questionId);

        return $this->view('surveys/question', ['title' => __('surveys.edit_question'), 'survey' => $survey, 'q' => $question]);
    }

    public function updateQuestion(Request $request, int $id, int $questionId): Response
    {
        $survey = $this->find($id);
        $question = $this->findQuestion($survey, $questionId);
        $this->withInput($request, fn (): int => $this->service->saveQuestion($survey, $request->all(), $question));
        Session::flash('success', __('surveys.question_saved'));

        return $this->redirect('/surveys/' . $id . '/edit#questions');
    }

    public function deleteQuestion(int $id, int $questionId): Response
    {
        $survey = $this->find($id);
        $this->service->deleteQuestion($survey, $this->findQuestion($survey, $questionId));
        Session::flash('success', __('surveys.question_deleted'));

        return $this->redirect('/surveys/' . $id . '/edit#questions');
    }

    public function moveQuestion(Request $request, int $id, int $questionId): Response
    {
        $survey = $this->find($id);
        $this->service->moveQuestion($survey, $this->findQuestion($survey, $questionId), $request->input('direction') === 'up' ? -1 : 1);

        return $this->redirect('/surveys/' . $id . '/edit#questions');
    }

    /** Answer form for respondents; staff see a read-only preview. */
    public function show(int $id): Response
    {
        $survey = $this->find($id);
        $userId = (int) Auth::id();
        $respondent = Auth::can('surveys.respond') ? $this->surveys->respondent($userId) : null;
        $reason = $this->service->cannotAnswer($survey, $userId, $respondent);
        $staff = Auth::can('surveys.manage') || Auth::can('surveys.results');
        if ($reason !== null && !$staff && $reason !== 'surveys.already_answered') {
            throw new HttpException(403);
        }

        return $this->view('surveys/answer', [
            'title' => localized($survey, 'title'),
            'survey' => $survey,
            'questions' => $this->surveys->questions($id),
            'reason' => $reason,
            'staff' => $staff,
        ]);
    }

    public function answer(Request $request, int $id): Response
    {
        $survey = $this->find($id);
        $userId = (int) Auth::id();
        $respondent = Auth::can('surveys.respond') ? $this->surveys->respondent($userId) : null;
        if ($respondent === null) {
            throw new HttpException(403);
        }
        $answers = $request->input('answers', []);
        $this->withInput($request, fn () => $this->service->respond($survey, $userId, $respondent, is_array($answers) ? $answers : []));
        Session::flash('success', __('surveys.thanks'));

        return $this->redirect('/surveys');
    }

    public function results(Request $request, int $id): Response
    {
        $survey = $this->findForResults($id);

        return $this->view('surveys/results', [
            'title' => __('surveys.results') . ': ' . localized($survey, 'title'),
            'survey' => $survey,
            'results' => $this->service->results($survey),
            'compare' => $this->service->compare($survey, (string) $request->query('by', 'respondent_role')),
            'participants' => (bool) $survey['is_anonymous'] ? [] : $this->surveys->participants($id),
        ]);
    }

    public function export(Request $request, int $id): Response
    {
        $survey = $this->findForResults($id);
        $questions = $this->surveys->questions($id);
        $named = !(bool) $survey['is_anonymous'];
        $options = [];
        foreach ($questions as $q) {
            foreach ($q['options'] as $o) {
                $options[(int) $o['id']] = localized($o, 'label');
            }
        }
        $raw = $this->surveys->rawResponses($id, $named);
        $departments = $this->surveys->labels('departments', array_column($raw, 'department_id'));
        $header = $named ? [__('users.name'), __('fields.group_id'), __('surveys.submitted_at')] : [__('surveys.submitted_on')];
        array_push($header, __('surveys.role'), __('fields.department_id'), __('fields.course'));
        foreach ($questions as $i => $q) {
            $header[] = ($i + 1) . '. ' . localized($q, 'text');
        }
        $rows = [[localized($survey, 'title'), (bool) $survey['is_anonymous'] ? __('surveys.anonymous') : __('surveys.named')], $header];
        foreach ($raw as $r) {
            $row = $named
                ? [$r['last_name'] !== null ? full_name($r) : '—', $r['group_code'] ?? '', format_date((string) $r['submitted_at'], 'd.m.Y H:i')]
                : [format_date((string) $r['submitted_on'])];
            array_push($row, __('surveys.roles.' . $r['respondent_role']), $r['department_id'] !== null ? ($departments[(int) $r['department_id']] ?? '') : '', $r['course'] ?? '');
            foreach ($questions as $q) {
                $values = [];
                foreach ($r['answers'][(int) $q['id']] ?? [] as $a) {
                    $values[] = $a['option_id'] !== null ? ($options[(int) $a['option_id']] ?? '') : ($a['value_int'] ?? $a['value_text']);
                }
                $row[] = implode('; ', array_map('strval', $values));
            }
            $rows[] = $row;
        }

        return Csv::download($rows, 'survey-' . $id . '-' . date('Ymd') . '.csv');
    }

    public function print(Request $request, int $id): Response
    {
        $survey = $this->findForResults($id);

        return $this->view('surveys/print', [
            'title' => __('surveys.results') . ': ' . localized($survey, 'title'),
            'survey' => $survey,
            'results' => $this->service->results($survey),
            'compare' => $this->service->compare($survey, (string) $request->query('by', 'respondent_role')),
        ], 'layouts/print');
    }

    // ---- helpers -------------------------------------------------------------

    private function form(?array $survey): Response
    {
        $options = new OptionsRepository();

        return $this->view('surveys/form', [
            'title' => $survey !== null ? localized($survey, 'title') : __('surveys.create'),
            'survey' => $survey,
            'questions' => $survey !== null ? $this->surveys->questions((int) $survey['id']) : [],
            'departments' => $options->departments(false, true),
            'groups' => $options->groups(),
        ]);
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->surveys->find($id) ?? throw new HttpException(404);
    }

    /** @return array<string, mixed> */
    private function findForResults(int $id): array
    {
        if (!Auth::can('surveys.results') && !Auth::can('surveys.manage')) {
            throw new HttpException(403);
        }
        $survey = $this->find($id);
        if ($survey['status'] === 'draft') {
            throw new HttpException(404);
        }

        return $survey;
    }

    /** @return array<string, mixed> */
    private function findQuestion(array $survey, int $questionId): array
    {
        $q = $this->surveys->question($questionId);
        if ($q === null || (int) $q['survey_id'] !== (int) $survey['id']) {
            throw new HttpException(404);
        }

        return $q;
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
