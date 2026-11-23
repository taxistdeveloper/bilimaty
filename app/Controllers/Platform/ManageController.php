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
use App\Platform\CollegeManager;
use App\Platform\PlatformAuth;

/**
 * Owner's management of colleges: entering a college, its users, tariffs, announcements,
 * backups, archive and deletion of the database.
 */
final class ManageController extends Controller
{
    private function manager(): CollegeManager
    {
        return new CollegeManager(PlatformAuth::id(), (string) (PlatformAuth::user()['name'] ?? ''));
    }

    // ---- entering a college ----------------------------------------------------

    /** One-time link → the college opens signed in as its administrator. */
    public function impersonate(Request $request, int $id): Response
    {
        $college = $this->college($id);
        $userId = ctype_digit((string) $request->input('user_id', '')) ? (int) $request->input('user_id') : $this->adminUserId($college);
        if ($userId === null) {
            throw new ValidationException(['user_id' => [__('platform.admin_missing')]]);
        }
        $link = $this->manager()->loginLink($college, $userId);

        return Response::redirect((string) Tenant::rootBase() . $link);
    }

    // ---- users of a college ---------------------------------------------------

    public function users(Request $request, int $id): Response
    {
        $college = $this->college($id);
        $filters = ['q' => mb_substr(trim((string) $request->query('q', '')), 0, 100), 'role' => preg_match('/^[a-z_]{1,50}$/', (string) $request->query('role', '')) ? (string) $request->query('role') : ''];

        return $this->view('platform/users', [
            'title' => __('platform.users_title') . ' · ' . $college['name'],
            'college' => $college,
            'filters' => $filters,
            'roles' => $this->manager()->roles($college),
            'result' => $this->manager()->users($college, $filters, max(1, (int) $request->query('page', 1))),
            'tempPassword' => Session::pullFlash('temp_password'),
        ], 'layouts/platform');
    }

    public function addAdmin(Request $request, int $id): Response
    {
        $credentials = $this->manager()->addAdmin($this->college($id), $request->all());
        Session::flash('temp_password', $credentials);
        Session::flash('success', __('platform.admin_added'));

        return $this->redirect('/colleges/' . $id . '/users');
    }

    public function userActive(Request $request, int $id, int $userId): Response
    {
        $active = $request->input('active') === '1';
        $this->manager()->setActive($this->college($id), $userId, $active);
        Session::flash('success', __($active ? 'platform.user_unblocked' : 'platform.user_blocked'));

        return $this->back($request, '/colleges/' . $id . '/users');
    }

    public function userPassword(Request $request, int $id, int $userId): Response
    {
        Session::flash('temp_password', $this->manager()->resetPassword($this->college($id), $userId));
        Session::flash('success', __('platform.user_password_reset'));

        return $this->back($request, '/colleges/' . $id . '/users');
    }

    // ---- backups and archive ---------------------------------------------------

    public function backup(int $id): Response
    {
        $name = $this->manager()->backup($this->college($id));
        Session::flash('success', __('platform.backup_created', ['name' => $name]));

        return $this->redirect('/colleges/' . $id);
    }

    public function downloadBackup(int $id, string $name): Response
    {
        $college = $this->college($id, true);
        $name .= '.sql.gz';
        $path = CollegeManager::backupPath($college, $name) ?? throw new HttpException(404);
        (new \App\Platform\PlatformService(null, PlatformAuth::id()))->audit('college.backup_downloaded', $id, ['file' => $name]);

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length' => (string) filesize($path),
        ]);
    }

    public function archive(Request $request, int $id): Response
    {
        $archive = $request->input('archive') === '1';
        $this->manager()->archive($this->college($id, true), $archive);
        Session::flash('success', __($archive ? 'platform.college_archived' : 'platform.college_restored'));

        return $this->redirect('/colleges/' . $id);
    }

    public function deleteDatabase(Request $request, int $id): Response
    {
        try {
            $this->manager()->deleteDatabase($this->college($id, true), (string) $request->input('confirm', ''));
        } catch (ValidationException $e) {
            Session::flash('error', implode(' ', array_merge(...array_values($e->errors))));

            return $this->redirect('/colleges/' . $id);
        }
        Session::flash('success', __('platform.db_deleted_done'));

        return $this->redirect('/colleges/' . $id);
    }

    // ---- plans -----------------------------------------------------------------

    public function plans(): Response
    {
        return $this->view('platform/plans', [
            'title' => __('platform.plans'),
            'plans' => Database::fetchAll("SELECT p.*, (SELECT COUNT(*) FROM colleges c WHERE c.plan_id = p.id AND c.status <> 'archived') AS colleges FROM plans p ORDER BY p.is_active DESC, p.monthly_price"),
        ], 'layouts/platform');
    }

    public function storePlan(Request $request): Response
    {
        $this->manager()->savePlan(null, ['is_active' => 1] + array_filter($this->validate($request, CollegeManager::planRules()), static fn ($v): bool => $v !== null) + ['max_students' => null]);
        Session::flash('success', __('platform.plan_saved'));

        return $this->redirect('/plans');
    }

    public function updatePlan(Request $request, int $id): Response
    {
        $plan = Database::fetch('SELECT * FROM plans WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $this->manager()->savePlan($plan, $this->validate($request, CollegeManager::planRules()));
        Session::flash('success', __('platform.plan_saved'));

        return $this->redirect('/plans');
    }

    // ---- announcements ---------------------------------------------------------

    public function announcements(): Response
    {
        return $this->view('platform/announcements', [
            'title' => __('platform.announcements'),
            'items' => Database::fetchAll('SELECT a.*, c.name AS college_name FROM announcements a LEFT JOIN colleges c ON c.id = a.college_id ORDER BY a.starts_on DESC, a.id DESC LIMIT 200'),
            'colleges' => Database::fetchAll("SELECT id, name FROM colleges WHERE status <> 'archived' ORDER BY name"),
        ], 'layouts/platform');
    }

    public function storeAnnouncement(Request $request): Response
    {
        $this->manager()->addAnnouncement($request->all());
        Session::flash('success', __('platform.announcement_saved'));

        return $this->redirect('/announcements');
    }

    public function deleteAnnouncement(int $id): Response
    {
        $item = Database::fetch('SELECT * FROM announcements WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $this->manager()->deleteAnnouncement($item);
        Session::flash('success', __('platform.announcement_deleted'));

        return $this->redirect('/announcements');
    }

    // ---- helpers ---------------------------------------------------------------

    /** @return array<string, mixed> */
    private function college(int $id, bool $evenWithoutDatabase = false): array
    {
        $college = Database::fetch('SELECT c.*, p.name AS plan_name, p.max_students FROM colleges c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.id = ?', [$id]) ?? throw new HttpException(404);
        if (!$evenWithoutDatabase && $college['db_deleted_at'] !== null) {
            throw new HttpException(404);
        }

        return $college;
    }

    private function adminUserId(array $college): ?int
    {
        $pdo = \App\Platform\Platform::tenantPdo((string) $college['db_name']);
        $st = $pdo->prepare("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                             WHERE u.is_active = 1 AND u.deleted_at IS NULL AND r.slug IN ('super_admin','admin')
                             ORDER BY u.login = ? DESC, r.slug = 'super_admin' DESC, u.id LIMIT 1");
        $st->execute([(string) ($college['admin_login'] ?? '')]);
        $id = $st->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
