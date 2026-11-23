<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Repositories\DocumentRepository;
use App\Repositories\StudentRepository;

/**
 * Requests and certificates workflow.
 *
 *   draft ──submit──▶ submitted ──take──▶ in_review ──approve──▶ approved ──complete──▶ completed
 *                         │                 │  ▲                        (document generated / changes applied)
 *                         └──reject──┐      │  └──resubmit── needs_info ◀──request_info──┘
 *                                    ▼      └──reject──▶ rejected
 *
 * The registration number is given on the first submit. Every transition and comment is written to
 * the history; the student is notified about each decision, processors about new and resubmitted requests.
 */
final class DocumentService
{
    /** Personal data a student may ask to change, with validation rules. */
    public const PERSONAL_FIELDS = [
        'last_name' => 'string|max:100',
        'first_name' => 'string|max:100',
        'middle_name' => 'string|max:100',
        'iin' => 'iin',
        'birth_date' => 'date',
        'phone' => 'phone|max:20',
        'email' => 'email|max:190',
    ];

    public const FINAL = ['rejected', 'completed'];

    private const MAX_FILE = 5 * 1024 * 1024;
    private const MIME = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(private readonly DocumentRepository $repository = new DocumentRepository())
    {
    }

    // ---- student side --------------------------------------------------------

