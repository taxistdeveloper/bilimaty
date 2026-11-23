<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Personnel department: personnel files, orders (signing applies them and syncs the teacher profile,
 * dismissal closes the account), leave requests with the annual balance, absences in the timetable,
 * professional development and reminders, department scope and personal data.
 */
final class HrTest extends IntegrationTestCase
{
    private function hr(): Http
    {
        return $this->as('t.hr', 'hr');
    }

    /** Personnel files of all teachers (idempotent). */
    private function registerTeachers(Http $hr): void
    {
        $hr->submit('/hr/employees/register-teachers', []);
    }

    private function employeeOf(string $login): int
    {
        return (int) $this->value('SELECT e.id FROM employees e JOIN users u ON u.id = e.user_id WHERE u.login = ?', [$login]);
    }

    private function signLastOrder(Http $hr): string
    {
        $order = (int) $this->value('SELECT MAX(id) FROM hr_orders');
        $hr->submit("/hr/orders/{$order}/sign", []);

        return (string) $this->value('SELECT number FROM hr_orders WHERE id = ?', [$order]);
    }

    public function testPersonnelFilesAndOrders(): void
    {
        $hr = $this->hr();
        $this->assertSame(200, $hr->get('/hr')['status']);
        $this->registerTeachers($hr);
        $this->assertSame((int) $this->value('SELECT COUNT(*) FROM teachers'), (int) $this->value('SELECT COUNT(*) FROM employees e JOIN teachers t ON t.user_id = e.user_id'), 'Every teacher has a file');
        $this->registerTeachers($hr);
        $this->assertSame((int) $this->value('SELECT COUNT(*) FROM teachers'), (int) $this->value('SELECT COUNT(*) FROM employees e JOIN teachers t ON t.user_id = e.user_id'), 'No duplicates');

        // Transfer of a teacher: a draft changes nothing, signing gives a number and updates the teacher profile.
        $id = $this->employeeOf('petrov.av');
        $today = date('Y-m-d');
        $orders = (int) $this->value('SELECT COUNT(*) FROM hr_orders');
        $hr->submit("/hr/employees/{$id}/orders", ['_form' => 'order', 'type' => 'transfer', 'order_date' => $today, 'effective_date' => $today, 'position' => '']);
        $this->assertSame($orders, (int) $this->value('SELECT COUNT(*) FROM hr_orders'), 'Position is required for a transfer');
        $hr->submit("/hr/employees/{$id}/orders", ['_form' => 'order', 'type' => 'transfer', 'order_date' => $today, 'effective_date' => $today, 'position' => 'Старший преподаватель', 'basis' => 'Заявление']);
        $this->assertSame('draft', $this->value('SELECT status FROM hr_orders WHERE id = (SELECT MAX(id) FROM hr_orders)'));
        $this->assertNotSame('Старший преподаватель', $this->value('SELECT t.position FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.login = ?', ['petrov.av']));
        $number = $this->signLastOrder($hr);
        $this->assertMatchesRegularExpression('/^К-' . date('Y') . '-\d{5}$/u', $number);
        $this->assertSame('Старший преподаватель', $this->value('SELECT position FROM employees WHERE id = ?', [$id]));
        $this->assertSame('Старший преподаватель', $this->value('SELECT t.position FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.login = ?', ['petrov.av']), 'Teacher profile synced');
        $order = (int) $this->value('SELECT MAX(id) FROM hr_orders');
        $this->assertStringContainsString($number, $hr->get("/hr/orders/{$order}?format=print")['body']);
        $hr->submit("/hr/orders/{$order}/sign", []);
        $this->assertSame($number, $this->value('SELECT number FROM hr_orders WHERE id = ?', [$order]), 'A signed order is not signed again');

        // A new staff member without system access; dismissal today closes the account.
        $hr->submit('/hr/employees', ['last_name' => 'Кадрова', 'first_name' => 'Айгерим', 'personnel_number' => 'T-901', 'staff_category' => 'support', 'employment_type' => 'main', 'rate' => '1', 'hire_date' => '2020-09-01']);
        $new = (int) $this->value("SELECT id FROM employees WHERE personnel_number = 'T-901'");
        $this->assertTrue($new > 0, 'File created');
        $user = (int) $this->value('SELECT user_id FROM employees WHERE id = ?', [$new]);
        $this->assertSame(0, (int) $this->value('SELECT is_active FROM users WHERE id = ?', [$user]), 'No access');
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM user_roles WHERE user_id = ?', [$user]), 'No roles');
        $hr->submit('/hr/employees', ['last_name' => 'Дубль', 'first_name' => 'Д', 'personnel_number' => 'T-901', 'staff_category' => 'support', 'employment_type' => 'main', 'rate' => '1']);
        $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM users WHERE last_name = 'Дубль'"), 'Personnel number is unique');

        // Dismissal of a teacher with access: today → account closed at once; a future date → by hr:daily.
        $teacher = $this->employeeOf('omarov.dm');
        $hr->submit("/hr/employees/{$teacher}/orders", ['_form' => 'order', 'type' => 'dismissal', 'order_date' => $today, 'effective_date' => date('Y-m-d', strtotime('+5 days')), 'reason' => 'по собственному желанию']);
        $this->signLastOrder($hr);
        $this->assertSame('dismissed', $this->value('SELECT status FROM employees WHERE id = ?', [$teacher]));
        $this->assertSame('dismissed', $this->value('SELECT t.status FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.login = ?', ['omarov.dm']));
        $this->assertSame(1, (int) $this->value("SELECT is_active FROM users WHERE login = 'omarov.dm'"), 'Account works until the dismissal date');
        $orders = (int) $this->value('SELECT COUNT(*) FROM hr_orders');
        $hr->submit("/hr/employees/{$teacher}/orders", ['_form' => 'order', 'type' => 'transfer', 'order_date' => $today, 'effective_date' => $today, 'position' => 'X']);
        $this->assertSame($orders, (int) $this->value('SELECT COUNT(*) FROM hr_orders'), 'Only a hire order for a dismissed employee');
        $this->db()->prepare('UPDATE employees SET dismissal_date = CURDATE() - INTERVAL 1 DAY WHERE id = ?')->execute([$teacher]);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/cli.php') . ' hr:daily', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertSame(0, (int) $this->value("SELECT is_active FROM users WHERE login = 'omarov.dm'"), 'hr:daily closed the account');

        // Re-hire restores access.
        $hr->submit("/hr/employees/{$teacher}/orders", ['_form' => 'order', 'type' => 'hire', 'order_date' => $today, 'effective_date' => $today, 'position' => 'Преподаватель', 'rate' => '1.00', 'staff_category' => 'teaching', 'employment_type' => 'main']);
        $this->signLastOrder($hr);
        $this->assertSame('active', $this->value('SELECT status FROM employees WHERE id = ?', [$teacher]));
        $this->assertSame(1, (int) $this->value("SELECT is_active FROM users WHERE login = 'omarov.dm'"));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'hr.order_signed' AND entity_id = ?", [$order]));
    }

