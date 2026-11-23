<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Repositories\EmployeeRepository;
use App\Repositories\UserRepository;

/**
 * Personnel department: personnel files, personnel orders, leaves/sick leaves/business trips,
 * qualification categories and professional development.
 *
 * Orders:  draft ──sign──▶ signed (number К-YYYY-NNNNN, the change is applied to the file)
 *            └──cancel──▶ cancelled.  A signed order is never edited: a new order corrects it.
 * Dismissal closes the account on the dismissal date (at once when the date has come,
 * otherwise by `php cli.php hr:daily`). Job data of a teacher is copied to the teacher profile.
 *
 * Leaves:  requested ──approve──▶ approved      (HR may also record an absence as approved at once,
 *              ├──reject──▶ rejected             e.g. a sick-leave sheet)
 *              └──cancel──▶ cancelled (by the employee while requested, by HR any time)
 */
final class HrService
{
    public const CATEGORIES = ['teaching', 'administrative', 'support'];
    public const EMPLOYMENT = ['main', 'internal_part_time', 'external_part_time'];
    public const ORDER_TYPES = ['hire', 'transfer', 'rate_change', 'dismissal', 'other'];
    public const LEAVE_TYPES = ['annual', 'unpaid', 'study', 'maternity', 'sick', 'business_trip', 'other'];
    /** Types an employee may request themselves; the rest are recorded by HR from documents. */
    public const SELF_LEAVE_TYPES = ['annual', 'unpaid', 'study', 'other'];

    private const PERSON = ['last_name', 'first_name', 'middle_name', 'phone', 'email'];
    private const FILE = ['personnel_number', 'iin', 'birth_date', 'gender', 'address', 'education', 'department_id', 'position', 'staff_category', 'employment_type',
        'rate', 'hire_date', 'prior_experience_months', 'annual_leave_days', 'qualification_category', 'category_assigned_on', 'category_valid_until', 'note'];

