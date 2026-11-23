<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\Csv;
use App\Repositories\EmployeeRepository;
use App\Repositories\OptionsRepository;
use App\Services\AccessScope;
use App\Services\HrService;

/**
 * Personnel department: overview, personnel files, personnel orders, professional development
 * and the qualification report. View: hr.view (a department head — their departments only);
 * changes: hr.manage. Personal data (IIN, birth date, address) is shown to hr.manage only.
 */
final class HrController extends Controller
{
    private EmployeeRepository $employees;
    private HrService $hr;

    public function __construct()
    {
        $this->employees = new EmployeeRepository();
        $this->hr = new HrService($this->employees);
    }

    public function index(): Response
    {
        $scope = AccessScope::departmentIds();
        $today = date('Y-m-d');
        $absent = $this->employees->leaves(['status' => 'approved', 'from' => $today, 'to' => $today], $scope);

        return $this->view('hr/index', [
            'title' => __('hr.title'),
            'total' => $this->employees->paginate(['status' => 'working'], $scope, 1, 1)['total'],
            'absent' => $absent,
            'requests' => $this->employees->leaves(['status' => 'requested'], $scope),
            'drafts' => Auth::can('hr.manage') ? $this->employees->orders(['status' => 'draft'], $scope) : [],
            'reminders' => $this->hr->reminders($scope),
            'withoutFile' => Auth::can('hr.manage') ? count($this->employees->teachersWithoutFile()) : 0,
        ]);
    }

    // ---- personnel files -----------------------------------------------------

