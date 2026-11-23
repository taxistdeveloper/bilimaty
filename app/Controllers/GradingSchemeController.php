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
use App\Repositories\GradingSchemeRepository;
use App\Services\AuditLogger;

/**
 * Grading schemes (grading.manage). A draft is edited freely; activation requires
 * at least one component and weights summing to exactly 100 %; an active scheme is
 * immutable — changed rules are made as a new version (copy) so already fixed finals
 * keep the version they were calculated with.
 */
final class GradingSchemeController extends Controller
{
    private GradingSchemeRepository $schemes;

    public function __construct()
    {
        $this->schemes = new GradingSchemeRepository();
    }

    public function index(): Response
    {
        return $this->view('grading_schemes/index', ['title' => __('grading.title'), 'schemes' => $this->schemes->all()]);
    }

    public function create(): Response
    {
        return $this->form(null, ['final_decimals' => 0, 'components' => []]);
    }

    public function store(Request $request): Response
    {
        [$data, $weights] = $this->validated($request);
        $id = Database::transaction(function () use ($data, $weights): int {
            $id = $this->schemes->create($data);
            $this->schemes->replaceComponents($id, $weights);

            return $id;
        });
        AuditLogger::log('grading.created', 'grading_schemes', $id, null, $data + ['weights' => $weights]);
        Session::flash('success', __('grading.saved'));

        return $this->redirect('/grading-schemes/' . $id . '/edit');
    }

    public function edit(int $id): Response
    {
        $scheme = $this->find($id);

        return $this->form($scheme, $scheme);
    }

    public function update(Request $request, int $id): Response
    {
        $scheme = $this->find($id);
        if ($scheme['status'] !== 'draft') {
            throw new HttpException(403, __('grading.immutable'));
        }
        [$data, $weights] = $this->validated($request);
        Database::transaction(function () use ($id, $data, $weights): void {
            $this->schemes->update($id, $data);
            $this->schemes->replaceComponents($id, $weights);
        });
        AuditLogger::log('grading.updated', 'grading_schemes', $id, null, $data + ['weights' => $weights]);
        Session::flash('success', __('grading.saved'));

        return $this->redirect('/grading-schemes/' . $id . '/edit');
    }

    public function status(Request $request, int $id): Response
    {
        $scheme = $this->find($id);
        $action = (string) $request->input('action', '');
        try {
            match ($action) {
                'activate' => $this->activate($scheme),
                'archive' => $this->archive($scheme),
                'version' => $id = $this->newVersion($scheme),
                'delete' => $this->delete($scheme),
                default => throw new HttpException(400),
            };
        } catch (ValidationException $e) {
            Session::flash('error', (string) (array_values($e->errors)[0][0] ?? ''));

            return $this->redirect('/grading-schemes/' . $scheme['id'] . '/edit');
        }
        Session::flash('success', __('grading.status_' . $action));

        return $this->redirect($action === 'delete' ? '/grading-schemes' : '/grading-schemes/' . $id . '/edit');
    }