    /**
     * Create or update a request (draft or needs_info) and optionally submit it.
     *
     * @param array<string, mixed> $student
     * @param array<string, mixed> $type
     * @param array<string, mixed> $input purpose, copies, details, changes[field]
     * @param array<string, mixed>|null $file uploaded file ($_FILES entry) or null
     */
    public function save(array $student, array $type, array $input, ?array $request, ?array $file, bool $submit): int
    {
        if ($request !== null && !in_array($request['status'], ['draft', 'needs_info'], true)) {
            throw new ValidationException(['details' => [__('documents.not_editable')]]);
        }
        $errors = [];
        $purpose = trim((string) ($input['purpose'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        $copies = (int) ($input['copies'] ?? 1);
        if ((bool) $type['needs_purpose'] && $purpose === '' && $submit) {
            $errors['purpose'][] = __('validation.required', ['field' => __('fields.purpose')]);
        }
        if (mb_strlen($purpose) > 255) {
            $errors['purpose'][] = __('documents.too_long', ['field' => __('fields.purpose'), 'max' => 255]);
        }
        if ($copies < 1 || $copies > 5) {
            $errors['copies'][] = __('validation.between', ['field' => __('fields.copies'), 'min' => 1, 'max' => 5]);
        }
        if ($type['generator'] === 'none' && $details === '' && $submit) {
            $errors['details'][] = __('validation.required', ['field' => __('fields.details')]);
        }
        if (mb_strlen($details) > 5000) {
            $errors['details'][] = __('documents.too_long', ['field' => __('fields.details'), 'max' => 5000]);
        }
        $payload = null;
        if ($type['generator'] === 'personal_data') {
            [$payload, $changeErrors] = $this->changes($student, (array) ($input['changes'] ?? []));
            $errors += $changeErrors;
            if ($payload === [] && $submit) {
                $errors['changes'][] = __('documents.no_changes');
            }
        }
        $upload = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (!(bool) $type['allow_attachment']) {
                $errors['attachment'][] = __('documents.attachment_not_allowed');
            } else {
                $upload = $this->checkUpload($file, $errors);
            }
        }
        $hasFile = $upload !== null || ($request['attachment_path'] ?? null) !== null;
        if ($type['generator'] === 'personal_data' && $submit && !$hasFile) {
            $errors['attachment'][] = __('documents.attachment_required');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $data = [
            'purpose' => $purpose !== '' ? $purpose : null,
            'copies' => $copies,
            'details' => $details !== '' ? $details : null,
            'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($upload !== null) {
            $data += $this->store($upload);
        }

        $id = Database::transaction(function () use ($student, $type, $request, $data): int {
            if ($request === null) {
                $id = $this->repository->create($data + ['type_id' => (int) $type['id'], 'student_id' => (int) $student['id'], 'created_by' => Auth::id()]
                    + ['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => null]);
                $this->repository->log($id, 'status', null, 'draft', null, Auth::id());

                return $id;
            }
            $this->repository->update((int) $request['id'], $data);

            return (int) $request['id'];
        });
        if ($upload !== null && ($request['attachment_path'] ?? null) !== null) {
            $this->removeFile((string) $request['attachment_path']);
        }
        AuditLogger::log($request === null ? 'documents.created' : 'documents.updated', 'document_requests', $id, null, array_diff_key($data, ['payload' => 1]));
        if ($submit) {
            $this->submit($this->repository->find($id) ?? []);
        }

        return $id;
    }

    /** @param array<string, mixed> $request */
    public function submit(array $request): void
    {
        if (!in_array($request['status'], ['draft', 'needs_info'], true)) {
            throw new ValidationException(['status' => [__('documents.not_editable')]]);
        }
        $first = $request['number'] === null;
        $to = $request['assigned_to'] !== null ? 'in_review' : 'submitted';
        $number = Database::transaction(function () use ($request, $first, $to): string {
            $data = ['status' => $to];
            if ($first) {
                $type = $this->repository->type((int) $request['type_id']);
                $data += [
                    'number' => $this->repository->nextNumber('document_requests', __('documents.number_prefix', [], 'ru') . '-' . date('Y') . '-'),
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'due_date' => $type['processing_days'] !== null ? date('Y-m-d', strtotime('+' . (int) $type['processing_days'] . ' days')) : null,
                ];
            }
            $this->repository->update((int) $request['id'], $data);
            $this->repository->log((int) $request['id'], 'status', $request['status'], $to, null, Auth::id());

            return (string) ($data['number'] ?? $request['number']);
        });
        AuditLogger::log('documents.submitted', 'document_requests', (int) $request['id'], ['status' => $request['status']], ['status' => $to, 'number' => $number]);
        $recipients = $request['assigned_to'] !== null ? [(int) $request['assigned_to']] : $this->repository->processors((int) $request['student_id']);
        Notifier::send($recipients, 'document', $first ? 'documents.notify.new_title' : 'documents.notify.resubmitted_title', 'documents.notify.new_body',
            ['number' => $number, 'student' => full_name($request, true)], '/documents/' . $request['id']);
    }

    /** @param array<string, mixed> $request */
    public function deleteDraft(array $request): void
    {
        if ($request['status'] !== 'draft' || $request['number'] !== null) {
            throw new ValidationException(['status' => [__('documents.not_editable')]]);
        }
        $this->repository->delete((int) $request['id']);
        if ($request['attachment_path'] !== null) {
            $this->removeFile((string) $request['attachment_path']);
        }
        AuditLogger::log('documents.deleted', 'document_requests', (int) $request['id'], ['type' => $request['type_code']], null);
    }

    // ---- processing ----------------------------------------------------------

    /** @param array<string, mixed> $request */
    public function transition(array $request, string $action, string $comment): void
    {
        $comment = trim($comment);
        $from = (string) $request['status'];
        $allowed = [
            'take' => ['submitted', 'in_review'],
            'request_info' => ['in_review'],
            'approve' => ['in_review'],
            'reject' => ['submitted', 'in_review', 'needs_info'],
            'complete' => ['approved'],
        ];
        if (!isset($allowed[$action]) || !in_array($from, $allowed[$action], true)) {
            throw new ValidationException(['status' => [__('documents.wrong_status')]]);
        }
        if (in_array($action, ['request_info', 'reject'], true) && $comment === '') {
            throw new ValidationException(['comment' => [__('documents.comment_required')]]);
        }
        if ($action === 'take' && $from === 'in_review' && (int) $request['assigned_to'] === (int) Auth::id()) {
            return; // already mine
        }
        $to = ['take' => 'in_review', 'request_info' => 'needs_info', 'approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed'][$action];

        Database::transaction(function () use ($request, $action, $from, $to, $comment): void {
            $data = ['status' => $to];
            if ($action === 'take') {
                $data['assigned_to'] = Auth::id();
            }
            if (in_array($action, ['approve', 'reject'], true)) {
                $data += ['decided_at' => date('Y-m-d H:i:s'), 'decided_by' => Auth::id(), 'decision_comment' => $comment !== '' ? mb_substr($comment, 0, 1000) : null];
            }
            if ($action === 'complete') {
                $data['completed_at'] = date('Y-m-d H:i:s');
            }
            if ($action === 'approve') {
                $data += $this->generate($request);
            }
            $this->repository->update((int) $request['id'], $data);
            $this->repository->log((int) $request['id'], 'status', $from, $to, $comment !== '' ? $comment : null, Auth::id());
        });
        AuditLogger::log('documents.' . $action, 'document_requests', (int) $request['id'], ['status' => $from], ['status' => $to, 'comment' => $comment]);
        Notifier::send([(int) $request['student_user_id']], 'document', 'documents.notify.status_title', 'documents.notify.status_' . $to,
            ['number' => (string) $request['number'], 'comment' => $comment], '/documents/' . $request['id']);
    }

    /**
     * @param array<string, mixed> $request
     * @param bool $fromStudent comments of the student go to the processor, staff comments to the student
     */
    public function comment(array $request, string $text, bool $internal, bool $fromStudent): void
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            throw new ValidationException(['comment' => [__('documents.comment_invalid')]]);
        }
        if ($request['status'] === 'draft' || in_array($request['status'], self::FINAL, true)) {
            throw new ValidationException(['comment' => [__('documents.wrong_status')]]);
        }
        $this->repository->log((int) $request['id'], 'comment', null, null, $text, Auth::id(), $internal && !$fromStudent);
        if ($internal && !$fromStudent) {
            return;
        }
        $recipients = $fromStudent
            ? ($request['assigned_to'] !== null ? [(int) $request['assigned_to']] : $this->repository->processors((int) $request['student_id']))
            : [(int) $request['student_user_id']];
        Notifier::send($recipients, 'document', 'documents.notify.comment_title', null, ['number' => (string) $request['number']], '/documents/' . $request['id']);
    }

    /** @param array<string, mixed> $document */
    public function revoke(array $document, string $reason): void
    {
        $this->repository->revokeDocument((int) $document['id'], $reason, Auth::id());
        AuditLogger::log('documents.certificate_revoked', 'generated_documents', (int) $document['id'], ['status' => $document['status']], ['status' => 'revoked', 'reason' => $reason]);
    }

    // ---- generation ----------------------------------------------------------

    /**
     * Produce the result of an approved request. Runs inside the approval transaction.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed> columns to store on the request
     */
    private function generate(array $request): array
    {
        return match ($request['generator']) {
            'study_certificate' => $this->certificate($request),
            'transcript' => ['transcript_id' => (new TranscriptService())->issue((int) $request['student_id'])],
            'personal_data' => $this->applyChanges($request),
            default => [],
        };
    }

    /** @param array<string, mixed> $request */
    private function certificate(array $request): array
    {
        $student = (new StudentRepository())->find((int) $request['student_id']) ?? throw new ValidationException(['status' => [__('transcripts.student_missing')]]);
        if ($student['status'] !== 'active' || $student['current_group_id'] === null) {
            throw new ValidationException(['status' => [__('documents.not_studying')]]);
        }
        $group = Database::fetch(
            'SELECT g.code, g.course, g.study_form, q.name_ru AS qualification_ru, q.name_kk AS qualification_kk, sp.code AS specialty_code, sp.name_ru AS specialty_ru, sp.name_kk AS specialty_kk
             FROM `groups` g JOIN specialties sp ON sp.id = g.specialty_id LEFT JOIN qualifications q ON q.id = g.qualification_id WHERE g.id = ?',
            [(int) $student['current_group_id']]
        );
        $enrollment = Database::fetch("SELECT start_date, order_number FROM student_group_history WHERE student_id = ? AND reason IN ('enrollment','restoration') ORDER BY start_date LIMIT 1", [(int) $student['id']]);
        $year = Database::fetchColumn("SELECT title FROM academic_years WHERE status = 'active' LIMIT 1");
        $snapshot = [
            'student' => ['last_name' => $student['last_name'], 'first_name' => $student['first_name'], 'middle_name' => $student['middle_name'],
                'iin' => $student['iin'], 'birth_date' => $student['birth_date'], 'gender' => $student['gender'], 'student_number' => $student['student_number']],
            'study' => ['group' => $group['code'] ?? null, 'course' => $group['course'] ?? null, 'study_form' => $group['study_form'] ?? null,
                'specialty_code' => $group['specialty_code'] ?? null, 'specialty_ru' => $group['specialty_ru'] ?? null, 'specialty_kk' => $group['specialty_kk'] ?? null,
                'qualification_ru' => $group['qualification_ru'] ?? null, 'qualification_kk' => $group['qualification_kk'] ?? null,
                'funding' => $student['funding'], 'enrollment_date' => $enrollment['start_date'] ?? $student['enrollment_date'], 'enrollment_order' => $enrollment['order_number'] ?? null,
                'academic_year' => $year !== null ? (string) $year : null],
            'college' => ['name_ru' => Settings::get('college_name_ru'), 'name_kk' => Settings::get('college_name_kk'),
                'address_ru' => Settings::get('college_address_ru'), 'address_kk' => Settings::get('college_address_kk'),
                'signer_position_ru' => Settings::get('document_signer_position_ru'), 'signer_position_kk' => Settings::get('document_signer_position_kk'),
                'signer_name' => Settings::get('document_signer_name')],
            'purpose' => $request['purpose'],
            'copies' => (int) $request['copies'],
            'request_number' => $request['number'],
            'issued_on' => date('Y-m-d'),
        ];
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->repository->createDocument([
            'request_id' => (int) $request['id'],
            'student_id' => (int) $request['student_id'],
            'kind' => 'study_certificate',
            'number' => $this->repository->nextNumber('generated_documents', __('documents.certificate_prefix', [], 'ru') . '-' . date('Y') . '-'),
            'snapshot' => $json,
            'fingerprint' => hash('sha256', $json),
            'verification_code' => bin2hex(random_bytes(16)),
            'issued_by' => Auth::id(),
        ]);

        return [];
    }

    /** @param array<string, mixed> $request */
    private function applyChanges(array $request): array
    {
        $changes = (array) json_decode((string) $request['payload'], true);
        $student = (new StudentRepository())->find((int) $request['student_id']) ?? throw new ValidationException(['status' => [__('transcripts.student_missing')]]);
        [, $errors] = $this->changes($student, $changes);
        if ($errors !== []) {
            throw new ValidationException(['status' => [__('documents.changes_invalid', ['errors' => implode(' ', array_merge(...array_values($errors)))])]]);
        }
        $userColumns = array_intersect_key($changes, array_flip(['last_name', 'first_name', 'middle_name', 'phone', 'email']));
        $studentColumns = array_intersect_key($changes, array_flip(['iin', 'birth_date']));
        if ($userColumns !== []) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($userColumns)));
            Database::execute("UPDATE users SET {$set} WHERE id = ?", [...array_values($userColumns), (int) $request['student_user_id']]);
        }
        if ($studentColumns !== []) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($studentColumns)));
            Database::execute("UPDATE students SET {$set} WHERE id = ?", [...array_values($studentColumns), (int) $request['student_id']]);
        }
        AuditLogger::log('documents.personal_data_applied', 'students', (int) $request['student_id'], array_intersect_key($student, $changes), $changes);

        return [];
    }

    // ---- helpers -------------------------------------------------------------

    /**
     * Requested personal data changes: only filled fields that differ from the current values.
     *
     * @param array<string, mixed> $student
     * @param array<string, mixed> $input
     * @return array{0: array<string, string>, 1: array<string, list<string>>}
     */
    private function changes(array $student, array $input): array
    {
        $changes = [];
        $rules = [];
        foreach (self::PERSONAL_FIELDS as $field => $rule) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && $value !== (string) ($student[$field] ?? '')) {
                $changes[$field] = $field === 'email' ? mb_strtolower($value) : $value;
                $rules[$field] = $rule;
            }
        }
        $errors = [];
        if ($changes !== []) {
            $validator = Validator::make($changes, $rules);
            foreach ($validator->errors() as $field => $messages) {
                $errors['changes.' . $field] = $messages;
            }
        }
        foreach (['last_name', 'first_name', 'middle_name'] as $name) {
            if (isset($changes[$name]) && !isset($errors['changes.' . $name]) && !preg_match("/^[\\p{L}][\\p{L}\\s'\\-]*$/u", $changes[$name])) {
                $errors['changes.' . $name][] = __('documents.name_invalid');
            }
        }
        if (isset($changes['birth_date']) && !isset($errors['changes.birth_date']) && $changes['birth_date'] >= date('Y-m-d')) {
            $errors['changes.birth_date'][] = __('documents.birth_date_invalid');
        }
        if (isset($changes['email']) && !isset($errors['changes.email'])
            && Database::fetchColumn('SELECT 1 FROM users WHERE email = ? AND id <> ? AND deleted_at IS NULL', [$changes['email'], (int) $student['user_id']]) !== null) {
            $errors['changes.email'][] = __('validation.unique', ['field' => __('fields.email')]);
        }
        if (isset($changes['iin']) && !isset($errors['changes.iin'])
            && Database::fetchColumn('SELECT 1 FROM students WHERE iin = ? AND id <> ?', [$changes['iin'], (int) $student['id']]) !== null) {
            $errors['changes.iin'][] = __('validation.unique', ['field' => __('fields.iin')]);
        }

        return [$changes, $errors];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, list<string>> $errors
     * @return array<string, mixed>|null
     */
    private function checkUpload(array $file, array &$errors): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $errors['attachment'][] = __('documents.upload_failed');

            return null;
        }
        if ((int) $file['size'] > self::MAX_FILE || (int) $file['size'] === 0) {
            $errors['attachment'][] = __('documents.file_too_big', ['mb' => self::MAX_FILE / 1024 / 1024]);

            return null;
        }
        // The type is taken from the file content, never from the browser-supplied name or MIME.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!isset(self::MIME[$mime])) {
            $errors['attachment'][] = __('documents.file_type');

            return null;
        }

        return $file + ['detected_mime' => $mime];
    }

    /** @param array<string, mixed> $upload @return array<string, mixed> */
    private function store(array $upload): array
    {
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create the attachments directory.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::MIME[$upload['detected_mime']];
        if (!move_uploaded_file((string) $upload['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Cannot store the uploaded file.');
        }
        $original = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', basename((string) $upload['name'])) ?: 'file';

        return ['attachment_path' => $name, 'attachment_name' => mb_substr($original, 0, 255), 'attachment_mime' => $upload['detected_mime'], 'attachment_size' => (int) $upload['size']];
    }

    private function removeFile(string $name): void
    {
        $path = self::path($name);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    public static function directory(): string
    {
        return \App\Core\Tenant::storage('documents');
    }

    /** Absolute path of a stored attachment (null for anything that is not one of our generated names). */
    public static function path(string $name): ?string
    {
        return preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $name) ? self::directory() . '/' . $name : null;
    }
}