    public function employees(Request $request): Response
    {
        $filters = $this->filters($request);
        $scope = AccessScope::departmentIds();
        if ($request->query('format') === 'csv') {
            return $this->exportEmployees($filters, $scope);
        }

        return $this->view('hr/employees', [
            'title' => __('hr.employees'),
            'filters' => $filters,
            'departments' => (new OptionsRepository())->departments(true),
            'result' => $this->employees->paginate($filters, $scope, max(1, (int) $request->query('page', 1))),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $tab = in_array($request->query('tab'), ['orders', 'leaves', 'trainings'], true) ? (string) $request->query('tab') : 'orders';
        $tab = ['leave' => 'leaves', 'training' => 'trainings', 'order' => 'orders'][old('_form')] ?? $tab;
        $employee = $this->findVisible($id);
        $year = (int) date('Y');

        return $this->view('hr/show', [
            'title' => full_name($employee),
            'employee' => $employee,
            'orders' => $this->employees->orders([], null, $id),
            'leaves' => $this->employees->leaves([], null, $id),
            'trainings' => $this->employees->trainings($id),
            'balance' => $this->hr->balance($employee, $year),
            'year' => $year,
            'experience' => HrService::experienceMonths($employee),
            'canManage' => $this->canManage(),
            'departments' => (new OptionsRepository())->departments(true, true),
            'tab' => $tab,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('hr.manage');
        $userId = ctype_digit((string) $request->query('user', '')) ? (int) $request->query('user') : null;
        $user = null;
        if ($userId !== null) {
            $user = array_values(array_filter($this->employees->usersWithoutFile(), static fn (array $u): bool => (int) $u['id'] === $userId))[0] ?? throw new HttpException(404);
        }

        return $this->form(null, $user);
    }

    public function store(Request $request): Response
    {
        $this->authorize('hr.manage');
        $userId = ctype_digit((string) $request->input('user_id', '')) ? (int) $request->input('user_id') : null;
        if ($userId !== null && !in_array($userId, array_map('intval', array_column($this->employees->usersWithoutFile(), 'id')), true)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, HrService::rules(null, $userId === null));
        $id = $this->hr->create($data, $userId);
        Session::flash('success', __('hr.employee_created'));

        return $this->redirect('/hr/employees/' . $id);
    }

    public function edit(int $id): Response
    {
        $this->authorize('hr.manage');

        return $this->form($this->findVisible($id), null);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize('hr.manage');
        $employee = $this->findVisible($id);
        $this->hr->update($employee, $this->validate($request, HrService::rules($employee)));
        Session::flash('success', __('hr.employee_updated'));

        return $this->redirect('/hr/employees/' . $id);
    }

    public function registerTeachers(): Response
    {
        $this->authorize('hr.manage');
        $count = $this->hr->registerTeachers();
        Session::flash('success', __('hr.teachers_registered', ['count' => $count]));

        return $this->redirect('/hr/employees');
    }

    /** @param array<string, mixed>|null $employee */
    private function form(?array $employee, ?array $user): Response
    {
        return $this->view('hr/form', [
            'title' => $employee === null ? __('hr.employee_create') : __('hr.employee_edit'),
            'employee' => $employee,
            'user' => $user,
            'users' => $employee === null && $user === null ? $this->employees->usersWithoutFile() : [],
            'nextNumber' => $this->employees->nextPersonnelNumber(),
            'departments' => (new OptionsRepository())->departments(true, true),
        ]);
    }

    // ---- orders --------------------------------------------------------------

    public function orders(Request $request): Response
    {
        $filters = [
            'type' => in_array($request->query('type'), HrService::ORDER_TYPES, true) ? (string) $request->query('type') : '',
            'status' => in_array($request->query('status'), ['draft', 'signed', 'cancelled'], true) ? (string) $request->query('status') : '',
            'year' => ctype_digit((string) $request->query('year', '')) ? (int) $request->query('year') : (int) date('Y'),
        ];

        return $this->view('hr/orders', [
            'title' => __('hr.orders'),
            'filters' => $filters,
            'orders' => $this->employees->orders($filters, AccessScope::departmentIds()),
        ]);
    }

    public function storeOrder(Request $request, int $id): Response
    {
        $this->authorize('hr.manage');
        $employee = $this->findVisible($id);
        $type = in_array($request->input('type'), HrService::ORDER_TYPES, true) ? (string) $request->input('type') : 'other';
        $orderId = $this->keepInput($request, fn () => $this->hr->createOrder($employee, $this->validate($request, HrService::orderRules($type))));
        Session::flash('success', __('hr.order_created'));

        return $this->redirect('/hr/orders/' . $orderId);
    }

    public function order(Request $request, int $id): Response
    {
        $order = $this->findOrder($id);
        $employee = $this->employees->find((int) $order['employee_id']) ?? throw new HttpException(404);

        return $this->view('hr/order', [
            'title' => $order['number'] !== null ? __('hr.order_title', ['number' => $order['number']]) : __('hr.order_draft_title'),
            'order' => $order,
            'details' => (array) json_decode((string) $order['details'], true),
            'employee' => $employee,
            'departments' => (new OptionsRepository())->departments(),
            'canManage' => $this->canManage(),
            'print' => $request->query('format') === 'print',
        ], $request->query('format') === 'print' ? 'layouts/print' : 'layouts/app');
    }

    public function signOrder(int $id): Response
    {
        $this->authorize('hr.manage');
        $number = $this->hr->signOrder($this->findOrder($id));
        Session::flash('success', __('hr.order_signed', ['number' => $number]));

        return $this->redirect('/hr/orders/' . $id);
    }

    public function cancelOrder(int $id): Response
    {
        $this->authorize('hr.manage');
        $this->hr->cancelOrder($this->findOrder($id));
        Session::flash('success', __('hr.order_cancelled'));

        return $this->redirect('/hr/orders/' . $id);
    }

    // ---- qualification -------------------------------------------------------

    public function storeTraining(Request $request, int $id): Response
    {
        $this->authorize('hr.manage');
        $employee = $this->findVisible($id);
        $this->keepInput($request, fn () => $this->hr->addTraining($employee, $request->all()));
        Session::flash('success', __('hr.training_added'));

        return $this->redirect('/hr/employees/' . $id . '?tab=trainings');
    }

    public function deleteTraining(int $id): Response
    {
        $this->authorize('hr.manage');
        $training = $this->employees->training($id) ?? throw new HttpException(404);
        $this->findVisible((int) $training['employee_id']);
        $this->hr->deleteTraining($training);
        Session::flash('success', __('hr.training_deleted'));

        return $this->redirect('/hr/employees/' . $training['employee_id'] . '?tab=trainings');
    }

    /** Qualification report: categories, their expiry, last professional development; CSV and print. */
    public function qualification(Request $request): Response
    {
        $filters = $this->filters($request) + ['category' => 'teaching'];
        $filters['category'] = 'teaching';
        $rows = $this->employees->all($filters, AccessScope::departmentIds());
        $reminders = $this->hr->reminders(AccessScope::departmentIds());
        $format = (string) $request->query('format', '');
        if ($format === 'csv') {
            $out = [[__('hr.qualification'), format_date(date('Y-m-d'))],
                ['№', __('users.name'), __('fields.personnel_number'), __('fields.department_id'), __('fields.position'), __('fields.qualification_category'),
                    __('fields.category_assigned_on'), __('fields.category_valid_until'), __('hr.last_training')]];
            foreach ($rows as $i => $r) {
                $out[] = [$i + 1, full_name($r), $r['personnel_number'], $this->department($r), $r['position'], $r['qualification_category'] ? __('hr.categories.' . $r['qualification_category']) : '',
                    format_date($r['category_assigned_on']), format_date($r['category_valid_until']), format_date($r['last_training'])];
            }

            return Csv::download($out, 'qualification-' . date('Ymd') . '.csv');
        }

        return $this->view('hr/qualification', [
            'title' => __('hr.qualification'),
            'rows' => $rows,
            'filters' => $filters,
            'departments' => (new OptionsRepository())->departments(true),
            'expiring' => array_column($reminders['categories'], null, 'id'),
            'due' => array_column($reminders['trainings'], null, 'id'),
            'print' => $format === 'print',
        ], $format === 'print' ? 'layouts/print' : 'layouts/app');
    }

    // ---- helpers -------------------------------------------------------------

    /** @return array{q: string, department: ?int, category: string, status: string} */
    private function filters(Request $request): array
    {
        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'department' => ctype_digit((string) $request->query('department', '')) ? (int) $request->query('department') : null,
            'category' => in_array($request->query('category'), HrService::CATEGORIES, true) ? (string) $request->query('category') : '',
            'status' => in_array($request->query('status'), ['working', 'active', 'dismissed', 'all'], true) ? (string) $request->query('status') : 'working',
        ];
    }

    private function exportEmployees(array $filters, ?array $scope): Response
    {
        $full = Auth::can('hr.manage');
        $header = ['№', __('fields.personnel_number'), __('users.name'), __('fields.department_id'), __('fields.position'), __('fields.staff_category'),
            __('fields.employment_type'), __('fields.rate'), __('fields.hire_date'), __('hr.experience'), __('common.status')];
        if ($full) {
            array_push($header, __('fields.iin'), __('fields.birth_date'), __('fields.phone'));
        }
        $rows = [[__('hr.employees'), format_date(date('Y-m-d'))], $header];
        foreach ($this->employees->all($filters, $scope) as $i => $r) {
            $row = [$i + 1, $r['personnel_number'], full_name($r), $this->department($r), $r['position'], __('hr.staff_categories.' . $r['staff_category']),
                __('hr.employment_types.' . $r['employment_type']), $r['rate'], format_date($r['hire_date']), self::years(HrService::experienceMonths($r)), __('hr.statuses.' . $r['status'])];
            if ($full) {
                array_push($row, $r['iin'], format_date($r['birth_date']), $r['phone']);
            }
            $rows[] = $row;
        }

        return Csv::download($rows, 'employees-' . date('Ymd') . '.csv');
    }

    public static function years(int $months): string
    {
        return __('hr.experience_value', ['y' => intdiv($months, 12), 'm' => $months % 12]);
    }

    private function department(array $row): string
    {
        return $row['department_name_ru'] !== null ? localized(['name_ru' => $row['department_name_ru'], 'name_kk' => $row['department_name_kk']]) : '';
    }

    /** @return array<string, mixed> */
    private function findVisible(int $id): array
    {
        $employee = $this->employees->find($id) ?? throw new HttpException(404);
        if (!AccessScope::allowsDepartment($employee['department_id'] !== null ? (int) $employee['department_id'] : null)) {
            throw new HttpException(403);
        }

        return $employee;
    }

    /** @return array<string, mixed> */
    private function findOrder(int $id): array
    {
        $order = $this->employees->order($id) ?? throw new HttpException(404);
        $this->findVisible((int) $order['employee_id']);

        return $order;
    }

    private function canManage(): bool
    {
        return Auth::can('hr.manage');
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return (array) Config::get('hr.categories', []);
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
