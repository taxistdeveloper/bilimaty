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
use App\Helpers\QrCode;
use App\Repositories\DocumentRepository;
use App\Repositories\JournalRepository;
use App\Repositories\StudentRepository;
use App\Services\AccessScope;
use App\Services\DocumentService;

/**
 * Requests and certificates: the student's requests and the staff inbox.
 *
 * A student (documents.request) works only with own requests. Staff (documents.process) see
 * submitted requests of students in their scope and move them through the workflow.
 */
final class DocumentController extends Controller
{
    private DocumentRepository $documents;
    private DocumentService $service;

    public function __construct()
    {
        $this->documents = new DocumentRepository();
        $this->service = new DocumentService($this->documents);
    }

    public function index(Request $request): Response
    {
        if (!Auth::can('documents.process')) {
            $studentId = $this->ownStudentId() ?? throw new HttpException(403);

            return $this->view('documents/my', [
                'title' => __('documents.my_title'),
                'requests' => $this->documents->forStudent($studentId),
                'types' => $this->documents->types(),
            ]);
        }
        $filters = [
            'status' => (string) $request->query('status', 'active'),
            'type' => ctype_digit((string) $request->query('type', '')) ? (int) $request->query('type') : null,
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'overdue' => $request->query('overdue') === '1',
            'mine' => $request->query('mine') === '1',
        ];
        $groups = AccessScope::groupIds();

        return $this->view('documents/index', [
            'title' => __('documents.title'),
            'filters' => $filters,
            'result' => $this->documents->inbox($filters, $groups, (int) Auth::id(), max(1, (int) $request->query('page', 1))),
            'counts' => $this->documents->counts($groups),
            'types' => $this->documents->types(false),
        ]);
    }

    public function create(Request $request): Response
    {
        $studentId = $this->requireStudent();
        $type = ctype_digit((string) $request->query('type', '')) ? $this->documents->type((int) $request->query('type')) : null;
        if ($type === null || !(bool) $type['is_active']) {
            return $this->redirect('/documents');
        }

        return $this->form($type, null, $studentId);
    }

    public function store(Request $request): Response
    {
        $studentId = $this->requireStudent();
        $type = $this->documents->type((int) $request->input('type_id', 0));
        if ($type === null || !(bool) $type['is_active']) {
            throw new HttpException(404);
        }
        $student = (new StudentRepository())->find($studentId) ?? throw new HttpException(404);
        $submit = $request->input('action') === 'submit';
        $id = $this->withInput($request, fn (): int => $this->service->save($student, $type, $request->all(), null, $this->file($request), $submit));
        Session::flash('success', __($submit ? 'documents.submitted' : 'documents.draft_saved'));

        return $this->redirect('/documents/' . $id);
    }

    /**
     * POST /api/document-requests {type_id, purpose?, copies?, details?, changes?, submit?}
     * Files a request of the signed-in student (attachments are added in the web form).
     */
    public function apiStore(Request $request): Response
    {
        $studentId = $this->requireStudent();
        $type = $this->documents->type((int) $request->input('type_id', 0));
        if ($type === null || !(bool) $type['is_active']) {
            throw new ValidationException(['type_id' => [__('validation.exists', ['field' => __('documents.type')])]]);
        }
        $student = (new StudentRepository())->find($studentId) ?? throw new HttpException(404);
        $submit = !in_array($request->input('submit', true), [false, 0, '0', 'false'], true);
        $id = $this->service->save($student, $type, $request->all(), null, null, $submit);
        $r = $this->documents->find($id);

        return $this->json(['id' => $id, 'number' => $r['number'], 'status' => $r['status'], 'due_date' => $r['due_date']], __($submit ? 'documents.submitted' : 'documents.draft_saved'), 201);
    }

    public function show(int $id): Response
    {
        $r = $this->find($id);
        $own = $this->isOwner($r);
        $process = $this->canProcess($r);
        if (!$own && !$process) {
            throw new HttpException(403);
        }
        $student = (new StudentRepository())->find((int) $r['student_id']);

        return $this->view('documents/show', [
            'title' => __('documents.request') . ($r['number'] !== null ? ' ' . $r['number'] : ''),
            'r' => $r,
            'student' => $student,
            'changes' => $r['payload'] !== null ? (array) json_decode((string) $r['payload'], true) : [],
            'history' => $this->documents->history($id, $process),
            'document' => $this->documents->documentOfRequest($id),
            'own' => $own,
            'process' => $process,
        ]);
    }

    public function edit(int $id): Response
    {
        $r = $this->find($id);
        if (!$this->isOwner($r) || !in_array($r['status'], ['draft', 'needs_info'], true)) {
            throw new HttpException(403);
        }

        return $this->form($this->documents->type((int) $r['type_id']) ?? throw new HttpException(404), $r, (int) $r['student_id']);
    }

