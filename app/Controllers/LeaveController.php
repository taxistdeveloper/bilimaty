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
use App\Repositories\EmployeeRepository;
use App\Services\AccessScope;
use App\Services\HrService;

/**
 * Leaves, sick leaves and business trips.
 *  - /my/leaves  — an employee (leave.request) sees the balance and history, requests a leave, cancels a request;
 *  - /hr/leaves  — HR (hr.view / hr.manage) sees absences, decides requests, records absences from documents.
 */
final class LeaveController extends Controller
{
    private EmployeeRepository $employees;
    private HrService $hr;

    public function __construct()
    {
        $this->employees = new EmployeeRepository();
        $this->hr = new HrService($this->employees);
    }

    // ---- self-service --------------------------------------------------------

    public function my(): Response
    {
        $employee = $this->employees->findByUser((int) Auth::id());
        $year = (int) date('Y');

        return $this->view('hr/my_leaves', [
            'title' => __('hr.my_leaves'),
            'employee' => $employee,
            'leaves' => $employee !== null ? $this->employees->leaves([], null, (int) $employee['id']) : [],
            'balance' => $employee !== null ? $this->hr->balance($employee, $year) : null,
            'year' => $year,
        ]);
    }

    public function request(Request $request): Response
    {
        $employee = $this->employees->findByUser((int) Auth::id()) ?? throw new ValidationException(['type' => [__('hr.no_file')]]);
        $this->keepInput($request, fn () => $this->hr->addLeave($employee, $request->all(), false));
        Session::flash('success', __('hr.leave_requested'));

        return $this->redirect('/my/leaves');
    }

    public function cancelOwn(int $id): Response
    {
        $leave = $this->employees->leave($id) ?? throw new HttpException(404);
        if ((int) $leave['user_id'] !== Auth::id()) {
            throw new HttpException(403);
        }
        $this->hr->cancelLeave($leave, false);
        Session::flash('success', __('hr.leave_cancelled'));

        return $this->redirect('/my/leaves');
    }

    // ---- HR ------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month', '')) ? (string) $request->query('month') : date('Y-m');
        $status = in_array($request->query('status'), ['requested', 'approved', 'rejected', 'cancelled'], true) ? (string) $request->query('status') : '';
        $type = in_array($request->query('type'), HrService::LEAVE_TYPES, true) ? (string) $request->query('type') : '';
        // Pending requests are listed whatever their dates; other lists show the chosen month.
        $filters = ['status' => $status, 'type' => $type] + ($status === 'requested' ? [] : ['from' => $month . '-01', 'to' => date('Y-m-t', strtotime($month . '-01'))]);

        return $this->view('hr/leaves', [
            'title' => __('hr.leaves'),
            'leaves' => $this->employees->leaves($filters, AccessScope::departmentIds()),
            'month' => $month,
            'status' => $status,
            'type' => $type,
            'canManage' => Auth::can('hr.manage'),
        ]);
    }

    public function record(Request $request, int $id): Response
    {
        $this->authorize('hr.manage');
        $employee = $this->employees->find($id) ?? throw new HttpException(404);
        if (!AccessScope::allowsDepartment($employee['department_id'] !== null ? (int) $employee['department_id'] : null)) {
            throw new HttpException(403);
        }
        $this->keepInput($request, fn () => $this->hr->addLeave($employee, $request->all(), true));
        Session::flash('success', __('hr.leave_recorded'));

        return $this->redirect('/hr/employees/' . $id . '?tab=leaves');
    }

    public function approve(Request $request, int $id): Response
    {
        return $this->decide($request, $id, true);
    }

    public function reject(Request $request, int $id): Response
    {
        return $this->decide($request, $id, false);
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->authorize('hr.manage');
        $this->hr->cancelLeave($this->find($id), true);
        Session::flash('success', __('hr.leave_cancelled'));

        return $this->back($request, '/hr/leaves');
    }

    private function decide(Request $request, int $id, bool $approve): Response
    {
        $this->authorize('hr.manage');
        $comment = mb_substr(trim((string) $request->input('comment', '')), 0, 500);
        if (!$approve && $comment === '') {
            throw new ValidationException(['comment' => [__('validation.required', ['field' => __('fields.comment')])]]);
        }
        $this->hr->decideLeave($this->find($id), $approve, $comment !== '' ? $comment : null);
        Session::flash('success', __($approve ? 'hr.leave_approved' : 'hr.leave_rejected'));

        return $this->back($request, '/hr/leaves');
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $leave = $this->employees->leave($id) ?? throw new HttpException(404);
        if (!AccessScope::allowsDepartment($leave['department_id'] !== null ? (int) $leave['department_id'] : null)) {
            throw new HttpException(403);
        }

        return $leave;
    }

    /** Service-level validation errors keep what the user typed. */
    private function keepInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $e->oldInput !== [] ? $e->oldInput : $request->all());
        }
    }
}