    public function __construct(
        private readonly EmployeeRepository $repository = new EmployeeRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    // ---- personnel files -----------------------------------------------------

    /**
     * @param array<string, mixed>|null $employee existing file (uniqueness ignores it)
     * @return array<string, string>
     */
    public static function rules(?array $employee = null, bool $withPerson = true): array
    {
        $id = $employee !== null ? ',' . $employee['id'] : '';
        $rules = [
            'personnel_number' => 'required|alpha_dash|max:30|unique:employees,personnel_number' . $id,
            'iin' => 'nullable|iin|unique:employees,iin' . $id,
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string|max:255',
            'education' => 'nullable|string|max:500',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position' => 'nullable|string|max:150',
            'staff_category' => 'required|in:' . implode(',', self::CATEGORIES),
            'employment_type' => 'required|in:' . implode(',', self::EMPLOYMENT),
            'rate' => 'required|decimal:2|between:0.1,2',
            'hire_date' => 'nullable|date',
            'prior_experience_months' => 'nullable|integer|min:0|max:900',
            'annual_leave_days' => 'nullable|integer|between:1,90',
            'qualification_category' => 'nullable|in:' . implode(',', (array) Config::get('hr.categories', [])),
            'category_assigned_on' => 'nullable|date',
            'category_valid_until' => 'nullable|date|after:category_assigned_on',
            'note' => 'nullable|string|max:500',
        ];
        if ($withPerson) {
            $userIgnore = $employee !== null ? ',' . $employee['user_id'] : '';
            $rules = [
                'last_name' => 'required|string|max:100',
                'first_name' => 'required|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'phone' => 'nullable|phone|max:20',
                'email' => 'nullable|email|max:190|unique:users,email' . $userIgnore,
            ] + $rules;
        }

        return $rules;
    }

    /**
     * Open a personnel file: for an existing account ($userId) or for a new person, who gets an
     * inactive account without roles (no system access until an administrator grants it).
     *
     * @param array<string, mixed> $data validated
     */
    public function create(array $data, ?int $userId): int
    {
        if ($userId !== null) {
            if ($this->users->find($userId) === null || $this->repository->findByUser($userId) !== null) {
                throw new ValidationException(['user_id' => [__('hr.user_has_file')]]);
            }
        }
        $id = Database::transaction(function () use ($data, $userId): int {
            if ($userId === null) {
                $userId = $this->users->create([
                    'login' => LoginGenerator::generate((string) $data['last_name'], (string) $data['first_name'], $data['middle_name'] ?? null),
                    'email' => $data['email'] ?? null,
                    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    'last_name' => $data['last_name'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'locale' => 'kk',
                    'is_active' => 0,
                ]);
            } else {
                $this->updatePerson($userId, $data);
            }
            $file = $this->file($data) + ['user_id' => $userId, 'status' => 'active', 'created_by' => Auth::id()];
            $id = $this->repository->create($file);
            $this->syncTeacher($userId, $file);

            return $id;
        });
        AuditLogger::log('hr.employee_created', 'employees', $id, null, $this->file($data) + ['user_id' => $userId]);

        return $id;
    }

    /**
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $data validated
     */
    public function update(array $employee, array $data): void
    {
        $file = $this->file($data);
        Database::transaction(function () use ($employee, $data, $file): void {
            $this->updatePerson((int) $employee['user_id'], $data);
            $this->repository->update((int) $employee['id'], $file);
            $this->syncTeacher((int) $employee['user_id'], $file + $employee);
        });
        $changed = array_keys(array_diff_assoc(array_map('strval', $file), array_map('strval', array_intersect_key($employee, $file))));
        if ($changed !== []) {
            $keys = array_flip($changed);
            AuditLogger::log('hr.employee_updated', 'employees', (int) $employee['id'], array_intersect_key($employee, $keys), array_intersect_key($file, $keys));
        }
    }

    /** Open personnel files for every teacher that has none yet (data is taken from the teacher profile). */
    public function registerTeachers(): int
    {
        $count = 0;
        foreach ($this->repository->teachersWithoutFile() as $teacher) {
            $number = (string) ($teacher['employee_number'] ?? '');
            if ($number === '' || Database::fetchColumn('SELECT 1 FROM employees WHERE personnel_number = ?', [$number]) !== null) {
                $number = $this->repository->nextPersonnelNumber();
            }
            $id = $this->repository->create([
                'user_id' => (int) $teacher['user_id'], 'personnel_number' => $number, 'department_id' => $teacher['department_id'],
                'position' => $teacher['position'], 'hire_date' => $teacher['hire_date'], 'staff_category' => 'teaching',
                'employment_type' => 'main', 'rate' => '1.00', 'status' => $teacher['status'], 'created_by' => Auth::id(),
            ]);
            AuditLogger::log('hr.employee_created', 'employees', $id, null, ['user_id' => (int) $teacher['user_id'], 'personnel_number' => $number, 'source' => 'teachers']);
            $count++;
        }

        return $count;
    }

    /** Total work experience in months: before the college + here (until dismissal or $on). */
    public static function experienceMonths(array $employee, ?string $on = null): int
    {
        $months = (int) ($employee['prior_experience_months'] ?? 0);
        if (!empty($employee['hire_date'])) {
            $end = new \DateTimeImmutable((string) ($employee['dismissal_date'] ?? null ?: ($on ?? date('Y-m-d'))));
            $start = new \DateTimeImmutable((string) $employee['hire_date']);
            if ($end > $start) {
                $diff = $start->diff($end);
                $months += $diff->y * 12 + $diff->m;
            }
        }

        return $months;
    }

    // ---- orders --------------------------------------------------------------

    /** @return array<string, string> rules of the order form; details depend on the type */
    public static function orderRules(string $type): array
    {
        $rules = [
            'type' => 'required|in:' . implode(',', self::ORDER_TYPES),
            'order_date' => 'required|date',
            'effective_date' => 'required|date',
            'basis' => 'nullable|string|max:500',
        ];

        return $rules + match ($type) {
            'hire' => ['position' => 'required|string|max:150', 'department_id' => 'nullable|integer|exists:departments,id', 'rate' => 'required|decimal:2|between:0.1,2',
                'staff_category' => 'required|in:' . implode(',', self::CATEGORIES), 'employment_type' => 'required|in:' . implode(',', self::EMPLOYMENT)],
            'transfer' => ['position' => 'required|string|max:150', 'department_id' => 'nullable|integer|exists:departments,id'],
            'rate_change' => ['rate' => 'required|decimal:2|between:0.1,2'],
            'dismissal' => ['reason' => 'required|string|max:255'],
            default => ['text' => 'required|string|max:1000'],
        };
    }

    /**
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $data validated (orderRules)
     */
    public function createOrder(array $employee, array $data): int
    {
        $type = (string) $data['type'];
        $errors = [];
        if ($employee['status'] === 'dismissed' && $type !== 'hire') {
            $errors['type'][] = __('hr.order_dismissed_only_hire');
        }
        if ($employee['status'] !== 'dismissed' && $type === 'hire' && $this->hasSignedHire((int) $employee['id'])) {
            $errors['type'][] = __('hr.order_already_hired');
        }
        if ($data['effective_date'] < $data['order_date'] && $type !== 'other') {
            // Orders take effect on or after they are issued (a past date needs an explanatory "other" order).
            $errors['effective_date'][] = __('validation.after', ['field' => __('fields.effective_date'), 'other' => __('fields.order_date')]);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $details = array_intersect_key($data, array_flip(['position', 'department_id', 'rate', 'staff_category', 'employment_type', 'reason', 'text']));
        $id = $this->repository->createOrder([
            'employee_id' => (int) $employee['id'], 'type' => $type, 'order_date' => $data['order_date'], 'effective_date' => $data['effective_date'],
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'basis' => $data['basis'] ?? null, 'created_by' => Auth::id(),
        ]);
        AuditLogger::log('hr.order_created', 'hr_orders', $id, null, ['employee_id' => (int) $employee['id'], 'type' => $type] + $details);

        return $id;
    }

    /** Sign a draft: give it a number and apply it to the personnel file. */
    public function signOrder(array $order): string
    {
        if ($order['status'] !== 'draft') {
            throw new ValidationException(['status' => [__('hr.order_not_draft')]]);
        }
        $number = Database::transaction(function () use ($order): string {
            $year = substr((string) $order['order_date'], 0, 4);
            $number = $this->repository->nextOrderNumber(Config::get('hr.order_prefix', 'К') . '-' . $year . '-');
            $this->repository->setOrderStatus((int) $order['id'], 'signed', $number, Auth::id());
            $this->apply($order);

            return $number;
        });
        AuditLogger::log('hr.order_signed', 'hr_orders', (int) $order['id'], ['status' => 'draft'], ['status' => 'signed', 'number' => $number]);

        return $number;
    }

    public function cancelOrder(array $order): void
    {
        if ($order['status'] !== 'draft') {
            throw new ValidationException(['status' => [__('hr.order_not_draft')]]);
        }
        $this->repository->setOrderStatus((int) $order['id'], 'cancelled', null, null);
        AuditLogger::log('hr.order_cancelled', 'hr_orders', (int) $order['id'], ['status' => 'draft'], ['status' => 'cancelled']);
    }

    /** Apply a signed order to the personnel file (inside the signing transaction). */
    private function apply(array $order): void
    {
        $employee = $this->repository->find((int) $order['employee_id']) ?? [];
        $d = (array) json_decode((string) $order['details'], true);
        $date = (string) $order['effective_date'];
        $change = match ($order['type']) {
            'hire' => ['position' => $d['position'], 'department_id' => $d['department_id'] ?? null, 'rate' => $d['rate'], 'staff_category' => $d['staff_category'],
                'employment_type' => $d['employment_type'], 'hire_date' => $date, 'dismissal_date' => null, 'status' => 'active'],
            'transfer' => ['position' => $d['position'], 'department_id' => $d['department_id'] ?? null],
            'rate_change' => ['rate' => $d['rate']],
            'dismissal' => ['dismissal_date' => $date, 'status' => 'dismissed'],
            default => [],
        };
        if ($change === []) {
            return;
        }
        if (array_key_exists('department_id', $change)) {
            $change['department_id'] = $change['department_id'] !== null && $change['department_id'] !== '' ? (int) $change['department_id'] : null;
        }
        $this->repository->update((int) $employee['id'], $change);
        $this->syncTeacher((int) $employee['user_id'], $change + $employee);
        $userId = (int) $employee['user_id'];
        if ($order['type'] === 'dismissal' && $date <= date('Y-m-d')) {
            $this->users->setActive($userId, false);
        } elseif ($order['type'] === 'hire' && $employee['status'] === 'dismissed' && $this->users->roleIds($userId) !== []) {
            $this->users->setActive($userId, true);
        }
    }

    /** Close accounts of employees whose dismissal date has come. @return int closed accounts */
    public function applyDueDismissals(?string $today = null): int
    {
        $count = 0;
        foreach ($this->repository->dueDismissals($today ?? date('Y-m-d')) as $row) {
            $this->users->setActive((int) $row['user_id'], false);
            AuditLogger::log('hr.account_closed', 'employees', (int) $row['id'], ['is_active' => 1], ['is_active' => 0]);
            $count++;
        }

        return $count;
    }

    private function hasSignedHire(int $employeeId): bool
    {
        return Database::fetchColumn("SELECT 1 FROM hr_orders WHERE employee_id = ? AND type = 'hire' AND status = 'signed' LIMIT 1", [$employeeId]) !== null;
    }

    // ---- leaves --------------------------------------------------------------

    public static function entitlement(array $employee): int
    {
        return (int) ($employee['annual_leave_days'] ?? null ?: Config::get('hr.annual_leave_days.' . $employee['staff_category'], 24));
    }

    /** @return array{entitled: int, used: int, requested: int, left: int} annual leave of the year */
    public function balance(array $employee, int $year): array
    {
        $entitled = self::entitlement($employee);
        $used = $this->repository->annualDays((int) $employee['id'], $year, false);
        $all = $this->repository->annualDays((int) $employee['id'], $year, true);

        return ['entitled' => $entitled, 'used' => $used, 'requested' => $all - $used, 'left' => $entitled - $all];
    }

    /**
     * Request (self-service) or record (HR, approved at once) an absence.
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $input type, start_date, end_date, reason, document_number, destination
     */
    public function addLeave(array $employee, array $input, bool $byHr): int
    {
        $types = $byHr ? self::LEAVE_TYPES : self::SELF_LEAVE_TYPES;
        $v = Validator::make($input, [
            'type' => 'required|in:' . implode(',', $types),
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'document_number' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            throw new ValidationException($v->errors());
        }
        $data = $v->validated();
        $errors = [];
        if ($data['end_date'] < $data['start_date']) {
            $errors['end_date'][] = __('validation.after', ['field' => __('fields.end_date'), 'other' => __('fields.start_date')]);
        }
        if ($employee['status'] === 'dismissed') {
            $errors['type'][] = __('hr.employee_dismissed');
        }
        if (!$byHr && $data['start_date'] < date('Y-m-d')) {
            $errors['start_date'][] = __('hr.leave_in_past');
        }
        if ($data['type'] === 'business_trip' && ($data['destination'] ?? null) === null) {
            $errors['destination'][] = __('validation.required', ['field' => __('fields.destination')]);
        }
        if ($errors === [] && $this->repository->overlaps((int) $employee['id'], $data['start_date'], $data['end_date'])) {
            $errors['start_date'][] = __('hr.leave_overlaps');
        }
        if ($errors === [] && $data['type'] === 'annual') {
            $errors += $this->checkBalance($employee, $data['start_date'], $data['end_date']);
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $days = (int) (new \DateTimeImmutable($data['start_date']))->diff(new \DateTimeImmutable($data['end_date']))->days + 1;
        $id = $this->repository->createLeave([
            'employee_id' => (int) $employee['id'], 'type' => $data['type'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'days' => $days,
            'status' => $byHr ? 'approved' : 'requested', 'reason' => $data['reason'] ?? null, 'document_number' => $data['document_number'] ?? null,
            'destination' => $data['destination'] ?? null, 'requested_by' => Auth::id(), 'decided_by' => $byHr ? Auth::id() : null, 'decided_at' => $byHr ? date('Y-m-d H:i:s') : null,
        ]);
        AuditLogger::log($byHr ? 'hr.leave_recorded' : 'hr.leave_requested', 'hr_leaves', $id, null, $data + ['employee_id' => (int) $employee['id'], 'days' => $days]);
        if ($byHr) {
            if ((int) $employee['user_id'] !== Auth::id()) {
                Notifier::send([(int) $employee['user_id']], 'hr', 'hr.notify.recorded_title', 'hr.notify.recorded_body',
                    ['type' => __('hr.leave_types.' . $data['type']), 'from' => format_date($data['start_date']), 'to' => format_date($data['end_date'])], '/my/leaves');
            }
        } else {
            Notifier::send($this->managers((int) Auth::id()), 'hr', 'hr.notify.request_title', 'hr.notify.request_body',
                ['name' => full_name($employee), 'type' => __('hr.leave_types.' . $data['type']), 'from' => format_date($data['start_date']), 'to' => format_date($data['end_date'])], '/hr/leaves?status=requested');
        }

        return $id;
    }

    /** @return array<string, list<string>> errors when an annual leave exceeds the balance of a year it touches */
    private function checkBalance(array $employee, string $from, string $to): array
    {
        for ($year = (int) substr($from, 0, 4); $year <= (int) substr($to, 0, 4); $year++) {
            $start = max($from, $year . '-01-01');
            $end = min($to, $year . '-12-31');
            $days = (int) (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days + 1;
            $taken = $this->repository->annualDays((int) $employee['id'], $year, true);
            $left = self::entitlement($employee) - $taken;
            if ($days > $left) {
                return ['end_date' => [__('hr.leave_exceeds', ['year' => $year, 'left' => max(0, $left)])]];
            }
        }

        return [];
    }

    /** HR decision on a requested leave. */
    public function decideLeave(array $leave, bool $approve, ?string $comment): void
    {
        if ($leave['status'] !== 'requested') {
            throw new ValidationException(['status' => [__('hr.leave_not_requested')]]);
        }
        $status = $approve ? 'approved' : 'rejected';
        $this->repository->decideLeave((int) $leave['id'], $status, $comment, Auth::id());
        AuditLogger::log('hr.leave_' . $status, 'hr_leaves', (int) $leave['id'], ['status' => 'requested'], ['status' => $status, 'comment' => $comment]);
        Notifier::send([(int) $leave['user_id']], 'hr', 'hr.notify.decided_title', 'hr.notify.' . $status . '_body',
            ['type' => __('hr.leave_types.' . $leave['type']), 'from' => format_date($leave['start_date']), 'to' => format_date($leave['end_date']), 'comment' => (string) $comment], '/my/leaves');
    }

    /** Cancel: the employee — own request while it is requested; HR — any requested or approved one. */
    public function cancelLeave(array $leave, bool $byHr): void
    {
        $allowed = $byHr ? ['requested', 'approved'] : ['requested'];
        if (!in_array($leave['status'], $allowed, true)) {
            throw new ValidationException(['status' => [__('hr.leave_not_cancellable')]]);
        }
        $this->repository->decideLeave((int) $leave['id'], 'cancelled', null, Auth::id());
        AuditLogger::log('hr.leave_cancelled', 'hr_leaves', (int) $leave['id'], ['status' => $leave['status']], ['status' => 'cancelled']);
        if ($byHr && (int) $leave['user_id'] !== Auth::id()) {
            Notifier::send([(int) $leave['user_id']], 'hr', 'hr.notify.decided_title', 'hr.notify.cancelled_body',
                ['type' => __('hr.leave_types.' . $leave['type']), 'from' => format_date($leave['start_date']), 'to' => format_date($leave['end_date']), 'comment' => ''], '/my/leaves');
        }
    }

    /**
     * Teachers absent on a date (approved leave, sick leave or trip): for substitutions in the timetable.
     *
     * @return array<int, array<string, mixed>> teacher id => leave row
     */
    public function absentTeachers(string $from, ?string $to = null): array
    {
        $out = [];
        foreach ($this->repository->leaves(['status' => 'approved', 'from' => $from, 'to' => $to ?? $from], null) as $leave) {
            if ($leave['teacher_id'] !== null) {
                $out[(int) $leave['teacher_id']] ??= $leave;
            }
        }

        return $out;
    }

    /** @return list<int> users who process personnel matters (hr.manage), except $exceptUser */
    private function managers(int $exceptUser): array
    {
        return array_values(array_diff(array_map('intval', array_column(Database::fetchAll(
            "SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id
             JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id AND p.slug = 'hr.manage'
             WHERE u.is_active = 1 AND u.deleted_at IS NULL"
        ), 'id')), [$exceptUser]));
    }

    // ---- qualification -------------------------------------------------------

    /** @param array<string, mixed> $input */
    public function addTraining(array $employee, array $input): int
    {
        $v = Validator::make($input, [
            'title' => 'required|string|max:255',
            'provider' => 'nullable|string|max:255',
            'hours' => 'required|integer|between:1,2000',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'certificate_number' => 'nullable|string|max:100',
        ]);
        $errors = $v->errors();
        $data = $v->validated();
        if (!isset($errors['end_date']) && isset($data['start_date'], $data['end_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'][] = __('validation.after', ['field' => __('fields.end_date'), 'other' => __('fields.start_date')]);
        }
        if (!isset($errors['end_date']) && isset($data['end_date']) && $data['end_date'] > date('Y-m-d')) {
            $errors['end_date'][] = __('hr.training_future');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $id = $this->repository->addTraining($data + ['employee_id' => (int) $employee['id'], 'provider' => null, 'certificate_number' => null, 'created_by' => Auth::id()]);
        AuditLogger::log('hr.training_added', 'hr_trainings', $id, null, $data + ['employee_id' => (int) $employee['id']]);

        return $id;
    }

    public function deleteTraining(array $training): void
    {
        $this->repository->deleteTraining((int) $training['id']);
        AuditLogger::log('hr.training_deleted', 'hr_trainings', (int) $training['id'], $training, null);
    }

    /**
     * Reminders: categories expiring soon (or expired) and teaching staff without recent professional development.
     *
     * @return array{categories: list<array<string, mixed>>, trainings: list<array<string, mixed>>}
     */
    public function reminders(?array $departments): array
    {
        $until = date('Y-m-d', strtotime('+' . (int) Config::get('hr.category_warning_days', 180) . ' days'));
        $since = date('Y-m-d', strtotime('-' . (int) Config::get('hr.training_period_years', 5) . ' years'));

        return [
            'categories' => $this->repository->expiringCategories($until, $departments),
            'trainings' => $this->repository->trainingDue($since, $departments),
        ];
    }

    // ---- helpers -------------------------------------------------------------

    private function updatePerson(int $userId, array $data): void
    {
        $person = array_intersect_key($data, array_flip(self::PERSON));
        if ($person !== []) {
            $this->users->update($userId, $person);
        }
    }

    /** @return array<string, mixed> */
    private function file(array $data): array
    {
        $file = [];
        foreach (self::FILE as $key) {
            if (array_key_exists($key, $data)) {
                $file[$key] = $data[$key];
            }
        }
        if (array_key_exists('department_id', $file)) {
            $file['department_id'] = $file['department_id'] !== null ? (int) $file['department_id'] : null;
        }
        if (array_key_exists('prior_experience_months', $file)) {
            $file['prior_experience_months'] = (int) $file['prior_experience_months'];
        }
        if (isset($file['iin'])) {
            $file['iin'] = (string) $file['iin'];
        }

        return $file;
    }

    /** Job data of a teacher lives in the teacher profile too (timetable, journals, workload read it). */
    private function syncTeacher(int $userId, array $employee): void
    {
        $this->repository->syncTeacher($userId, [
            'department_id' => $employee['department_id'] ?? null,
            'position' => $employee['position'] ?? null,
            'hire_date' => $employee['hire_date'] ?? null,
            'status' => $employee['status'] ?? 'active',
        ]);
    }
}
