<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\ValidationException;
use App\Helpers\Csv;
use App\Platform\PlatformAuth;
use App\Platform\PlatformService;
use App\Repositories\SettingsRepository;
use App\Services\Settings;

/**
 * Owner's panel: colleges and their subscription, invoices and payments, usage reports,
 * the owner's requisites for invoices.
 */
final class PlatformController extends Controller
{
    public const SETTINGS = ['owner_name', 'owner_bin', 'owner_address', 'owner_phone', 'owner_bank', 'owner_iban', 'owner_bik', 'owner_kbe', 'invoice_service', 'payment_note'];

    private function service(): PlatformService
    {
        return new PlatformService(null, PlatformAuth::id());
    }

    public function index(Request $request): Response
    {
        $archived = $request->query('archived') === '1';
        $colleges = $this->collegesWithState($archived);
        if ($request->query('format') === 'csv') {
            $rows = [[__('platform.colleges'), format_date(date('Y-m-d'))],
                [__('platform.code'), __('platform.college'), __('platform.city'), __('platform.state'), __('platform.paid_until'), __('platform.monthly_price'),
                    __('platform.students'), __('platform.teachers'), __('platform.active_users_30'), __('platform.last_login'), __('platform.contact')]];
            foreach ($colleges as $c) {
                $s = $c['stats'];
                $rows[] = [$c['code'], $c['name'], $c['city'], __('platform.states.' . $c['state']), format_date($c['paid_until']), $c['monthly_price'],
                    $s['students'] ?? '', $s['teachers'] ?? '', $s['active_users_30'] ?? '', $s['last_login'] ?? '', trim(($c['contact_name'] ?? '') . ' ' . ($c['contact_phone'] ?? ''))];
            }

            return Csv::download($rows, 'colleges-' . date('Ymd') . '.csv');
        }
        $states = array_count_values(array_column($colleges, 'state'));

        return $this->view('platform/index', [
            'title' => __('platform.title'),
            'colleges' => $colleges,
            'states' => $states,
            'archived' => $archived,
            'archivedCount' => (int) Database::fetchColumn("SELECT COUNT(*) FROM colleges WHERE status = 'archived'"),
            'monthly' => array_sum(array_map(static fn (array $c): float => $c['state'] !== 'suspended' ? (float) $c['monthly_price'] : 0.0, $colleges)),
            'paidYear' => (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM college_invoices WHERE status = 'paid' AND YEAR(paid_on) = YEAR(CURDATE())"),
            'unpaid' => Database::fetch("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM college_invoices WHERE status = 'issued'"),
        ], 'layouts/platform');
    }

    // ---- colleges ------------------------------------------------------------

    public function create(): Response
    {
        return $this->view('platform/form', ['title' => __('platform.college_create'), 'college' => null, 'plans' => $this->plans()], 'layouts/platform');
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, PlatformService::collegeRules());
        try {
            $result = $this->service()->createCollege($data);
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
        Session::flash('success', __('platform.college_created'));
        if ($result['password'] !== null) {
            Session::flash('temp_password', ['login' => $result['login'], 'password' => $result['password']]);
        }

        return $this->redirect('/colleges/' . $result['id']);
    }

    public function show(int $id): Response
    {
        $college = $this->find($id);

        return $this->view('platform/show', [
            'title' => $college['name'],
            'college' => $college,
            'state' => Tenant::state($college),
            'stats' => $college['db_deleted_at'] === null ? PlatformService::stats($college) : ['error' => 'deleted'],
            'backups' => \App\Platform\CollegeManager::backups($college),
            'invoices' => Database::fetchAll('SELECT * FROM college_invoices WHERE college_id = ? ORDER BY issued_on DESC, id DESC', [$id]),
            'audit' => Database::fetchAll('SELECT a.*, p.name AS admin_name FROM platform_audit a LEFT JOIN platform_admins p ON p.id = a.admin_id WHERE a.college_id = ? ORDER BY a.id DESC LIMIT 20', [$id]),
            'tempPassword' => Session::pullFlash('temp_password'),
            'url' => $this->collegeUrl($college),
        ], 'layouts/platform');
    }

    public function edit(int $id): Response
    {
        return $this->view('platform/form', ['title' => __('platform.college_edit'), 'college' => $this->find($id), 'plans' => $this->plans()], 'layouts/platform');
    }

    public function update(Request $request, int $id): Response
    {
        $college = $this->find($id);
        $this->service()->updateCollege($college, $this->validate($request, PlatformService::collegeRules($college)));
        Session::flash('success', __('platform.college_updated'));

        return $this->redirect('/colleges/' . $id);
    }

    public function status(Request $request, int $id): Response
    {
        $college = $this->find($id);
        $status = $request->input('status') === 'suspended' ? 'suspended' : 'active';
        $this->service()->setStatus($college, $status);
        Session::flash('success', __($status === 'suspended' ? 'platform.college_suspended' : 'platform.college_resumed'));

        return $this->redirect('/colleges/' . $id);
    }

    public function adminPassword(int $id): Response
    {
        $credentials = $this->service()->resetAdminPassword($this->find($id));
        Session::flash('temp_password', $credentials);
        Session::flash('success', __('platform.admin_password_reset'));

        return $this->redirect('/colleges/' . $id);
    }

    // ---- invoices ------------------------------------------------------------

    public function storeInvoice(Request $request, int $id): Response
    {
        $invoiceId = $this->service()->createInvoice($this->find($id), $request->all());
        Session::flash('success', __('platform.invoice_created'));

        return $this->redirect('/invoices/' . $invoiceId);
    }

    public function invoice(Request $request, int $id): Response
    {
        $invoice = $this->findInvoice($id);
        $print = $request->query('format') === 'print';

        return $this->view('platform/invoice', [
            'title' => __('platform.invoice') . ' № ' . $invoice['number'],
            'invoice' => $invoice,
            'college' => $this->find((int) $invoice['college_id']),
            'owner' => array_combine(self::SETTINGS, array_map(static fn (string $k): string => (string) Settings::get($k, ''), self::SETTINGS)),
            'print' => $print,
        ], $print ? 'layouts/print' : 'layouts/platform');
    }

    public function payInvoice(Request $request, int $id): Response
    {
        $invoice = $this->findInvoice($id);
        $period = $this->service()->payInvoice($invoice, $request->all());
        Session::flash('success', __('platform.invoice_paid', ['date' => format_date($period['to'])]));

        return $this->redirect('/colleges/' . $invoice['college_id']);
    }

    public function cancelInvoice(int $id): Response
    {
        $invoice = $this->findInvoice($id);
        $this->service()->cancelInvoice($invoice);
        Session::flash('success', __('platform.invoice_cancelled'));

        return $this->redirect('/colleges/' . $invoice['college_id']);
    }

    // ---- reports -------------------------------------------------------------

    public function reports(Request $request): Response
    {
        $year = ctype_digit((string) $request->query('year', '')) ? (int) $request->query('year') : (int) date('Y');
        $colleges = $this->collegesWithState();
        $paid = [];
        foreach (Database::fetchAll(
            "SELECT college_id, MONTH(paid_on) AS m, SUM(amount) AS total FROM college_invoices WHERE status = 'paid' AND YEAR(paid_on) = ? GROUP BY college_id, MONTH(paid_on)",
            [$year]
        ) as $r) {
            $paid[(int) $r['college_id']][(int) $r['m']] = (float) $r['total'];
        }
        if ($request->query('format') === 'csv') {
            $rows = [[__('platform.reports'), $year], array_merge([__('platform.college'), __('platform.state'), __('platform.paid_until'), __('platform.students'), __('platform.teachers'),
                __('platform.active_users_30'), __('platform.logins_30'), __('platform.grades_30')], array_map(static fn (int $m): string => (string) $m, range(1, 12)), [__('platform.total')])];
            foreach ($colleges as $c) {
                $s = $c['stats'];
                $months = array_map(static fn (int $m): float => $paid[(int) $c['id']][$m] ?? 0.0, range(1, 12));
                $rows[] = array_merge([$c['name'], __('platform.states.' . $c['state']), format_date($c['paid_until']), $s['students'] ?? '', $s['teachers'] ?? '',
                    $s['active_users_30'] ?? '', $s['logins_30'] ?? '', $s['grades_30'] ?? ''], $months, [array_sum($months)]);
            }

            return Csv::download($rows, 'platform-report-' . $year . '.csv');
        }

        return $this->view('platform/reports', ['title' => __('platform.reports'), 'colleges' => $colleges, 'paid' => $paid, 'year' => $year], 'layouts/platform');
    }

    // ---- settings ------------------------------------------------------------

    public function settings(): Response
    {
        return $this->view('platform/settings', [
            'title' => __('platform.settings'),
            'values' => array_combine(self::SETTINGS, array_map(static fn (string $k): string => (string) Settings::get($k, ''), self::SETTINGS)),
        ], 'layouts/platform');
    }

    public function saveSettings(Request $request): Response
    {
        $data = $this->validate($request, array_fill_keys(self::SETTINGS, 'nullable|string|max:500'));
        $repo = new SettingsRepository();
        foreach (self::SETTINGS as $key) {
            $repo->set($key, isset($data[$key]) ? (string) $data[$key] : null, 'string', null);
        }
        Settings::flush();
        $this->service()->audit('settings.updated', null, null);
        Session::flash('success', __('platform.settings_saved'));

        return $this->redirect('/settings');
    }

    // ---- helpers -------------------------------------------------------------

    /** @return list<array<string, mixed>> colleges with 'state' and live 'stats' */
    private function collegesWithState(bool $archived = false): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT c.*, p.name AS plan_name, p.max_students FROM colleges c LEFT JOIN plans p ON p.id = c.plan_id WHERE (c.status = ?) = ? ORDER BY c.name', ['archived', $archived ? 1 : 0]) as $c) {
            $c['state'] = Tenant::state($c);
            $c['stats'] = $c['db_deleted_at'] === null ? PlatformService::stats($c) : ['error' => 'deleted'];
            $out[] = $c;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return Database::fetch('SELECT c.*, p.name AS plan_name, p.max_students FROM colleges c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.id = ?', [$id]) ?? throw new HttpException(404);
    }

    /** @return array<int, string> active tariffs (id => "name · limit") */
    private function plans(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT id, name, max_students, monthly_price FROM plans WHERE is_active = 1 ORDER BY monthly_price') as $p) {
            $out[(int) $p['id']] = $p['name'] . ' · ' . ($p['max_students'] !== null ? __('platform.up_to_students', ['n' => $p['max_students']]) : __('platform.unlimited'))
                . ' · ' . number_format((float) $p['monthly_price'], 0, ',', ' ') . ' ₸';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function findInvoice(int $id): array
    {
        return Database::fetch('SELECT * FROM college_invoices WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    /** Full sign-in address to give to the college (APP_URL, or the current host while it is localhost). */
    private function collegeUrl(array $college): string
    {
        $configured = (string) \App\Core\Config::get('app.url', '');
        $request = \App\Core\Application::instance()->request();
        $site = $configured !== '' && !str_contains($configured, 'localhost')
            ? rtrim($configured, '/')
            : (($request?->isSecure() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . Tenant::rootBase());

        return $site . '/' . $college['code'] . '/login';
    }
}