    public function testLeaveRequestsBalanceAndTimetable(): void
    {
        $hr = $this->hr();
        $this->registerTeachers($hr);
        $teacher = $this->as('petrov.av');
        $employee = $this->employeeOf('petrov.av');
        $this->db()->prepare('DELETE FROM hr_leaves WHERE employee_id = ?')->execute([$employee]);
        $this->db()->prepare("UPDATE employees SET staff_category = 'teaching', annual_leave_days = NULL, status = 'active' WHERE id = ?")->execute([$employee]);
        $page = $teacher->get('/my/leaves');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('56', $page['body'], 'Teaching staff: 56 days by default');
        $this->assertSame(403, $teacher->get('/hr/leaves')['status'], 'A teacher does not see the HR section');

        // Requests: past dates, more than the balance and overlaps are refused.
        $start = strtotime('first day of next month');
        $d = static fn (int $days): string => date('Y-m-d', $start + $days * 86400);
        $this->assertNotSame([], $teacher->submit('/my/leaves', ['_form' => 'leave', 'type' => 'annual', 'start_date' => date('Y-m-d', strtotime('-3 days')), 'end_date' => date('Y-m-d')])['errors'], 'Past dates');
        $this->assertNotSame([], $teacher->submit('/my/leaves', ['_form' => 'leave', 'type' => 'sick', 'start_date' => $d(0), 'end_date' => $d(2)])['errors'], 'Sick leave is recorded by HR');
        $this->db()->prepare('UPDATE employees SET annual_leave_days = 20 WHERE id = ?')->execute([$employee]);
        $this->assertNotSame([], $teacher->submit('/my/leaves', ['_form' => 'leave', 'type' => 'annual', 'start_date' => $d(0), 'end_date' => $d(24)])['errors'], 'More than the 20 days of the file');
        $r = $teacher->submit('/my/leaves', ['_form' => 'leave', 'type' => 'annual', 'start_date' => $d(0), 'end_date' => $d(13), 'reason' => 'Отдых']);
        $this->assertSame([], $r['errors']);
        $leave = (int) $this->value('SELECT MAX(id) FROM hr_leaves WHERE employee_id = ?', [$employee]);
        $this->assertSame('requested', $this->value('SELECT status FROM hr_leaves WHERE id = ?', [$leave]));
        $this->assertSame(14, (int) $this->value('SELECT days FROM hr_leaves WHERE id = ?', [$leave]));
        $this->assertNotSame([], $teacher->submit('/my/leaves', ['_form' => 'leave', 'type' => 'unpaid', 'start_date' => $d(5), 'end_date' => $d(6)])['errors'], 'Overlap');
        $this->assertTrue((int) $this->value("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.login = 't.hr' AND n.type = 'hr'") > 0, 'HR is notified');

        // Decisions: a rejection needs a reason; approval notifies the employee.
        $this->assertSame(403, $teacher->request('POST', "/hr/leaves/{$leave}/approve", [])['status']);
        $hr->submit("/hr/leaves/{$leave}/reject", ['comment' => '']);
        $this->assertSame('requested', $this->value('SELECT status FROM hr_leaves WHERE id = ?', [$leave]), 'A rejection needs a reason');
        $hr->submit("/hr/leaves/{$leave}/approve", []);
        $this->assertSame('approved', $this->value('SELECT status FROM hr_leaves WHERE id = ?', [$leave]));
        $this->assertTrue((int) $this->value("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.login = 'petrov.av' AND n.type = 'hr'") > 0);
        $teacher->submit("/my/leaves/{$leave}/cancel", []);
        $this->assertSame('approved', $this->value('SELECT status FROM hr_leaves WHERE id = ?', [$leave]), 'An approved leave is cancelled by HR only');
        if (date('Y', $start) === date('Y')) {
            $this->assertMatchesRegularExpression('/display-6 fw-bold">6</', $teacher->get('/my/leaves')['body'], 'Balance: 20 − 14');
        }

        // A sick leave recorded by HR is approved at once and shown in the timetable for substitutions.
        $hr->submit("/hr/employees/{$employee}/leaves", ['_form' => 'leave', 'type' => 'sick', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+2 days')), 'document_number' => 'ЛН-123']);
        $this->assertSame('approved', $this->value("SELECT status FROM hr_leaves WHERE employee_id = ? AND type = 'sick'", [$employee]));
        $this->assertStringContainsString('Петров', $this->as('t.office', 'academic_office')->get('/schedule')['body'], 'Absent teacher in the timetable');
        $this->assertStringContainsString('Петров', $hr->get('/hr')['body'], 'Absent today on the HR overview');
        // Someone else's request cannot be withdrawn.
        $other = $this->employeeOf('seitqali.ab');
        $this->db()->prepare("INSERT INTO hr_leaves (employee_id, type, start_date, end_date, days, status) VALUES (?, 'unpaid', CURDATE() + INTERVAL 200 DAY, CURDATE() + INTERVAL 201 DAY, 2, 'requested')")->execute([$other]);
        $foreignLeave = (int) $this->db()->lastInsertId();
        $this->assertSame(403, $teacher->request('POST', "/my/leaves/{$foreignLeave}/cancel", [])['status']);
        $this->assertSame('requested', $this->value('SELECT status FROM hr_leaves WHERE id = ?', [$foreignLeave]));
    }

    public function testQualificationScopeAndPersonalData(): void
    {
        $hr = $this->hr();
        $this->registerTeachers($hr);
        $own = $this->employeeOf('seitqali.ab');
        $ownDept = (int) $this->value('SELECT department_id FROM employees WHERE id = ?', [$own]);
        $foreign = (int) $this->value('SELECT id FROM employees WHERE department_id IS NOT NULL AND department_id <> ? LIMIT 1', [$ownDept]);
        $this->assertTrue($foreign > 0, 'Another department exists');
        $this->db()->prepare("UPDATE employees SET iin = '900101300123', address = 'ул. Абая 1' WHERE id = ?")->execute([$own]);

        // Department head: own department only, read-only, no personal data.
        $head = $this->as('seitqali.ab');
        $this->assertSame(200, $head->get("/hr/employees/{$own}")['status']);
        $this->assertStringNotContainsString('900101300123', $head->get("/hr/employees/{$own}")['body'], 'IIN hidden from a department head');
        $this->assertSame(403, $head->get("/hr/employees/{$foreign}")['status']);
        $this->assertSame(403, $head->get('/hr/employees/create')['status']);
        $this->assertSame(403, $head->request('POST', "/hr/employees/{$own}/orders", ['type' => 'other'])['status']);
        $list = $head->get('/hr/employees?status=all')['body'];
        $this->assertStringNotContainsString('/hr/employees/' . $foreign . '"', $list, 'List is scoped');
        $this->assertStringContainsString('900101300123', $hr->get("/hr/employees/{$own}")['body'], 'HR sees the IIN');
        $this->assertStringNotContainsString('900101300123', $head->get('/hr/employees?format=csv')['body'], 'CSV without IIN for a department head');

        // Professional development and reminders.
        $this->db()->prepare('DELETE FROM hr_trainings WHERE employee_id = ?')->execute([$own]);
        $hr->submit("/hr/employees/{$own}/trainings", ['_form' => 'training', 'title' => 'Цифровая педагогика', 'hours' => 72, 'start_date' => date('Y-m-d', strtotime('+1 day')), 'end_date' => date('Y-m-d', strtotime('+10 days'))]);
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM hr_trainings WHERE employee_id = ?', [$own]), 'Unfinished courses are not added');
        $this->db()->prepare("UPDATE employees SET qualification_category = 'expert', category_assigned_on = '2021-12-01', category_valid_until = CURDATE() + INTERVAL 30 DAY, status = 'active' WHERE id = ?")->execute([$own]);
        $name = (string) $this->value('SELECT u.last_name FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = ?', [$own]);
        $overview = $hr->get('/hr')['body'];
        $this->assertStringContainsString($name, $overview, 'Reminder about the category and missing courses');
        $hr->submit("/hr/employees/{$own}/trainings", ['_form' => 'training', 'title' => 'Цифровая педагогика', 'provider' => 'Өрлеу', 'hours' => 72, 'start_date' => date('Y-m-d', strtotime('-20 days')), 'end_date' => date('Y-m-d', strtotime('-10 days')), 'certificate_number' => 'С-77']);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM hr_trainings WHERE employee_id = ?', [$own]));
        $csv = $hr->get('/hr/qualification?format=csv');
        $this->assertStringContainsString('text/csv', $csv['type']);
        $this->assertStringContainsString($name, $csv['body']);
        $this->assertStringContainsString(date('d.m.Y', strtotime('-10 days')), $csv['body'], 'Last courses in the report');
        $this->assertSame(200, $hr->get('/hr/qualification?format=print')['status']);
    }
}
