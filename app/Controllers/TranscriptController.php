<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Helpers\QrCode;
use App\Repositories\JournalRepository;
use App\Repositories\StudentRepository;
use App\Services\AccessScope;
use App\Services\TranscriptService;

/**
 * Transcripts.
 *   staff preview / list:  transcripts.view + student's group in AccessScope
 *   issue / revoke:        transcripts.issue + scope
 *   student:               own preview (not an official document) and own issued transcripts
 *   public /verify/{code}: document facts only, no personal data
 */
final class TranscriptController extends Controller
{
    public function index(Request $request): Response
    {
        if ($this->ownStudentId() !== null && !Auth::can('groups.view')) {
            return $this->redirect('/transcripts/my');
        }
        [$scopeSql, $params] = AccessScope::condition('s.current_group_id', AccessScope::groupIds());
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $where = $scopeSql;
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where .= " AND (tr.number LIKE ? OR CONCAT_WS(' ', u.last_name, u.first_name, u.middle_name) LIKE ? OR s.student_number LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        $issued = Database::fetchAll(
            "SELECT tr.id, tr.number, tr.status, tr.issued_at, s.id AS student_id, s.student_number, u.last_name, u.first_name, u.middle_name, g.code AS group_code
             FROM transcripts tr JOIN students s ON s.id = tr.student_id JOIN users u ON u.id = s.user_id
             LEFT JOIN `groups` g ON g.id = s.current_group_id WHERE {$where} ORDER BY tr.issued_at DESC LIMIT 200",
            $params
        );