    public function update(Request $request, int $id): Response
    {
        $r = $this->find($id);
        if (!$this->isOwner($r)) {
            throw new HttpException(403);
        }
        $student = (new StudentRepository())->find((int) $r['student_id']) ?? throw new HttpException(404);
        $type = $this->documents->type((int) $r['type_id']) ?? throw new HttpException(404);
        $submit = $request->input('action') === 'submit';
        $this->withInput($request, fn (): int => $this->service->save($student, $type, $request->all(), $r, $this->file($request), $submit));
        Session::flash('success', __($submit ? 'documents.submitted' : 'documents.draft_saved'));

        return $this->redirect('/documents/' . $id);
    }

    public function destroy(int $id): Response
    {
        $r = $this->find($id);
        if (!$this->isOwner($r)) {
            throw new HttpException(403);
        }
        $this->service->deleteDraft($r);
        Session::flash('success', __('documents.deleted'));

        return $this->redirect('/documents');
    }

    public function status(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $action = (string) $request->input('action', '');
        if ($action === 'submit') {
            if (!$this->isOwner($r)) {
                throw new HttpException(403);
            }
            $this->service->submit($r);
            Session::flash('success', __('documents.submitted'));

            return $this->redirect('/documents/' . $id);
        }
        if (!$this->canProcess($r)) {
            throw new HttpException(403);
        }
        $this->withInput($request, fn () => $this->service->transition($r, $action, (string) $request->input('comment', '')));
        Session::flash('success', __('documents.status_done.' . $action));

        return $this->redirect('/documents/' . $id);
    }

    public function comment(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $own = $this->isOwner($r);
        if (!$own && !$this->canProcess($r)) {
            throw new HttpException(403);
        }
        $this->withInput($request, fn () => $this->service->comment($r, (string) $request->input('comment', ''), !$own && $request->input('internal') === '1', $own));
        Session::flash('success', __('documents.comment_added'));

        return $this->redirect('/documents/' . $id . '#history');
    }

    public function attachment(int $id): Response
    {
        $r = $this->find($id);
        if (!$this->isOwner($r) && !$this->canProcess($r)) {
            throw new HttpException(403);
        }
        $path = $r['attachment_path'] !== null ? DocumentService::path((string) $r['attachment_path']) : null;
        if ($path === null || !is_file($path)) {
            throw new HttpException(404);
        }
        $name = (string) $r['attachment_name'];

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => (string) $r['attachment_mime'],
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function certificate(Request $request, int $id): Response
    {
        $r = $this->find($id);
        if (!$this->isOwner($r) && !$this->canProcess($r)) {
            throw new HttpException(403);
        }
        $document = $this->documents->documentOfRequest($id) ?? throw new HttpException(404);
        $url = TranscriptController::verifyUrl($request, (string) $document['verification_code']);

        return $this->view('documents/certificate', [
            'title' => __('documents.certificate') . ' ' . $document['number'],
            'd' => $document,
            's' => (array) json_decode((string) $document['snapshot'], true),
            'qr' => QrCode::svg($url, 4, __('transcripts.qr_label')),
            'verifyUrl' => $url,
            'canRevoke' => $this->canProcess($r) && $document['status'] === 'valid',
        ], 'layouts/print');
    }

    public function revokeCertificate(Request $request, int $id): Response
    {
        $r = $this->find($id);
        if (!$this->canProcess($r)) {
            throw new HttpException(403);
        }
        $document = $this->documents->documentOfRequest($id) ?? throw new HttpException(404);
        $data = $this->validate($request, ['reason' => 'required|string|max:255']);
        if ($document['status'] === 'valid') {
            $this->service->revoke($document, (string) $data['reason']);
        }
        Session::flash('success', __('documents.certificate_revoked'));

        return $this->redirect('/documents/' . $id);
    }

    // ---- helpers -------------------------------------------------------------

    /** @param array<string, mixed> $type */
    private function form(array $type, ?array $r, int $studentId): Response
    {
        return $this->view('documents/form', [
            'title' => localized($type),
            'type' => $type,
            'r' => $r,
            'student' => (new StudentRepository())->find($studentId),
            'changes' => $r !== null && $r['payload'] !== null ? (array) json_decode((string) $r['payload'], true) : [],
        ]);
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->documents->find($id) ?? throw new HttpException(404);
    }

    private function ownStudentId(): ?int
    {
        return (new JournalRepository())->studentIdOfUser((int) Auth::id());
    }

    private function requireStudent(): int
    {
        if (!Auth::can('documents.request')) {
            throw new HttpException(403);
        }

        return $this->ownStudentId() ?? throw new HttpException(403);
    }

    /** @param array<string, mixed> $r */
    private function isOwner(array $r): bool
    {
        return Auth::can('documents.request') && $this->ownStudentId() === (int) $r['student_id'];
    }

    /** Staff in scope; drafts belong to the student until they are submitted. @param array<string, mixed> $r */
    private function canProcess(array $r): bool
    {
        return $r['status'] !== 'draft'
            && Auth::can('documents.process')
            && AccessScope::allowsGroup($r['current_group_id'] !== null ? (int) $r['current_group_id'] : null);
    }

    /** @return array<string, mixed>|null */
    private function file(Request $request): ?array
    {
        $file = $request->file('attachment');

        return is_array($file) && !is_array($file['name'] ?? null) ? $file : null;
    }

    private function withInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, array_diff_key($request->all(), ['attachment' => 1]));
        }
    }
}