    /** @param array<string, mixed> $scheme */
    private function activate(array $scheme): void
    {
        if ($scheme['status'] !== 'draft') {
            throw new ValidationException(['status' => [__('grading.immutable')]]);
        }
        $total = array_sum(array_map(static fn (array $c): float => (float) $c['weight'], $scheme['components']));
        if ($scheme['components'] === [] || abs($total - 100.0) > 0.001) {
            throw new ValidationException(['status' => [__('grading.weights_must_be_100', ['sum' => rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.')])]]);
        }
        $this->schemes->setStatus((int) $scheme['id'], 'active', Auth::id());
        AuditLogger::log('grading.activated', 'grading_schemes', (int) $scheme['id']);
    }

    /** @param array<string, mixed> $scheme */
    private function archive(array $scheme): void
    {
        if ($scheme['status'] !== 'active') {
            throw new ValidationException(['status' => [__('grading.only_active_archive')]]);
        }
        $this->schemes->setStatus((int) $scheme['id'], 'archived', null);
        AuditLogger::log('grading.archived', 'grading_schemes', (int) $scheme['id']);
    }

    /** @param array<string, mixed> $scheme */
    private function newVersion(array $scheme): int
    {
        $weights = [];
        foreach ($scheme['components'] as $c) {
            $weights[(int) $c['assessment_type_id']] = (string) $c['weight'];
        }
        $id = Database::transaction(function () use ($scheme, $weights): int {
            $id = $this->schemes->create([
                'title' => $scheme['title'], 'version' => (int) $scheme['version'] + 1, 'parent_id' => (int) $scheme['id'],
                'grade_min' => $scheme['grade_min'], 'grade_max' => $scheme['grade_max'], 'final_decimals' => $scheme['final_decimals'],
                'pass_threshold' => $scheme['pass_threshold'], 'note' => $scheme['note'],
            ]);
            $this->schemes->replaceComponents($id, $weights);

            return $id;
        });
        AuditLogger::log('grading.versioned', 'grading_schemes', $id, ['from' => (int) $scheme['id']]);

        return $id;
    }

    /** @param array<string, mixed> $scheme */
    private function delete(array $scheme): void
    {
        if ($scheme['status'] !== 'draft' || (int) $scheme['journals_count'] > 0) {
            throw new ValidationException(['status' => [__('grading.cannot_delete')]]);
        }
        $this->schemes->delete((int) $scheme['id']);
        AuditLogger::log('grading.deleted', 'grading_schemes', (int) $scheme['id'], ['title' => $scheme['title']]);
    }

    /**
     * @param array<string, mixed>|null $scheme
     * @param array<string, mixed> $values
     */
    private function form(?array $scheme, array $values): Response
    {
        $weights = [];
        foreach ($values['components'] ?? [] as $c) {
            $weights[(int) $c['assessment_type_id']] = $c['weight'];
        }

        return $this->view('grading_schemes/form', [
            'title' => $scheme === null ? __('grading.create') : $scheme['title'] . ' v' . $scheme['version'],
            'scheme' => $scheme,
            'values' => $values,
            'weights' => $weights,
            'types' => $this->schemes->componentTypes(),
            'readonly' => $scheme !== null && $scheme['status'] !== 'draft',
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function validated(Request $request): array
    {
        $data = $this->validate($request, [
            'title' => 'required|string|max:255',
            'grade_min' => 'required|decimal:2|between:0,1000',
            'grade_max' => 'required|decimal:2|between:0,1000',
            'final_decimals' => 'required|integer|between:0,2',
            'pass_threshold' => 'nullable|decimal:2|between:0,1000',
            'note' => 'nullable|string|max:500',
        ]);
        $errors = [];
        if ((float) $data['grade_max'] <= (float) $data['grade_min']) {
            $errors['grade_max'][] = __('grading.scale_order');
        }
        if (($data['pass_threshold'] ?? null) !== null && ((float) $data['pass_threshold'] < (float) $data['grade_min'] || (float) $data['pass_threshold'] > (float) $data['grade_max'])) {
            $errors['pass_threshold'][] = __('grading.threshold_in_scale');
        }

        $typeIds = array_map(static fn (array $t): int => (int) $t['id'], $this->schemes->componentTypes());
        $weights = [];
        foreach ((array) $request->input('weights', []) as $typeId => $weight) {
            $weight = trim((string) $weight);
            if ($weight === '') {
                continue;
            }
            if (!in_array((int) $typeId, $typeIds, true) || !preg_match('/^\d{1,3}(\.\d{1,2})?$/', $weight) || (float) $weight <= 0 || (float) $weight > 100) {
                $errors['weights'][] = __('grading.weight_invalid');
                break;
            }
            $weights[(int) $typeId] = $weight;
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $request->all());
        }

        return [[
            'title' => (string) $data['title'],
            'grade_min' => (string) $data['grade_min'],
            'grade_max' => (string) $data['grade_max'],
            'final_decimals' => (int) $data['final_decimals'],
            'pass_threshold' => ($data['pass_threshold'] ?? null) !== null ? (string) $data['pass_threshold'] : null,
            'note' => $data['note'] ?? null,
        ], $weights];
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->schemes->find($id) ?? throw new HttpException(404);
    }
}