        return $this->view('transcripts/index', ['title' => __('transcripts.title'), 'issued' => $issued, 'q' => $q]);
    }

    /** Live preview of a student's transcript for staff (+ issue button). */
    public function preview(int $id): Response
    {
        $student = $this->visibleStudent($id);

        return $this->view('transcripts/preview', [
            'title' => __('transcripts.title') . ': ' . full_name($student),
            'data' => (new TranscriptService())->build($id),
            'issued' => $this->issuedOf($id),
            'canIssue' => Auth::can('transcripts.issue'),
            'own' => false,
        ]);
    }

    public function my(): Response
    {
        $studentId = $this->ownStudentId() ?? throw new HttpException(403);

        return $this->view('transcripts/preview', [
            'title' => __('transcripts.my_title'),
            'data' => (new TranscriptService())->build($studentId),
            'issued' => $this->issuedOf($studentId),
            'canIssue' => false,
            'own' => true,
        ]);
    }

    public function issue(int $id): Response
    {
        $this->visibleStudent($id);
        if (!Auth::can('transcripts.issue')) {
            throw new HttpException(403);
        }
        try {
            $transcriptId = (new TranscriptService())->issue($id);
        } catch (ValidationException $e) {
            Session::flash('error', (string) (array_values($e->errors)[0][0] ?? ''));

            return $this->redirect('/students/' . $id . '/transcript');
        }
        Session::flash('success', __('transcripts.issued_ok'));

        return $this->redirect('/transcripts/' . $transcriptId);
    }

    /** Issued transcript: A4 document with number, fingerprint and QR code. */
    public function show(Request $request, int $id): Response
    {
        $transcript = $this->visibleTranscript($id);
        $url = self::verifyUrl($request, (string) $transcript['verification_code']);

        return $this->view('transcripts/document', [
            'title' => __('transcripts.document') . ' ' . $transcript['number'],
            't' => $transcript,
            'qr' => QrCode::svg($url, 4, __('transcripts.qr_label')),
            'verifyUrl' => $url,
            'canRevoke' => Auth::can('transcripts.issue') && $transcript['status'] === 'issued',
        ], 'layouts/print');
    }

    /** CSV (UTF-8 with BOM, ";") of an issued transcript — opens in Excel. */
    public function export(int $id): Response
    {
        $t = $this->visibleTranscript($id);
        $h = $t['snapshot']['header'];
        $codes = [];
        foreach ($t['items'] as $item) {
            $codes = array_values(array_unique([...$codes, ...array_keys((array) $item['components'])]));
        }
        $rows = [
            [__('transcripts.document'), $t['number'], __('transcripts.issued_at'), format_date($t['issued_at'])],
            [__('users.name'), $h['full_name'], __('fields.student_number'), $h['student_number']],
            [__('fields.specialty_id'), $h['specialty'] ?? '', __('fields.group_id'), $h['group'] ?? ''],
            [],
            [__('fields.academic_year_id'), __('fields.semester'), __('curricula.subject'), __('transcripts.hours'), __('curricula.credits'), ...$codes, __('journal.final'), __('transcripts.letter')],
        ];
        foreach ($t['items'] as $item) {
            $row = [$item['academic_year'], $item['semester_number'], $item['subject_code'] . ' ' . localized(['name_ru' => $item['subject_name_ru'], 'name_kk' => $item['subject_name_kk']]),
                $item['hours'] ?? '', $item['credits'] ?? ''];
            foreach ($codes as $code) {
                $row[] = $item['components'][$code] ?? '';
            }
            $row[] = $item['final'] ?? '';
            $row[] = $item['letter'] ?? '';
            $rows[] = $row;
        }
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ';', '"', '\\');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="transcript-' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $t['number']) . '.csv"',
        ]);
    }

    public function revoke(Request $request, int $id): Response
    {
        $t = $this->visibleTranscript($id);
        if (!Auth::can('transcripts.issue')) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, ['reason' => 'required|string|max:255']);
        (new TranscriptService())->revoke($t, (string) $data['reason']);
        Session::flash('success', __('transcripts.revoked_ok'));

        return $this->redirect('/students/' . $t['student_id'] . '/transcript');
    }

    /** Public verification page (no authentication, no personal data). */
    public function verify(Request $request, ?string $code = null): Response
    {
        $code = $code ?? (string) $request->query('code', '');
        $normalized = strtolower(trim($code));
        // One check page for every document with a QR code: transcripts and certificates.
        $result = null;
        if (preg_match('/^[a-f0-9]{32}$/', $normalized)) {
            $result = (new TranscriptService())->verify($normalized);
            $result = $result !== null ? $result + ['kind' => 'transcript'] : (new \App\Repositories\DocumentRepository())->verifyDocument($normalized);
            if ($result !== null && $result['kind'] !== 'transcript') {
                $result['status'] = $result['status'] === 'valid' ? 'issued' : 'revoked';
            }
        }

        return $this->view('transcripts/verify', [
            'title' => __('transcripts.verify_title_any'),
            'code' => $code,
            'result' => $result,
        ], 'layouts/auth');
    }

    /** GET /api/students/{id}/transcript — live transcript data (same access rules). */
    public function api(int $id): Response
    {
        $own = $this->ownStudentId();
        if ($own !== $id) {
            $this->visibleStudent($id);
        }

        return $this->json((new TranscriptService())->build($id));
    }

    // ------------------------------------------------------------------ helpers

    /** Public check URL put into QR codes (APP_URL, or the current host while APP_URL is localhost). */
    public static function verifyUrl(Request $request, string $code): string
    {
        $configured = (string) Config::get('app.url', '');
        $base = $configured !== '' && !str_contains($configured, 'localhost')
            ? rtrim($configured, '/') . \App\Core\Tenant::pathPrefix()
            : ($request->isSecure() ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . $request->baseUrl;

        return $base . '/verify/' . $code;
    }

    /** @return list<array<string, mixed>> */
    private function issuedOf(int $studentId): array
    {
        return Database::fetchAll('SELECT id, number, status, issued_at, gpa FROM transcripts WHERE student_id = ? ORDER BY issued_at DESC', [$studentId]);
    }

    private function ownStudentId(): ?int
    {
        return (new JournalRepository())->studentIdOfUser((int) Auth::id());
    }

    /** @return array<string, mixed> */
    private function visibleStudent(int $id): array
    {
        if (!Auth::can('transcripts.view') || !Auth::can('students.view')) {
            throw new HttpException(403);
        }
        $student = (new StudentRepository())->find($id) ?? throw new HttpException(404);
        if (!AccessScope::allowsGroup($student['current_group_id'] !== null ? (int) $student['current_group_id'] : null)) {
            throw new HttpException(403);
        }

        return $student;
    }

    /** @return array<string, mixed> */
    private function visibleTranscript(int $id): array
    {
        $t = (new TranscriptService())->find($id) ?? throw new HttpException(404);
        if ($this->ownStudentId() !== (int) $t['student_id']) {
            $this->visibleStudent((int) $t['student_id']);
        }

        return $t;
    }
}
