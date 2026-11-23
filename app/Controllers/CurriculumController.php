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
use App\Repositories\CurriculumRepository;
use App\Repositories\OptionsRepository;
use App\Services\CurriculumService;

/**
 * Curricula. View: curricula.view; changes: curricula.manage (drafts only).
 */
final class CurriculumController extends Controller
{
    public const FORMS = ['full_time', 'part_time', 'evening', 'distance'];
    public const BASES = ['basic_secondary', 'general_secondary', 'tvet'];
    public const CONTROL = ['exam', 'credit', 'diff_credit', 'coursework', 'none'];

    private CurriculumRepository $plans;

    public function __construct()
    {
        $this->plans = new CurriculumRepository();
    }

    public function index(Request $request): Response
    {
        $filters = [
            'specialty' => ctype_digit((string) $request->query('specialty', '')) ? (int) $request->query('specialty') : null,
            'status' => in_array($request->query('status'), ['draft', 'approved', 'archived'], true) ? (string) $request->query('status') : '',
            'year' => ctype_digit((string) $request->query('year', '')) ? (int) $request->query('year') : null,
        ];

        return $this->view('curricula/index', [
            'title' => __('curricula.title'),
            'filters' => $filters,
            'plans' => $this->plans->list($filters),
            'specialties' => (new OptionsRepository())->specialties(),
        ]);
    }

    public function show(int $id): Response
    {
        $plan = $this->find($id);
        $items = $this->plans->items($id);
        $bySemester = [];
        foreach ($items as $item) {
            $bySemester[(int) $item['semester']][] = $item;
        }
        ksort($bySemester);

        return $this->view('curricula/show', [
            'title' => $plan['title'],
            'plan' => $plan,
            'bySemester' => $bySemester,
            'groups' => $this->plans->groups($id),
            'subjects' => $this->subjectOptions(),
            'canEdit' => Auth::can('curricula.manage') && $plan['status'] === 'draft',
            'canManage' => Auth::can('curricula.manage'),
            'controls' => self::CONTROL,
        ]);
    }

    /** Print-ready A4 version of the plan (saved as PDF from the browser). */
    public function print(int $id): Response
    {
        $plan = $this->find($id);
        $bySemester = [];
        foreach ($this->plans->items($id) as $item) {
            $bySemester[(int) $item['semester']][] = $item;
        }
        ksort($bySemester);

        return $this->view('curricula/print', ['title' => $plan['title'], 'plan' => $plan, 'bySemester' => $bySemester], 'layouts/print');
    }

    public function create(): Response
    {
        return $this->form(null, ['admission_year' => (int) date('Y'), 'study_form' => 'full_time', 'base_education' => 'basic_secondary', 'semesters_count' => 8]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request);
        $id = $this->withInput($request, fn (): int => (new CurriculumService())->save(null, $data));
        Session::flash('success', __('curricula.created'));

        return $this->redirect('/curricula/' . $id);
    }

    public function edit(int $id): Response
    {
        $plan = $this->find($id);
        if ($plan['status'] !== 'draft') {
            throw new HttpException(403, __('curricula.only_draft'));
        }

        return $this->form($plan, $plan);
    }

    public function update(Request $request, int $id): Response
    {
        $plan = $this->findDraft($id);
        $data = $this->validated($request);
        $this->withInput($request, fn (): int => (new CurriculumService())->save($plan, $data));
        Session::flash('success', __('curricula.updated'));

        return $this->redirect('/curricula/' . $id);
    }

    public function status(Request $request, int $id): Response
    {
        $plan = $this->find($id);
        $action = (string) $request->input('action', '');
        if (!in_array($action, ['approve', 'reopen', 'archive', 'delete'], true)) {
            throw new HttpException(400);
        }
        try {
            (new CurriculumService())->{$action}($plan);
        } catch (ValidationException $e) {
            Session::flash('error', (string) (array_values($e->errors)[0][0] ?? ''));

            return $this->redirect('/curricula/' . $id);
        }
        Session::flash('success', __('curricula.status_' . $action));

        return $this->redirect($action === 'delete' ? '/curricula' : '/curricula/' . $id);
    }

    public function copy(Request $request, int $id): Response
    {
        $plan = $this->find($id);
        $data = $this->validate($request, [
            'admission_year' => 'required|integer|between:2000,2100',
            'title' => 'required|string|max:255',
        ]);
        $newId = $this->withInput($request, fn (): int => (new CurriculumService())->copy($plan, (int) $data['admission_year'], (string) $data['title']));
        Session::flash('success', __('curricula.copied'));

        return $this->redirect('/curricula/' . $newId);
    }

    public function storeItem(Request $request, int $id): Response
    {
        $plan = $this->findDraft($id);
        $data = $this->validatedItem($request);
        $this->withInput($request, fn () => (new CurriculumService())->saveItem($plan, null, $data));
        Session::flash('success', __('curricula.item_saved'));

        return $this->redirect('/curricula/' . $id . '#semester-' . $data['semester']);
    }

