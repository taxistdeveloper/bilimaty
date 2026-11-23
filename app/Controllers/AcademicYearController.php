<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\AcademicYearRepository;
use App\Services\AcademicYearService;

/** Academic years and semesters. Route access: academic_years.manage. */
final class AcademicYearController extends Controller
{
    private AcademicYearRepository $years;

    public function __construct()
    {
        $this->years = new AcademicYearRepository();
    }

    public function index(): Response
    {
        return $this->view('academic_years/index', [
            'title' => __('years.title'),
            'years' => $this->years->allWithStats(),
        ]);
    }

    public function create(): Response
    {
        // Suggest the year after the latest one: 1 Sep – 30 Jun.
        $all = $this->years->allWithStats();
        $startYear = $all !== [] ? (int) substr((string) end($all)['start_date'], 0, 4) + 1 : (int) date('Y');

        return $this->view('academic_years/form', [
            'title' => __('years.create'),
            'year' => null,
            'values' => [
                'start_date' => "{$startYear}-09-01", 'end_date' => ($startYear + 1) . '-06-30',
                's1_start' => "{$startYear}-09-01", 's1_end' => "{$startYear}-12-31",
                's2_start' => ($startYear + 1) . '-01-12', 's2_end' => ($startYear + 1) . '-06-30',
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->withInput($request, fn (): int => (new AcademicYearService())->save(null, $this->validated($request)));
        Session::flash('success', __('years.created'));

        return $this->redirect('/academic-years#year-' . $id);
    }

    public function edit(int $id): Response
    {
        $year = $this->find($id);
        if ($year['status'] === 'archived') {
            throw new HttpException(403, __('years.archived_readonly'));
        }
        $s = [];
        foreach ($year['semesters'] as $semester) {
            $s[(int) $semester['number']] = $semester;
        }

        return $this->view('academic_years/form', [
            'title' => __('years.edit') . ' ' . str_replace('-', '–', (string) $year['title']),
            'year' => $year,
            'values' => [
                'start_date' => $year['start_date'], 'end_date' => $year['end_date'],
                's1_start' => $s[1]['start_date'] ?? '', 's1_end' => $s[1]['end_date'] ?? '',
                's2_start' => $s[2]['start_date'] ?? '', 's2_end' => $s[2]['end_date'] ?? '',
            ],
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $year = $this->find($id);
        $this->withInput($request, fn (): int => (new AcademicYearService())->save($year, $this->validated($request)));
        Session::flash('success', __('years.updated'));

        return $this->redirect('/academic-years#year-' . $id);
    }

    public function activate(int $id): Response
    {
        (new AcademicYearService())->activate($this->find($id));
        Session::flash('success', __('years.activated'));

        return $this->redirect('/academic-years');
    }

    public function archive(int $id): Response
    {
        return $this->statusAction($id, 'archive', 'years.archived');
    }

    public function restore(int $id): Response
    {
        return $this->statusAction($id, 'restore', 'years.restored');
    }

    public function destroy(int $id): Response
    {
        return $this->statusAction($id, 'delete', 'years.deleted');
    }

    private function statusAction(int $id, string $method, string $message): Response
    {
        try {
            (new AcademicYearService())->{$method}($this->find($id));
            Session::flash('success', __($message));
        } catch (ValidationException $e) {
            Session::flash('error', (string) (array_values($e->errors)[0][0] ?? ''));
        }

        return $this->redirect('/academic-years');
    }

    /** @return array<string, string> */
    private function validated(Request $request): array
    {
        $data = $this->validate($request, [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            's1_start' => 'required|date',
            's1_end' => 'required|date',
            's2_start' => 'required|date',
            's2_end' => 'required|date',
        ]);

        return array_map('strval', $data);
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
        return $this->years->find($id) ?? throw new HttpException(404);
    }
}