    public function editItem(int $id, int $itemId): Response
    {
        $plan = $this->find($id);
        $item = $this->plans->findItem($id, $itemId) ?? throw new HttpException(404);
        if ($plan['status'] !== 'draft') {
            throw new HttpException(403, __('curricula.only_draft'));
        }

        return $this->view('curricula/item', [
            'title' => __('curricula.edit_item'),
            'plan' => $plan,
            'item' => $item,
            'subjects' => $this->subjectOptions(),
            'controls' => self::CONTROL,
        ]);
    }

    public function updateItem(Request $request, int $id, int $itemId): Response
    {
        $plan = $this->findDraft($id);
        $this->plans->findItem($id, $itemId) ?? throw new HttpException(404);
        $data = $this->validatedItem($request);
        $this->withInput($request, fn () => (new CurriculumService())->saveItem($plan, $itemId, $data));
        Session::flash('success', __('curricula.item_saved'));

        return $this->redirect('/curricula/' . $id . '#semester-' . $data['semester']);
    }

    public function destroyItem(int $id, int $itemId): Response
    {
        $plan = $this->findDraft($id);
        $this->plans->findItem($id, $itemId) ?? throw new HttpException(404);
        (new CurriculumService())->deleteItem($plan, $itemId);
        Session::flash('success', __('curricula.item_deleted'));

        return $this->redirect('/curricula/' . $id);
    }

    /**
     * @param array<string, mixed>|null $plan
     * @param array<string, mixed> $values
     */
    private function form(?array $plan, array $values): Response
    {
        $options = new OptionsRepository();

        return $this->view('curricula/form', [
            'title' => $plan === null ? __('curricula.create') : __('curricula.edit'),
            'plan' => $plan,
            'values' => $values,
            'specialties' => $options->specialties(false, true),
            'qualifications' => $options->qualifications(),
            'forms' => self::FORMS,
            'bases' => self::BASES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $this->validate($request, [
            'specialty_id' => 'required|integer|exists:specialties,id',
            'qualification_id' => 'nullable|integer|exists:qualifications,id',
            'title' => 'required|string|max:255',
            'admission_year' => 'required|integer|between:2000,2100',
            'study_form' => 'required|in:' . implode(',', self::FORMS),
            'base_education' => 'required|in:' . implode(',', self::BASES),
            'semesters_count' => 'required|integer|between:1,10',
            'note' => 'nullable|string|max:500',
        ]);
        $data['specialty_id'] = (int) $data['specialty_id'];
        $data['qualification_id'] = !empty($data['qualification_id']) ? (int) $data['qualification_id'] : null;
        $data['admission_year'] = (int) $data['admission_year'];
        $data['semesters_count'] = (int) $data['semesters_count'];
        $data['note'] ??= null;

        if ($data['qualification_id'] !== null && (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM qualifications WHERE id = ? AND specialty_id = ?',
            [$data['qualification_id'], $data['specialty_id']]
        ) === 0) {
            throw new ValidationException(['qualification_id' => [__('groups.qualification_mismatch')]], $request->all());
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validatedItem(Request $request): array
    {
        $data = $this->validate($request, [
            'subject_id' => 'required|integer|exists:subjects,id',
            'module_code' => 'nullable|string|max:30',
            'semester' => 'required|integer|between:1,10',
            'hours_total' => 'required|integer|between:1,2000',
            'hours_theory' => 'nullable|integer|between:0,2000',
            'hours_practice' => 'nullable|integer|between:0,2000',
            'hours_lab' => 'nullable|integer|between:0,2000',
            'credits' => 'nullable|decimal:2|between:0,100',
            'control_form' => 'required|in:' . implode(',', self::CONTROL),
        ]);
        foreach (['subject_id', 'semester', 'hours_total', 'hours_theory', 'hours_practice', 'hours_lab'] as $key) {
            $data[$key] = (int) ($data[$key] ?? 0);
        }
        $data['module_code'] = ($data['module_code'] ?? '') !== '' ? $data['module_code'] : null;
        $data['credits'] = ($data['credits'] ?? null) !== null ? (string) $data['credits'] : null;

        return $data;
    }

    /** @return array<int, string> */
    private function subjectOptions(): array
    {
        $options = [];
        foreach (Database::fetchAll('SELECT id, code, name_ru, name_kk FROM subjects WHERE is_active = 1 ORDER BY code') as $row) {
            $options[(int) $row['id']] = $row['code'] . ' — ' . localized($row);
        }

        return $options;
    }

    private function withInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
    }

    /** Approved and archived plans are read-only. @return array<string, mixed> */
    private function findDraft(int $id): array
    {
        $plan = $this->find($id);
        if ($plan['status'] !== 'draft') {
            throw new HttpException(403, __('curricula.only_draft'));
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->plans->find($id) ?? throw new HttpException(404);
    }
}
